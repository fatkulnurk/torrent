<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Providers;

use Fatkulnurk\Torrent\Exceptions\AuthenticationException;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use Fatkulnurk\Torrent\Providers\DelugeProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class DelugeProviderTest extends TestCase
{
    private function createProvider(array $responses, array $config = []): DelugeProvider
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        $config['handler'] = $handler;
        $config['password'] ??= 'deluge';

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, array_merge(['timeout' => 10.0, 'verify_ssl' => true], $config));

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:8112');

        return $provider;
    }

    private function jsonRpcResult(mixed $result): string
    {
        return json_encode(['result' => $result, 'error' => null, 'id' => 1]);
    }

    private function jsonRpcError(string $message, int $code = 1): string
    {
        return json_encode(['result' => null, 'error' => ['code' => $code, 'message' => $message], 'id' => 1]);
    }

    public function testAuthenticateSuccess(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $sessionCookieProperty = $reflection->getProperty('sessionCookie');
        $sessionCookie = $sessionCookieProperty->getValue($provider);

        $this->assertSame('_session_id=abc123', $sessionCookie);
    }

    public function testAuthenticateNoPassword(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Password is required');

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, ['timeout' => 10.0, 'verify_ssl' => true]);

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:8112');

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue(
            $provider,
            new \GuzzleHttp\Client(['handler' => HandlerStack::create(new MockHandler([]))])
        );

        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);
    }

    public function testAuthenticateWrongPassword(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], $this->jsonRpcResult(false)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Deluge authentication failed');

        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);
    }

    public function testAddTorrentMagnet(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult(true)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->addTorrent('magnet:?xt=urn:btih:abc123');

        $this->assertTrue($result);
    }

    public function testAddTorrentInvalidBase64(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Invalid base64 encoded torrent data');

        $provider->addTorrent('not-valid-base64!!!');
    }

    public function testGetTorrents(): void
    {
        $torrentsData = [
            'hash1' => [
                'hash' => 'hash1',
                'name' => 'test1.torrent',
                'state' => 'Seeding',
                'total_size' => 1048576,
                'total_done' => 1048576,
                'download_location' => '/downloads',
                'progress' => 100.0,
            ],
            'hash2' => [
                'hash' => 'hash2',
                'name' => 'test2.torrent',
                'state' => 'Downloading',
                'total_size' => 2097152,
                'total_done' => 1048576,
                'download_location' => '/downloads',
                'progress' => 50.0,
            ],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult($torrentsData)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $torrents = $provider->getTorrents();

        $this->assertCount(2, $torrents);
        $this->assertSame('hash1', $torrents[0]->hash);
        $this->assertSame('test1.torrent', $torrents[0]->name);
        $this->assertSame(2, $torrents[0]->status);
        $this->assertSame(1048576, $torrents[0]->totalSize);
        $this->assertSame(1.0, $torrents[0]->percentDone);
        $this->assertSame('hash2', $torrents[1]->hash);
        $this->assertSame(1, $torrents[1]->status);
        $this->assertSame(0.5, $torrents[1]->percentDone);
        $this->assertSame(1048576, $torrents[1]->leftUntilDone);
    }

    public function testGetTorrentsEmpty(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([])),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $torrents = $provider->getTorrents();

        $this->assertCount(0, $torrents);
    }

    public function testGetTorrent(): void
    {
        $torrentData = [
            'hash' => 'hash1',
            'name' => 'test.torrent',
            'state' => 'Seeding',
            'total_size' => 1048576,
            'total_done' => 1048576,
            'download_location' => '/downloads',
            'progress' => 100.0,
        ];

        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult($torrentData)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $torrent = $provider->getTorrent('hash1');

        $this->assertSame('hash1', $torrent->hash);
        $this->assertSame('test.torrent', $torrent->name);
        $this->assertSame(2, $torrent->status);
        $this->assertSame('/downloads', $torrent->downloadDir);
    }

    public function testGetTorrentNotFound(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([])),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('not found');

        $provider->getTorrent('nonexistent');
    }

    public function testPauseTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult(true)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->pauseTorrent('hash1');

        $this->assertTrue($result);
    }

    public function testResumeTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult(true)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->resumeTorrent('hash1');

        $this->assertTrue($result);
    }

    public function testRemoveTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult(true)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->removeTorrent('hash1', true);

        $this->assertTrue($result);
    }

    public function testSetDownloadPath(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult(true)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $result = $provider->setDownloadPath('hash1', '/new/path');

        $this->assertTrue($result);
    }

    public function testGetServerStatus(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult('2.1.1')),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $status = $provider->getServerStatus();

        $this->assertSame('2.1.1', $status->version);
    }

    public function testApiErrorThrowsException(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Set-Cookie' => '_session_id=abc123; Path=/; HttpOnly'], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcResult([['host_id_1', '127.0.0.1', 58846, 'local', 'pass']])),
            new Response(200, [], $this->jsonRpcResult(true)),
            new Response(200, [], $this->jsonRpcError('Method not found', 2)),
        ]);

        $reflection = new \ReflectionClass(DelugeProvider::class);
        $initialize = $reflection->getMethod('initialize');
        $initialize->invoke($provider);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Deluge API error: Method not found');

        $provider->getTorrents();
    }
}
