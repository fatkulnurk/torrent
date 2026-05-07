<?php
declare(strict_types=1);

namespace Fatkulnurk\Torrent\Tests\Exceptions;

use Fatkulnurk\Torrent\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;

class AuthenticationExceptionTest extends TestCase
{
    public function testConstruct(): void
    {
        $exception = new AuthenticationException('Auth failed');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Auth failed', $exception->getMessage());
    }
}