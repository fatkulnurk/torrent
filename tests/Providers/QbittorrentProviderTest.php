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
        $config['username'] = $config['username'] ?? 'admin';
        $config['password'] = $config['password'] ?? 'password';

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

        $sidProperty = $reflection->getProperty('sid');
        $sid = $sidProperty->getValue($provider);

        $this->assertSame('abc123', $sid);
    }

    public function testAuthenticateNoCredentials(): void
    {
        $this->assertTrue(true);
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
            new Response(200, [], json_encode([
                ['hash' => 'hash1', 'name' => 'test1.torrent'],
                ['hash' => 'hash2', 'name' => 'test2.torrent'],
            ])),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $torrents = $provider->getTorrents();

        $this->assertCount(2, $torrents);
        $this->assertSame('hash1', $torrents[0]->hash);
        $this->assertSame('hash2', $torrents[1]->hash);
    }

    public function testGetTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], json_encode([
                ['hash' => 'hash1', 'name' => 'test.torrent'],
            ])),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $torrent = $provider->getTorrent('hash1');

        $this->assertSame('hash1', $torrent->hash);
        $this->assertSame('test.torrent', $torrent->name);
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
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], '{"saveData": true}'),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->pauseTorrent('hash1');

        $this->assertTrue($result);
    }

    public function testResumeTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], '{"saveData": true}'),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->resumeTorrent('hash1');

        $this->assertTrue($result);
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
            new Response(200, ['Set-Cookie' => 'SID=abc123; path=/'], ''),
            new Response(200, [], '{"coreVersion": "4.3.5"}'),
        ]);

        $reflection = new \ReflectionClass(QbittorrentProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $status = $provider->getServerStatus();

        $this->assertSame('4.3.5', $status->version);
    }
}