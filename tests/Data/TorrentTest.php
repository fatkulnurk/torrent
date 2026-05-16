<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Data;

use Fatkulnurk\Torrent\Data\Torrent;
use PHPUnit\Framework\TestCase;

class TorrentTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'hash' => 'abc123def',
            'name' => 'test.torrent',
            'status' => 4,
            'totalSize' => 1024000,
            'leftUntilDone' => 0,
            'downloadDir' => '/downloads',
            'percentDone' => 1.0,
        ];

        $torrent = Torrent::fromArray($data);

        $this->assertSame('abc123def', $torrent->hash);
        $this->assertSame('test.torrent', $torrent->name);
        $this->assertSame(4, $torrent->status);
        $this->assertSame(1024000, $torrent->totalSize);
        $this->assertSame(0, $torrent->leftUntilDone);
        $this->assertSame('/downloads', $torrent->downloadDir);
        $this->assertSame(1.0, $torrent->percentDone);
    }

    public function testFromArrayWithHashString(): void
    {
        $data = [
            'hashString' => 'abc123def',
            'name' => 'test.torrent',
            'status' => 4,
        ];

        $torrent = Torrent::fromArray($data);

        $this->assertSame('abc123def', $torrent->hash);
    }

    public function testFromArrayWithDefaults(): void
    {
        $torrent = Torrent::fromArray([]);

        $this->assertSame('', $torrent->hash);
        $this->assertSame('', $torrent->name);
        $this->assertSame(0, $torrent->status);
        $this->assertSame(0, $torrent->totalSize);
        $this->assertSame(0, $torrent->leftUntilDone);
        $this->assertSame('', $torrent->downloadDir);
        $this->assertSame(0.0, $torrent->percentDone);
    }

    public function testCollection(): void
    {
        $data = [
            ['hash' => 'hash1', 'name' => 'torrent1.torrent'],
            ['hash' => 'hash2', 'name' => 'torrent2.torrent'],
        ];

        $collection = Torrent::collection($data);

        $this->assertCount(2, $collection);
        $this->assertInstanceOf(Torrent::class, $collection[0]);
        $this->assertSame('hash1', $collection[0]->hash);
        $this->assertSame('hash2', $collection[1]->hash);
    }

    public function testCollectionEmpty(): void
    {
        $collection = Torrent::collection([]);

        $this->assertCount(0, $collection);
    }
}
