<?php
declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Providers;

use Fatkulnurk\Torrent\Exceptions\RequestException;
use Fatkulnurk\Torrent\Providers\AbstractProvider;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class MockProvider extends AbstractProvider
{
    public function __construct(
        string $baseUrl,
        array $config = []
    ) {
        parent::__construct($baseUrl, $config);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function callRequest(string $method, string $endpoint, array $options = []): mixed
    {
        return $this->request($method, $endpoint, $options);
    }

    public function testAddTorrent(string $source, array $options = []): bool
    {
        return $this->addTorrent($source, $options);
    }

    public function testGetTorrents(array $filters = []): array
    {
        return $this->getTorrents($filters);
    }

    public function testGetTorrent(string $hash): \Fatkulnurk\Torrent\Data\Torrent
    {
        return $this->getTorrent($hash);
    }

    public function testPauseTorrent(string $hash): bool
    {
        return $this->pauseTorrent($hash);
    }

    public function testResumeTorrent(string $hash): bool
    {
        return $this->resumeTorrent($hash);
    }

    public function testRemoveTorrent(string $hash, bool $deleteFiles = false): bool
    {
        return $this->removeTorrent($hash, $deleteFiles);
    }

    public function testSetDownloadPath(string $hash, string $path): bool
    {
        return $this->setDownloadPath($hash, $path);
    }

    public function testGetServerStatus(): \Fatkulnurk\Torrent\Data\ServerStatus
    {
        return $this->getServerStatus();
    }
}

class AbstractProviderTest extends TestCase
{
    public function testConstructor(): void
    {
        $provider = new MockProvider('http://localhost:8080', [
            'timeout' => 30.0,
            'verify_ssl' => false,
        ]);

        $this->assertInstanceOf(Client::class, $provider->getClient());
        $this->assertSame(30.0, $provider->getConfig()['timeout']);
        $this->assertFalse($provider->getConfig()['verify_ssl']);
    }

    public function testConstructorWithDefaults(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->assertSame(10.0, $provider->getConfig()['timeout']);
        $this->assertTrue($provider->getConfig()['verify_ssl']);
    }

    public function testAddTorrentThrowsException(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('addTorrent not implemented in provider');

        $provider->testAddTorrent('test.torrent');
    }

    public function testGetTorrentsThrowsException(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('getTorrents not implemented in provider');

        $provider->testGetTorrents();
    }

    public function testGetTorrentThrowsException(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('getTorrent not implemented in provider');

        $provider->testGetTorrent('hash123');
    }

    public function testPauseTorrentThrowsException(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('pauseTorrent not implemented in provider');

        $provider->testPauseTorrent('hash123');
    }

    public function testResumeTorrentThrowsException(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('resumeTorrent not implemented in provider');

        $provider->testResumeTorrent('hash123');
    }

    public function testRemoveTorrentThrowsException(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('removeTorrent not implemented in provider');

        $provider->testRemoveTorrent('hash123');
    }

    public function testSetDownloadPathThrowsException(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('setDownloadPath not implemented in provider');

        $provider->testSetDownloadPath('hash123', '/downloads');
    }

    public function testGetServerStatusThrowsException(): void
    {
        $provider = new MockProvider('http://localhost:8080');

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('getServerStatus not implemented in provider');

        $provider->testGetServerStatus();
    }
}