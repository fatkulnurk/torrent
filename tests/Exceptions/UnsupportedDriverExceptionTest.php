<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Exceptions;

use Fatkulnurk\Torrent\Exceptions\UnsupportedDriverException;
use PHPUnit\Framework\TestCase;

class UnsupportedDriverExceptionTest extends TestCase
{
    public function testConstruct(): void
    {
        $exception = new UnsupportedDriverException('Driver not found');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Driver not found', $exception->getMessage());
    }
}
