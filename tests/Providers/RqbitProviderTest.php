<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Providers;

use Fatkulnurk\Torrent\Exceptions\RequestException;
use Fatkulnurk\Torrent\Providers\RqbitProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RqbitProviderTest extends TestCase
{
    private function createProvider(array $responses, array $config = []): RqbitProvider
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        $config['handler'] = $handler;

        $reflection = new \ReflectionClass(RqbitProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, array_merge(['timeout' => 10.0, 'verify_ssl' => true], $config));

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://127.0.0.1:3030');

        return $provider;
    }

    public function testAddTorrentMagnet(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 1,
                'details' => ['info_hash' => 'abc', 'name' => 'test'],
                'output_folder' => '/downloads',
                'seen_peers' => null,
            ])),
        ]);

        $result = $provider->addTorrent('magnet:?xt=urn:btih:abc123');

        $this->assertTrue($result);
    }

    public function testAddTorrentUrl(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 1,
                'details' => ['info_hash' => 'def', 'name' => 'url-test'],
                'output_folder' => '/downloads',
                'seen_peers' => null,
            ])),
        ]);

        $result = $provider->addTorrent('https://example.com/test.torrent');

        $this->assertTrue($result);
    }

    public function testAddTorrentBase64(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 1,
                'details' => ['info_hash' => 'ghi', 'name' => 'base64-test'],
                'output_folder' => '/downloads',
                'seen_peers' => null,
            ])),
        ]);

        $result = $provider->addTorrent(base64_encode('fake torrent bytes'));

        $this->assertTrue($result);
    }

    public function testAddTorrentInvalidBase64(): void
    {
        $provider = $this->createProvider([]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Invalid base64 encoded torrent data');

        $provider->addTorrent('not-valid-base64!!!');
    }

    public function testAddTorrentWithOptions(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 1,
                'details' => ['info_hash' => 'xyz', 'name' => 'opts-test'],
                'output_folder' => '/custom',
                'seen_peers' => null,
            ])),
        ]);

        $result = $provider->addTorrent('magnet:?xt=urn:btih:test123', [
            'output_folder' => '/custom/downloads',
        ]);

        $this->assertTrue($result);
    }

    public function testGetTorrents(): void
    {
        $response = [
            'torrents' => [
                [
                    'id' => 0,
                    'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'name' => 'test1.torrent',
                    'output_folder' => '/downloads',
                    'total_pieces' => 100,
                    'stats' => [
                        'state' => 'live',
                        'total_bytes' => 2097152,
                        'progress_bytes' => 1048576,
                        'uploaded_bytes' => 0,
                        'finished' => false,
                        'file_progress' => [],
                        'error' => null,
                    ],
                ],
                [
                    'id' => 1,
                    'info_hash' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                    'name' => 'test2.torrent',
                    'output_folder' => '/downloads',
                    'total_pieces' => 200,
                    'stats' => [
                        'state' => 'paused',
                        'total_bytes' => 4194304,
                        'progress_bytes' => 0,
                        'uploaded_bytes' => 0,
                        'finished' => false,
                        'file_progress' => [],
                        'error' => null,
                    ],
                ],
            ],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($response)),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(2, $torrents);
        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $torrents[0]->hash);
        $this->assertSame('test1.torrent', $torrents[0]->name);
        $this->assertSame(2, $torrents[0]->status);
        $this->assertSame(2097152, $torrents[0]->totalSize);
        $this->assertSame(1048576, $torrents[0]->leftUntilDone);
        $this->assertSame(0.5, $torrents[0]->percentDone);
        $this->assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $torrents[1]->hash);
        $this->assertSame(0, $torrents[1]->status);
        $this->assertSame(4194304, $torrents[1]->totalSize);
        $this->assertSame(0.0, $torrents[1]->percentDone);
    }

    public function testGetTorrentsEmpty(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['torrents' => []])),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(0, $torrents);
    }

    public function testGetTorrent(): void
    {
        $detailsResponse = [
            'id' => 0,
            'info_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'name' => 'test.torrent',
            'output_folder' => '/downloads',
            'total_pieces' => 100,
            'files' => [
                [
                    'name' => 'file.bin',
                    'components' => ['file.bin'],
                    'length' => 1048576,
                    'included' => true,
                    'attributes' => [],
                ],
            ],
        ];

        $statsResponse = [
            'state' => 'live',
            'total_bytes' => 1048576,
            'progress_bytes' => 524288,
            'uploaded_bytes' => 0,
            'finished' => false,
            'file_progress' => [524288],
            'error' => null,
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($detailsResponse)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode($statsResponse)),
        ]);

        $torrent = $provider->getTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $torrent->hash);
        $this->assertSame('test.torrent', $torrent->name);
        $this->assertSame(2, $torrent->status);
        $this->assertSame(1048576, $torrent->totalSize);
        $this->assertSame(524288, $torrent->leftUntilDone);
        $this->assertSame('/downloads', $torrent->downloadDir);
        $this->assertSame(0.5, $torrent->percentDone);
    }

    public function testGetTorrentNotFound(): void
    {
        $provider = $this->createProvider([
            new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'torrent not found'])),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('HTTP request failed');

        $provider->getTorrent('nonexistent');
    }

    public function testPauseTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $result = $provider->pauseTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->assertTrue($result);
    }

    public function testResumeTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $result = $provider->resumeTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $this->assertTrue($result);
    }

    public function testRemoveTorrentWithFiles(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $result = $provider->removeTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', true);

        $this->assertTrue($result);
    }

    public function testRemoveTorrentWithoutFiles(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]);

        $result = $provider->removeTorrent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', false);

        $this->assertTrue($result);
    }

    public function testSetDownloadPathNotSupported(): void
    {
        $provider = $this->createProvider([]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('setDownloadPath is not supported by rqbit API');

        $provider->setDownloadPath('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '/new/path');
    }

    public function testGetServerStatus(): void
    {
        $statsResponse = [
            'counters' => [
                'fetched_bytes' => 1048576,
                'uploaded_bytes' => 0,
                'blocked_incoming' => 0,
                'blocked_outgoing' => 0,
            ],
            'download_speed' => ['mbps' => 5.0, 'human_readable' => '5.00 MiB/s'],
            'upload_speed' => ['mbps' => 1.0, 'human_readable' => '1.00 MiB/s'],
            'peers' => [
                'connecting' => 0,
                'live_tcp' => 2,
                'live_utp' => 1,
                'live_socks' => 0,
                'dead' => 0,
                'not_needed' => 0,
                'queued' => 0,
                'seen' => 10,
                'steals' => 0,
            ],
            'uptime_seconds' => 3600,
            'connections' => [],
        ];

        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($statsResponse)),
        ]);

        $status = $provider->getServerStatus();

        $this->assertNull($status->version);
        $this->assertNull($status->apiVersion);
    }
}
