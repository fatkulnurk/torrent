<?php
declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Data;

use Fatkulnurk\Torrent\Data\ServerStatus;
use PHPUnit\Framework\TestCase;

class ServerStatusTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'version' => '4.3.5',
            'apiVersion' => 16,
        ];

        $status = ServerStatus::fromArray($data);

        $this->assertSame('4.3.5', $status->version);
        $this->assertSame(16, $status->apiVersion);
    }

    public function testFromArrayWithCoreVersion(): void
    {
        $data = [
            'coreVersion' => '4.3.5',
            'apiVersion' => 16,
        ];

        $status = ServerStatus::fromArray($data);

        $this->assertSame('4.3.5', $status->version);
    }

    public function testFromArrayWithDefaults(): void
    {
        $status = ServerStatus::fromArray([]);

        $this->assertNull($status->version);
        $this->assertNull($status->apiVersion);
    }
}