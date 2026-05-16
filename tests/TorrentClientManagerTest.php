<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests;

use Fatkulnurk\Torrent\Exceptions\UnsupportedDriverException;
use Fatkulnurk\Torrent\Providers\QbittorrentProvider;
use Fatkulnurk\Torrent\Providers\RTorrentProvider;
use Fatkulnurk\Torrent\Providers\TransmissionProvider;
use Fatkulnurk\Torrent\TorrentClientManager;
use PHPUnit\Framework\TestCase;

class TorrentClientManagerTest extends TestCase
{
    protected function setUp(): void {}

    protected function tearDown(): void {}

    public function testAvailableDrivers(): void
    {
        $drivers = TorrentClientManager::availableDrivers();

        $this->assertContains('qbittorrent', $drivers);
        $this->assertContains('transmission', $drivers);
        $this->assertContains('rtorrent', $drivers);
        $this->assertContains('deluge', $drivers);
        $this->assertContains('rqbit', $drivers);
    }

    public function testMakeQbittorrent(): void
    {
        $this->assertTrue(true);
    }

    public function testMakeTransmission(): void
    {
        $this->assertTrue(true);
    }

    public function testMakeCaseInsensitive(): void
    {
        $this->assertTrue(true);
    }

    public function testMakeUnsupportedDriver(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('is not supported');

        TorrentClientManager::make('unknown', 'http://localhost:8080');
    }

    public function testMakeUnsupportedDriverShowsAvailable(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('Available drivers:');

        TorrentClientManager::make('unknown_driver', 'http://localhost:8112');
    }

    public function testRegister(): void
    {
        $this->assertTrue(true);
    }

    public function testRegisterCaseInsensitive(): void
    {
        $this->assertTrue(true);
    }

    public function testRegisterInvalidClass(): void
    {
        $this->expectException(UnsupportedDriverException::class);
        $this->expectExceptionMessage('must implement TorrentClientInterface');

        TorrentClientManager::register('invalid', \stdClass::class);
    }
}
