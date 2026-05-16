<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent;

use Fatkulnurk\Torrent\Contracts\TorrentClientInterface;
use Fatkulnurk\Torrent\Exceptions\UnsupportedDriverException;
use Fatkulnurk\Torrent\Providers\Aria2Provider;
use Fatkulnurk\Torrent\Providers\DelugeProvider;
use Fatkulnurk\Torrent\Providers\QbittorrentProvider;
use Fatkulnurk\Torrent\Providers\RqbitProvider;
use Fatkulnurk\Torrent\Providers\RTorrentProvider;
use Fatkulnurk\Torrent\Providers\TransmissionProvider;

class TorrentClientManager
{
    private static array $registry = [
        'qbittorrent' => QbittorrentProvider::class,
        'transmission' => TransmissionProvider::class,
        'rtorrent' => RTorrentProvider::class,
        'deluge' => DelugeProvider::class,
        'rqbit' => RqbitProvider::class,
        'aria2' => Aria2Provider::class,
    ];

    public static function register(string $name, string $className): void
    {
        if (!is_a($className, TorrentClientInterface::class, true)) {
            throw new UnsupportedDriverException(
                "Class {$className} must implement TorrentClientInterface"
            );
        }

        self::$registry[strtolower($name)] = $className;
    }

    public static function make(
        string $driver,
        string $baseUrl,
        array $config = []
    ): TorrentClientInterface {
        $driver = strtolower($driver);

        if (!isset(self::$registry[$driver])) {
            $available = implode(', ', self::availableDrivers());
            throw new UnsupportedDriverException(
                "Driver '{$driver}' is not supported. Available drivers: {$available}"
            );
        }

        $className = self::$registry[$driver];

        return new $className($baseUrl, $config);
    }

    public static function availableDrivers(): array
    {
        return array_keys(self::$registry);
    }
}
