<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Providers;

use Fatkulnurk\Torrent\Exceptions\RequestException;
use Fatkulnurk\Torrent\Providers\PorlaProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class PorlaProviderTest extends TestCase
{
    private function createProvider(array $responses, array $config = []): PorlaProvider
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        $reflection = new \ReflectionClass(PorlaProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, array_merge(['timeout' => 10.0, 'verify_ssl' => true], $config));

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:1337');

        return $provider;
    }

    private function jsonRpcResult(mixed $result): string
    {
        return json_encode(['jsonrpc' => '2.0', 'result' => $result, 'id' => 1], JSON_THROW_ON_ERROR);
    }

    private function jsonRpcError(string $message, int $code = 1): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'result' => null,
            'error' => ['code' => $code, 'message' => $message],
            'id' => 1,
        ], JSON_THROW_ON_ERROR);
    }

    public function testAddTorrentMagnet(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult([
                'info_hash' => ['dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c', null],
            ])),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(PorlaProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, [
            'timeout' => 10.0,
            'verify_ssl' => true,
            'token' => 'test-jwt',
            'save_path' => '/dl',
        ]);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:1337');

        $result = $provider->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c');

        $this->assertTrue($result);
        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertSame('torrents.add', $body['method']);
        $this->assertSame('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c', $body['params']['magnet_uri']);
        $this->assertSame('/dl', $body['params']['save_path']);
        $this->assertSame('Bearer test-jwt', $container[0]['request']->getHeaderLine('Authorization'));
    }

    public function testAddTorrentBase64(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult([
                'info_hash' => ['abc', null],
            ])),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(PorlaProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, ['timeout' => 10.0, 'verify_ssl' => true, 'save_path' => '/dl']);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:1337');

        $encoded = base64_encode('fake torrent data');
        $result = $provider->addTorrent($encoded);

        $this->assertTrue($result);
        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertSame($encoded, $body['params']['ti']);
    }

    public function testAddTorrentInvalidBase64(): void
    {
        $provider = $this->createProvider([]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Invalid base64 encoded torrent data');

        $provider->addTorrent('not-valid-base64!!!');
    }

    public function testGetTorrents(): void
    {
        $list = [
            'page' => 0,
            'page_size' => 100,
            'torrents_total' => 2,
            'torrents_total_unfiltered' => 2,
            'torrents' => [
                [
                    'info_hash' => ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', null],
                    'name' => 'test1.torrent',
                    'size' => 2097152,
                    'total' => 2097152,
                    'total_done' => 1048576,
                    'progress' => 0.5,
                    'save_path' => '/dl',
                    'flags' => 0,
                ],
                [
                    'info_hash' => ['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', null],
                    'name' => 'test2.torrent',
                    'size' => 4194304,
                    'total' => 4194304,
                    'total_done' => 4194304,
                    'progress' => 1.0,
                    'save_path' => '/dl',
                    'flags' => 0,
                ],
            ],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult($list)),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(2, $torrents);
        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $torrents[0]->hash);
        $this->assertSame('test1.torrent', $torrents[0]->name);
        $this->assertSame(1, $torrents[0]->status);
        $this->assertSame(2097152, $torrents[0]->totalSize);
        $this->assertSame(1048576, $torrents[0]->leftUntilDone);
        $this->assertSame(0.5, $torrents[0]->percentDone);
        $this->assertSame('/dl', $torrents[0]->downloadDir);
        $this->assertSame(2, $torrents[1]->status);
        $this->assertSame(1.0, $torrents[1]->percentDone);
    }

    public function testGetTorrentsEmpty(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult([
                'page' => 0,
                'page_size' => 100,
                'torrents_total' => 0,
                'torrents' => [],
            ])),
        ]);

        $this->assertCount(0, $provider->getTorrents());
    }

    public function testGetTorrent(): void
    {
        $list = [
            'page' => 0,
            'page_size' => 100,
            'torrents_total' => 1,
            'torrents' => [
                [
                    'info_hash' => ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', null],
                    'name' => 'ubuntu.iso',
                    'size' => 1024,
                    'total_done' => 512,
                    'progress' => 0.5,
                    'save_path' => '/dl',
                    'flags' => 16,
                ],
            ],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult($list)),
        ]);

        $torrent = $provider->getTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $torrent->hash);
        $this->assertSame('ubuntu.iso', $torrent->name);
        $this->assertSame(0, $torrent->status);
        $this->assertSame(512, $torrent->leftUntilDone);
    }

    public function testGetTorrentNotFound(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult([
                'page' => 0,
                'page_size' => 100,
                'torrents_total' => 0,
                'torrents' => [],
            ])),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('not found');

        $provider->getTorrent('nonexistent');
    }

    public function testPauseTorrent(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult(null)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(PorlaProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, ['timeout' => 10.0, 'verify_ssl' => true]);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:1337');

        $result = $provider->pauseTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->assertTrue($result);
        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertSame('torrents.pause', $body['method']);
        $this->assertSame(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', null], $body['params']['info_hash']);
    }

    public function testResumeTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult(null)),
        ]);

        $this->assertTrue($provider->resumeTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'));
    }

    public function testRemoveTorrent(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult(null)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(PorlaProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, ['timeout' => 10.0, 'verify_ssl' => true]);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:1337');

        $result = $provider->removeTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', true);

        $this->assertTrue($result);
        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertSame('torrents.remove', $body['method']);
        $this->assertSame([['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', null]], $body['params']['info_hashes']);
        $this->assertTrue($body['params']['remove_data']);
    }

    public function testSetDownloadPath(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult(null)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(PorlaProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, ['timeout' => 10.0, 'verify_ssl' => true]);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:1337');

        $result = $provider->setDownloadPath('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '/new/path');

        $this->assertTrue($result);
        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertSame('torrents.move', $body['method']);
        $this->assertSame('/new/path', $body['params']['path']);
    }

    public function testGetServerStatus(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult([
                'porla' => [
                    'branch' => 'main',
                    'commitish' => 'abc',
                    'version' => '0.41.0',
                ],
                'libtorrent' => [
                    'version' => '2.0.10',
                ],
            ])),
        ]);

        $status = $provider->getServerStatus();

        $this->assertSame('0.41.0', $status->version);
    }

    public function testApiErrorThrowsException(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcError('Method not found', -32601)),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Porla RPC error: Method not found');

        $provider->getTorrents();
    }

    public function testPauseTorrentV2Hash(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult(null)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $reflection = new \ReflectionClass(PorlaProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, ['timeout' => 10.0, 'verify_ssl' => true]);
        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:1337');

        $v2 = str_repeat('ab', 32);
        $provider->pauseTorrent($v2);

        $body = json_decode((string) $container[0]['request']->getBody(), true);
        $this->assertSame([null, $v2], $body['params']['info_hash']);
    }
}
