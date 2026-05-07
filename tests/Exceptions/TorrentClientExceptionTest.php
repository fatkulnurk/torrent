<?php
declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Exceptions;

use Fatkulnurk\Torrent\Exceptions\TorrentClientException;
use PHPUnit\Framework\TestCase;

class TorrentClientExceptionTest extends TestCase
{
    public function testConstructWithMessage(): void
    {
        $exception = new TorrentClientException('Test message');

        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testConstructWithCode(): void
    {
        $exception = new TorrentClientException('', 500);

        $this->assertSame(500, $exception->getCode());
    }

    public function testConstructWithPrevious(): void
    {
        $previous = new \RuntimeException('Previous');
        $exception = new TorrentClientException('', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}