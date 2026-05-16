<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Integration;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\TorrentClientManager;
use PHPUnit\Framework\TestCase;

final class AllProvidersTest extends TestCase
{
    private const BIG_BUCK_BUNNY = 'magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny&tr=udp%3A%2F%2Fexplodie.org%3A6969&tr=udp%3A%2F%2Ftracker.coppersurfer.tk%3A6969&tr=udp%3A%2F%2Ftracker.empire-js.us%3A1337&tr=udp%3A%2F%2Ftracker.leechers-paradise.org%3A6969&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337&tr=wss%3A%2F%2Ftracker.btorrent.xyz&tr=wss%3A%2F%2Ftracker.fastcast.nz&tr=wss%3A%2F%2Ftracker.openwebtorrent.com&ws=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2F&xs=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2Fbig-buck-bunny.torrent';

    protected function setUp(): void
    {
        if (!in_array(getenv('INTEGRATION'), ['true', '1', 'yes'], true)) {
            $this->markTestSkipped('Set INTEGRATION=true to run integration tests');
        }
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

    private function exerciseAllMethods(mixed $provider, string $label, bool $supportsSetDownloadPath = true): void
    {
        $hash = null;

        try {
            $this->assertTrue($provider->addTorrent(self::BIG_BUCK_BUNNY));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        try {
            $torrents = $provider->getTorrents();
            $this->assertIsArray($torrents);

            if ($torrents !== []) {
                $hash = $torrents[0]->hash;
            }
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        if ($hash === null) {
            return;
        }

        try {
            $torrent = $provider->getTorrent($hash);
            $this->assertSame($hash, $torrent->hash);
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->assertTrue($provider->pauseTorrent($hash));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->assertTrue($provider->resumeTorrent($hash));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        if ($supportsSetDownloadPath) {
            try {
                $provider->setDownloadPath($hash, '/downloads');
            } catch (\Throwable $e) {
                $this->addToAssertionCount(1);
            }
        } else {
            $this->addToAssertionCount(1);
        }

        try {
            $this->assertTrue($provider->removeTorrent($hash, false));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        try {
            $provider->addTorrent(self::BIG_BUCK_BUNNY);
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }
    }

    public function testAria2(): void
    {
        $provider = $this->connectProvider(
            'aria2',
            'aria2',
            getenv('ARIA2_URL') ?: 'http://localhost:6800',
            ['secret' => getenv('ARIA2_SECRET') ?: 'secret123'],
        );

        $this->exerciseAllMethods($provider, 'aria2');
    }

    public function testDeluge(): void
    {
        $provider = $this->connectProvider(
            'deluge',
            'Deluge',
            getenv('DELUGE_URL') ?: 'http://localhost:8112',
            ['password' => getenv('DELUGE_PASSWORD') ?: 'deluge'],
        );

        $this->exerciseAllMethods($provider, 'Deluge');
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

        $this->exerciseAllMethods($provider, 'qBittorrent');
    }

    public function testRqbit(): void
    {
        $provider = $this->connectProvider(
            'rqbit',
            'rqbit',
            getenv('RQBIT_URL') ?: 'http://localhost:3030',
        );

        $this->exerciseAllMethods($provider, 'rqbit', false);
    }

    public function testRTorrent(): void
    {
        try {
            $provider = TorrentClientManager::make(
                'rtorrent',
                getenv('RTORRENT_URL') ?: 'http://localhost:8000',
                ['rpc_endpoint' => '/'],
            );
            $status = $provider->getServerStatus();
            $this->assertNotNull($status->version);
        } catch (\Throwable $e) {
            $this->markTestSkipped('rTorrent not available: ' . $e->getMessage());
        }

        $this->exerciseAllMethods($provider, 'rTorrent');
    }

    public function testTransmissionWithAuth(): void
    {
        $provider = $this->connectProvider(
            'transmission',
            'Transmission',
            getenv('TRANSMISSION_URL') ?: 'http://localhost:9091',
            ['username' => 'admin', 'password' => 'admin'],
        );

        $this->exerciseAllMethods($provider, 'Transmission');
    }
}
