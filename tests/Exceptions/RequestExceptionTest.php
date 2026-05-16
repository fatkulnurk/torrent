<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Exceptions;

use Fatkulnurk\Torrent\Exceptions\RequestException;
use PHPUnit\Framework\TestCase;

class RequestExceptionTest extends TestCase
{
    public function testConstruct(): void
    {
        $exception = new RequestException('Request failed');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Request failed', $exception->getMessage());
    }
}
