<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Contracts;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;

interface TorrentClientInterface
{
    public function addTorrent(string $source, array $options = []): bool;

    /**
     * @return Torrent[]
     */
    public function getTorrents(array $filters = []): array;

    public function getTorrent(string $hash): Torrent;

    public function pauseTorrent(string $hash): bool;

    public function resumeTorrent(string $hash): bool;

    public function removeTorrent(string $hash, bool $deleteFiles = false): bool;

    public function setDownloadPath(string $hash, string $path): bool;

    public function getServerStatus(): ServerStatus;
}
