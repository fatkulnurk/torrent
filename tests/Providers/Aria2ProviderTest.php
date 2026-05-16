<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Providers;

use Fatkulnurk\Torrent\Exceptions\RequestException;
use Fatkulnurk\Torrent\Providers\Aria2Provider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class Aria2ProviderTest extends TestCase
{
    private function createProvider(array $responses, array $config = []): Aria2Provider
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        $config['handler'] = $handler;

        $reflection = new \ReflectionClass(Aria2Provider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, array_merge(['timeout' => 10.0, 'verify_ssl' => true], $config));

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:6800');

        return $provider;
    }

    private function jsonRpcResult(mixed $result): string
    {
        return json_encode(['jsonrpc' => '2.0', 'result' => $result, 'id' => 1]);
    }

    private function jsonRpcError(string $message, int $code = 1): string
    {
        return json_encode(['jsonrpc' => '2.0', 'result' => null, 'error' => ['code' => $code, 'message' => $message], 'id' => 1]);
    }

    public function testAddTorrentMagnet(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult('gid1')),
        ]);

        $result = $provider->addTorrent('magnet:?xt=urn:btih:abc123');

        $this->assertTrue($result);
    }

    public function testAddTorrentUrl(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult('gid2')),
        ]);

        $result = $provider->addTorrent('https://example.com/file.torrent');

        $this->assertTrue($result);
    }

    public function testAddTorrentBase64(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult('gid3')),
        ]);

        $result = $provider->addTorrent(base64_encode('fake torrent data'));

        $this->assertTrue($result);
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
        $activeTorrents = [
            [
                'gid' => 'gid1',
                'infoHash' => 'aaaa',
                'status' => 'active',
                'totalLength' => '1048576',
                'completedLength' => '524288',
                'uploadLength' => '0',
                'dir' => '/downloads',
                'bittorrent' => ['name' => 'active.torrent'],
                'files' => [['path' => '/downloads/active.torrent', 'length' => '1048576']],
            ],
        ];

        $waitingTorrents = [
            [
                'gid' => 'gid2',
                'infoHash' => 'bbbb',
                'status' => 'waiting',
                'totalLength' => '2097152',
                'completedLength' => '0',
                'uploadLength' => '0',
                'dir' => '/downloads',
                'bittorrent' => ['name' => 'waiting.torrent'],
                'files' => [['path' => '/downloads/waiting.torrent', 'length' => '2097152']],
            ],
        ];

        $stoppedTorrents = [
            [
                'gid' => 'gid3',
                'infoHash' => 'cccc',
                'status' => 'complete',
                'totalLength' => '4194304',
                'completedLength' => '4194304',
                'uploadLength' => '1048576',
                'dir' => '/downloads',
                'bittorrent' => ['name' => 'done.torrent'],
                'files' => [['path' => '/downloads/done.torrent', 'length' => '4194304']],
            ],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult($activeTorrents)),
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult($waitingTorrents)),
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult($stoppedTorrents)),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(3, $torrents);
        $this->assertSame('gid1', $torrents[0]->hash);
        $this->assertSame('active.torrent', $torrents[0]->name);
        $this->assertSame(2, $torrents[0]->status);
        $this->assertSame(1048576, $torrents[0]->totalSize);
        $this->assertSame(524288, $torrents[0]->leftUntilDone);
        $this->assertSame(0.5, $torrents[0]->percentDone);
        $this->assertSame('gid2', $torrents[1]->hash);
        $this->assertSame(1, $torrents[1]->status);
        $this->assertSame(0.0, $torrents[1]->percentDone);
        $this->assertSame('gid3', $torrents[2]->hash);
        $this->assertSame(3, $torrents[2]->status);
        $this->assertSame(1.0, $torrents[2]->percentDone);
    }

    public function testGetTorrentsEmpty(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult([])),
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult([])),
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult([])),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(0, $torrents);
    }

    public function testGetTorrent(): void
    {
        $torrentData = [
            'gid' => 'gid1',
            'infoHash' => 'aaaa',
            'status' => 'active',
            'totalLength' => '1048576',
            'completedLength' => '524288',
            'uploadLength' => '0',
            'dir' => '/downloads',
            'bittorrent' => ['name' => 'test.torrent'],
            'files' => [['path' => '/downloads/test.torrent', 'length' => '1048576']],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult($torrentData)),
        ]);

        $torrent = $provider->getTorrent('gid1');

        $this->assertSame('gid1', $torrent->hash);
        $this->assertSame('test.torrent', $torrent->name);
        $this->assertSame(2, $torrent->status);
        $this->assertSame(1048576, $torrent->totalSize);
        $this->assertSame('/downloads', $torrent->downloadDir);
    }

    public function testGetTorrentNotFound(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult(null)),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('not found');

        $provider->getTorrent('nonexistent');
    }

    public function testPauseTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult('OK')),
        ]);

        $result = $provider->pauseTorrent('gid1');

        $this->assertTrue($result);
    }

    public function testResumeTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult('OK')),
        ]);

        $result = $provider->resumeTorrent('gid1');

        $this->assertTrue($result);
    }

    public function testRemoveTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult('OK')),
        ]);

        $result = $provider->removeTorrent('gid1', false);

        $this->assertTrue($result);
    }

    public function testSetDownloadPath(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult('OK')),
        ]);

        $result = $provider->setDownloadPath('gid1', '/new/path');

        $this->assertTrue($result);
    }

    public function testGetServerStatus(): void
    {
        $versionData = [
            'version' => '1.37.0',
            'enabledFeatures' => ['Async DNS', 'BitTorrent', 'Firefox3 Cookie', 'GZip', 'HTTPS', 'Message Digest', 'Metalink', 'XML-RPC'],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult($versionData)),
        ]);

        $status = $provider->getServerStatus();

        $this->assertSame('1.37.0', $status->version);
    }

    public function testApiErrorThrowsException(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcError('Method not found', 2)),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('aria2 RPC error: Method not found');

        $provider->getTorrents();
    }

    public function testGetTorrentWithHttpFallbackName(): void
    {
        $torrentData = [
            'gid' => 'gid1',
            'status' => 'active',
            'totalLength' => '1024',
            'completedLength' => '512',
            'uploadLength' => '0',
            'dir' => '/downloads',
            'files' => [
                ['path' => '/downloads/ubuntu.iso', 'length' => '1024'],
            ],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult($torrentData)),
        ]);

        $torrent = $provider->getTorrent('gid1');

        $this->assertSame('ubuntu.iso', $torrent->name);
    }

    public function testWithSecretToken(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], $this->jsonRpcResult('OK')),
        ], ['secret' => 'mysecret']);

        $result = $provider->pauseTorrent('gid1');

        $this->assertTrue($result);
    }
}
