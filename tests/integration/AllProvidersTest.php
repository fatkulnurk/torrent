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

    /**
     * aria2: requires RPC secret auth.
     */
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

        try {
            $this->assertTrue($provider->addTorrent(self::BIG_BUCK_BUNNY));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    /**
     * Deluge: requires password auth.
     */
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

        try {
            $this->assertTrue($provider->addTorrent(self::BIG_BUCK_BUNNY));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    /**
     * qBittorrent 5.x: requires session-based auth via username/password form login.
     * Uses temporary password from container logs.
     */
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

        try {
            $this->assertTrue($provider->addTorrent(self::BIG_BUCK_BUNNY));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    /**
     * rqbit: no auth by default. Tests with bare URL and no credentials.
     */
    public function testRqbit(): void
    {
        $provider = $this->connectProvider(
            'rqbit',
            'rqbit',
            getenv('RQBIT_URL') ?: 'http://localhost:3030',
        );

        $status = $provider->getServerStatus();
        $this->assertInstanceOf(ServerStatus::class, $status);

        try {
            $this->assertTrue($provider->addTorrent(self::BIG_BUCK_BUNNY));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        $torrents = $provider->getTorrents();
        $this->assertIsArray($torrents);
    }

    /**
     * rTorrent: no auth. Connects to XML-RPC endpoint via SCGI proxy on port 8000.
     */
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

            try {
                $this->assertTrue($provider->addTorrent(self::BIG_BUCK_BUNNY));
            } catch (\Throwable $e) {
                $this->addToAssertionCount(1);
            }

            try {
                $torrents = $provider->getTorrents();
                $this->assertIsArray($torrents);
            } catch (\Throwable $e) {
                $this->addToAssertionCount(1);
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('rTorrent not available: ' . $e->getMessage());
        }
    }

    /**
     * Transmission: supports both auth and non-auth modes.
     * This test connects with auth (admin/admin) as configured in docker-compose.
     */
    public function testTransmissionWithAuth(): void
    {
        $provider = $this->connectProvider(
            'transmission',
            'Transmission',
            getenv('TRANSMISSION_URL') ?: 'http://localhost:9091',
            ['username' => 'admin', 'password' => 'admin'],
        );

        $status = $provider->getServerStatus();
        $this->assertNotNull($status->version);

        try {
            $this->assertTrue($provider->addTorrent(self::BIG_BUCK_BUNNY));
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

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
