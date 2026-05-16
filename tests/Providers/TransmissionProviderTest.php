<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Providers;

use Fatkulnurk\Torrent\Exceptions\RequestException;
use Fatkulnurk\Torrent\Providers\TransmissionProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TransmissionProviderTest extends TestCase
{
    private function createProvider(array $responses, array $config = []): TransmissionProvider
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        $reflection = new \ReflectionClass(TransmissionProvider::class);
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
        $baseUrlProperty->setValue($provider, 'http://localhost:9091');

        return $provider;
    }

    public function testConstructorWithAuth(): void
    {
        $mock = new MockHandler([]);
        $handler = HandlerStack::create($mock);

        $config = [
            'handler' => $handler,
            'timeout' => 10.0,
            'verify_ssl' => true,
            'username' => 'admin',
            'password' => 'password',
        ];

        $reflection = new \ReflectionClass(TransmissionProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, $config);

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:9091');

        $this->assertInstanceOf(TransmissionProvider::class, $provider);
    }

    public function testAddTorrentMagnet(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], '{"result": "success", "arguments": {"torrent-added": {}}}'),
        ]);

        $result = $provider->addTorrent('magnet:?xt=urn:btih:abc123');

        $this->assertTrue($result);
    }

    public function testAddTorrentDuplicate(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], '{"result": "success", "arguments": {"torrent-duplicate": {}}}'),
        ]);

        $result = $provider->addTorrent('magnet:?xt=urn:btih:abc123');

        $this->assertTrue($result);
    }

    public function testGetTorrents(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], json_encode([
                'result' => 'success',
                'arguments' => [
                    'torrents' => [
                        ['hashString' => 'hash1', 'name' => 'test1.torrent'],
                        ['hashString' => 'hash2', 'name' => 'test2.torrent'],
                    ],
                ],
            ])),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(2, $torrents);
        $this->assertSame('hash1', $torrents[0]->hash);
        $this->assertSame('hash2', $torrents[1]->hash);
    }

    public function testGetTorrentsEmpty(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], json_encode([
                'result' => 'success',
                'arguments' => ['torrents' => []],
            ])),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(0, $torrents);
    }

    public function testGetTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], json_encode([
                'result' => 'success',
                'arguments' => [
                    'torrents' => [
                        ['hashString' => 'hash1', 'name' => 'test.torrent'],
                    ],
                ],
            ])),
        ]);

        $torrent = $provider->getTorrent('hash1');

        $this->assertSame('hash1', $torrent->hash);
        $this->assertSame('test.torrent', $torrent->name);
    }

    public function testGetTorrentNotFound(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], json_encode([
                'result' => 'success',
                'arguments' => ['torrents' => []],
            ])),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('not found');

        $provider->getTorrent('nonexistent');
    }

    public function testPauseTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], '{"result": "success", "arguments": {}}'),
        ]);

        $result = $provider->pauseTorrent('hash1');

        $this->assertTrue($result);
    }

    public function testResumeTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], '{"result": "success", "arguments": {}}'),
        ]);

        $result = $provider->resumeTorrent('hash1');

        $this->assertTrue($result);
    }

    public function testRemoveTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], '{"result": "success", "arguments": {}}'),
        ]);

        $result = $provider->removeTorrent('hash1', true);

        $this->assertTrue($result);
    }

    public function testSetDownloadPath(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], '{"result": "success", "arguments": {}}'),
        ]);

        $result = $provider->setDownloadPath('hash1', '/new/path');

        $this->assertTrue($result);
    }

    public function testGetServerStatus(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], json_encode([
                'result' => 'success',
                'arguments' => [
                    'version' => '3.0',
                    'rpc-version' => 15,
                ],
            ])),
        ]);

        $status = $provider->getServerStatus();

        $this->assertSame('3.0', $status->version);
    }

    public function testRequestWithSessionId409(): void
    {
        $mock = new MockHandler([
            new Response(409, ['X-Transmission-Session-Id' => 'new-session-id'], '{}'),
            new Response(200, [], '{"result": "success", "arguments": {}}'),
        ]);
        $handler = HandlerStack::create($mock);

        $reflection = new \ReflectionClass(TransmissionProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, ['timeout' => 10.0, 'verify_ssl' => true]);

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:9091');

        $result = $provider->pauseTorrent('hash1');

        $this->assertTrue($result);
    }

    public function testApiErrorThrowsException(): void
    {
        $provider = $this->createProvider([
            new Response(200, [], '{"result": "failure", "arguments": {}}'),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Transmission API error');

        $provider->getTorrents();
    }
}
