<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Providers;

use Fatkulnurk\Torrent\Exceptions\AuthenticationException;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use Fatkulnurk\Torrent\Providers\QbittorrentProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class QbittorrentProviderTest extends TestCase
{
    private function createProvider(array $responses, array $config = []): QbittorrentProvider
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        $config['handler'] = $handler;
        $config['username'] ??= 'admin';
        $config['password'] ??= 'password';

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $clientConfig = [
            'timeout' => 10.0,
            'verify_ssl' => true,
        ];

        $clientConfig = array_merge($clientConfig, $config);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, $clientConfig);

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:8080');

        return $provider;
    }

    public function testAuthenticateSuccess(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $cookieProperty = $reflection->getProperty('cookie');
        $cookie = $cookieProperty->getValue($provider);

        $this->assertSame('SID=abc123', $cookie);
    }

    public function testAuthenticateNoCredentials(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Username and password are required');

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, ['timeout' => 10.0, 'verify_ssl' => true]);

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:8080');

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue(
            $provider,
            new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([]))])
        );

        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);
    }

    public function testAuthenticateNoSetCookie(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], ''),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('No Set-Cookie header received');

        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);
    }

    public function testAuthenticateNoSidInCookie(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'other=value'], ''),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Failed to extract SID');

        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);
    }

    public function testAddTorrentMagnet(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], '{"saveData": true}'),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->addTorrent('magnet:?xt=urn:btih:abc123');

        $this->assertTrue($result);
    }

    public function testGetTorrents(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                [
                    'hash' => 'hash1',
                    'name' => 'test1.torrent',
                    'state' => 'downloading',
                    'total_size' => 2097152,
                    'amount_left' => 1048576,
                    'save_path' => '/downloads',
                    'progress' => 0.5,
                ],
                [
                    'hash' => 'hash2',
                    'name' => 'test2.torrent',
                    'state' => 'uploading',
                    'total_size' => 1048576,
                    'amount_left' => 0,
                    'save_path' => '/downloads',
                    'progress' => 1.0,
                ],
            ])),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $torrents = $provider->getTorrents();

        $this->assertCount(2, $torrents);
        $this->assertSame('hash1', $torrents[0]->hash);
        $this->assertSame('test1.torrent', $torrents[0]->name);
        $this->assertSame(1, $torrents[0]->status);
        $this->assertSame(2097152, $torrents[0]->totalSize);
        $this->assertSame(1048576, $torrents[0]->leftUntilDone);
        $this->assertSame('/downloads', $torrents[0]->downloadDir);
        $this->assertSame(0.5, $torrents[0]->percentDone);
        $this->assertSame('hash2', $torrents[1]->hash);
        $this->assertSame(2, $torrents[1]->status);
        $this->assertSame(1.0, $torrents[1]->percentDone);
    }

    public function testGetTorrent(): void
    {
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                [
                    'hash' => 'hash1',
                    'name' => 'test.torrent',
                    'state' => 'pausedDL',
                    'total_size' => 1024,
                    'amount_left' => 512,
                    'save_path' => '/dl',
                    'progress' => 0.5,
                ],
            ])),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, [
            'timeout' => 10.0,
            'verify_ssl' => true,
            'username' => 'admin',
            'password' => 'password',
        ]);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:8080');

        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $torrent = $provider->getTorrent('hash1');

        $this->assertSame('hash1', $torrent->hash);
        $this->assertSame('test.torrent', $torrent->name);
        $this->assertSame(0, $torrent->status);
        $this->assertSame(1024, $torrent->totalSize);
        $this->assertSame(512, $torrent->leftUntilDone);
        $this->assertSame('/dl', $torrent->downloadDir);
        $this->assertSame(0.5, $torrent->percentDone);
        $this->assertSame('hashes=hash1', $container[1]['request']->getUri()->getQuery());
    }

    public function testGetTorrentNotFound(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], '[]'),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('not found');

        $provider->getTorrent('nonexistent');
    }

    public function testPauseTorrent(): void
    {
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], 'Ok.'),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, [
            'timeout' => 10.0,
            'verify_ssl' => true,
            'username' => 'admin',
            'password' => 'password',
        ]);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:8080');

        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->pauseTorrent('hash1');

        $this->assertTrue($result);
        $this->assertStringContainsString('api/v2/torrents/stop', (string) $container[1]['request']->getUri());
        $this->assertSame('hashes=hash1', (string) $container[1]['request']->getBody());
    }

    public function testResumeTorrent(): void
    {
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], 'Ok.'),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, [
            'timeout' => 10.0,
            'verify_ssl' => true,
            'username' => 'admin',
            'password' => 'password',
        ]);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:8080');

        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->resumeTorrent('hash1');

        $this->assertTrue($result);
        $this->assertStringContainsString('api/v2/torrents/start', (string) $container[1]['request']->getUri());
        $this->assertSame('hashes=hash1', (string) $container[1]['request']->getBody());
    }

    public function testRemoveTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], '{"saveData": true}'),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->removeTorrent('hash1', true);

        $this->assertTrue($result);
    }

    public function testSetDownloadPath(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], '{"saveData": true}'),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->setDownloadPath('hash1', '/new/path');

        $this->assertTrue($result);
    }

    public function testGetServerStatus(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'QBT_SID_8080=abc123; path=/'], ''),
            new Response(200, [], 'v5.2.0'),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $status = $provider->getServerStatus();

        $this->assertSame('v5.2.0', $status->version);
    }
}
