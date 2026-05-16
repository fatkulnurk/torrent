<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Integration;

use Fatkulnurk\Torrent\TorrentClientManager;
use PHPUnit\Framework\TestCase;

final class TestAllProviders extends TestCase
{
    private const TEST_MAGNET = 'magnet:?xt=urn:btih:0000000000000000000000000000000000000000&dn=test';

    protected function setUp(): void
    {
        if (!in_array(getenv('INTEGRATION'), ['true', '1', 'yes'], true)) {
            $this->markTestSkipped('Set INTEGRATION=true to run integration tests');
        }
    }

    public function testQbittorrent(): void
    {
        $password = getenv('QBITTORRENT_PASSWORD');

        if ($password === false || $password === '') {
            $this->markTestSkipped('Set QBITTORRENT_PASSWORD env var');
        }

        $provider = $this->connectProvider(
            'qbittorrent',
            'qBittorrent',
            getenv('QBITTORRENT_URL') ?: 'http://localhost:8080',
            ['username' => 'admin', 'password' => $password],
        );

        $status = $provider->getServerStatus();
        $this->assertNotNull($status->version);

        $this->assertTrue($provider->addTorrent(self::TEST_MAGNET));

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    public function testTransmission(): void
    {
        $provider = $this->connectProvider(
            'transmission',
            'Transmission',
            getenv('TRANSMISSION_URL') ?: 'http://localhost:9091',
            ['username' => 'admin', 'password' => 'admin'],
        );

        $status = $provider->getServerStatus();
        $this->assertNotNull($status->version);

        $this->assertTrue($provider->addTorrent(self::TEST_MAGNET));

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    public function testRTorrent(): void
    {
        $provider = $this->connectProvider(
            'rtorrent',
            'rTorrent',
            getenv('RTORRENT_URL') ?: 'http://localhost:8081',
        );

        $status = $provider->getServerStatus();
        $this->assertNotNull($status->version);

        $this->assertTrue($provider->addTorrent(self::TEST_MAGNET));

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    public function testDeluge(): void
    {
        $provider = $this->connectProvider(
            'deluge',
            'Deluge',
            getenv('DELUGE_URL') ?: 'http://localhost:8112',
            ['password' => getenv('DELUGE_PASSWORD') ?: 'deluge'],
        );

        $status = $provider->getServerStatus();
        $this->assertNotNull($status->version);

        $this->assertTrue($provider->addTorrent(self::TEST_MAGNET));

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    public function testRqbit(): void
    {
        $provider = $this->connectProvider(
            'rqbit',
            'rqbit',
            getenv('RQBIT_URL') ?: 'http://localhost:3030',
        );

        $status = $provider->getServerStatus();
        $this->assertNotNull($status->version);

        $this->assertTrue($provider->addTorrent(self::TEST_MAGNET));

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    public function testAria2(): void
    {
        $provider = $this->connectProvider(
            'aria2',
            'aria2',
            getenv('ARIA2_URL') ?: 'http://localhost:6800',
            ['secret' => getenv('ARIA2_SECRET') ?: 'secret123'],
        );

        $status = $provider->getServerStatus();
        $this->assertNotNull($status->version);

        $this->assertTrue($provider->addTorrent(self::TEST_MAGNET));

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    private function connectProvider(
        string $driver,
        string $label,
        string $baseUrl,
        array $config = [],
    ): mixed {
        try {
            $provider = TorrentClientManager::make($driver, $baseUrl, $config);
            $provider->getServerStatus();

            return $provider;
        } catch (\Throwable $e) {
            $this->markTestSkipped("{$label} not available: " . $e->getMessage());
        }
    }
}
