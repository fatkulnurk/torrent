<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Providers;

use Fatkulnurk\Torrent\Exceptions\RequestException;
use Fatkulnurk\Torrent\Providers\RTorrentProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RTorrentProviderTest extends TestCase
{
    private function createProvider(array $responses, array $config = []): RTorrentProvider
    {
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        $config['handler'] = $handler;

        $reflection = new \ReflectionClass(RTorrentProvider::class);
        $provider = $reflection->newInstanceWithoutConstructor();

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setValue($provider, new \GuzzleHttp\Client(['handler' => $handler]));

        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($provider, array_merge(['timeout' => 10.0, 'verify_ssl' => true], $config));

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setValue($provider, 'http://localhost:8080');

        return $provider;
    }

    public function testAddTorrentMagnet(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('')),
        ]);

        $result = $provider->addTorrent('magnet:?xt=urn:btih:abc123');

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
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('
                <array>
                    <data>
                        <value>
                            <array>
                                <data>
                                    <value><string>hash1</string></value>
                                    <value><string>test1.torrent</string></value>
                                    <value><int>1</int></value>
                                    <value><i8>1048576</i8></value>
                                    <value><i8>0</i8></value>
                                    <value><string>/downloads</string></value>
                                    <value><double>1.0</double></value>
                                    <value><int>1</int></value>
                                    <value><int>1</int></value>
                                </data>
                            </array>
                        </value>
                        <value>
                            <array>
                                <data>
                                    <value><string>hash2</string></value>
                                    <value><string>test2.torrent</string></value>
                                    <value><int>0</int></value>
                                    <value><i8>2097152</i8></value>
                                    <value><i8>1048576</i8></value>
                                    <value><string>/downloads</string></value>
                                    <value><double>0.5</double></value>
                                    <value><int>0</int></value>
                                    <value><int>0</int></value>
                                </data>
                            </array>
                        </value>
                    </data>
                </array>
            ')),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(2, $torrents);
        $this->assertSame('hash1', $torrents[0]->hash);
        $this->assertSame('test1.torrent', $torrents[0]->name);
        $this->assertSame(2, $torrents[0]->status);
        $this->assertSame(1048576, $torrents[0]->totalSize);
        $this->assertSame(1.0, $torrents[0]->percentDone);
        $this->assertSame('hash2', $torrents[1]->hash);
        $this->assertSame(0, $torrents[1]->status);
        $this->assertSame(0.5, $torrents[1]->percentDone);
    }

    public function testGetTorrentsEmpty(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('
                <array>
                    <data>
                    </data>
                </array>
            ')),
        ]);

        $torrents = $provider->getTorrents();

        $this->assertCount(0, $torrents);
    }

    public function testGetTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('
                <array>
                    <data>
                        <value>
                            <array>
                                <data>
                                    <value><string>hash1</string></value>
                                    <value><string>test.torrent</string></value>
                                    <value><int>1</int></value>
                                    <value><i8>1048576</i8></value>
                                    <value><i8>0</i8></value>
                                    <value><string>/downloads</string></value>
                                    <value><double>1.0</double></value>
                                    <value><int>1</int></value>
                                    <value><int>1</int></value>
                                </data>
                            </array>
                        </value>
                    </data>
                </array>
            ')),
        ]);

        $torrent = $provider->getTorrent('hash1');

        $this->assertSame('hash1', $torrent->hash);
        $this->assertSame('test.torrent', $torrent->name);
    }

    public function testGetTorrentNotFound(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('
                <array>
                    <data>
                    </data>
                </array>
            ')),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('not found');

        $provider->getTorrent('nonexistent');
    }

    public function testPauseTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('')),
        ]);

        $result = $provider->pauseTorrent('hash1');

        $this->assertTrue($result);
    }

    public function testResumeTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('')),
        ]);

        $result = $provider->resumeTorrent('hash1');

        $this->assertTrue($result);
    }

    public function testRemoveTorrent(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('')),
        ]);

        $result = $provider->removeTorrent('hash1', true);

        $this->assertTrue($result);
    }

    public function testSetDownloadPath(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('')),
        ]);

        $result = $provider->setDownloadPath('hash1', '/new/path');

        $this->assertTrue($result);
    }

    public function testGetServerStatus(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcResponse('
                <string>0.9.8</string>
            ')),
        ]);

        $status = $provider->getServerStatus();

        $this->assertSame('0.9.8', $status->version);
    }

    public function testApiFaultThrowsException(): void
    {
        $provider = $this->createProvider([
            new Response(200, ['Content-Type' => 'text/xml'], $this->xmlRpcFault(-1, 'Method not found')),
        ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('XML-RPC fault: Method not found');

        $provider->getTorrents();
    }

    private function xmlRpcResponse(string $innerXml): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><methodResponse><params><param><value>'
            . $innerXml
            . '</value></param></params></methodResponse>';
    }

    private function xmlRpcFault(int $code, string $message): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><methodResponse><fault><value><struct><member>'
            . '<name>faultCode</name><value><int>' . $code . '</int></value></member><member>'
            . '<name>faultString</name><value><string>' . htmlspecialchars($message) . '</string></value></member>'
            . '</struct></value></fault></methodResponse>';
    }
}
