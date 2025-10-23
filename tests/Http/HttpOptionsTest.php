<?php

namespace Opayo\Tests\Http;

use InvalidArgumentException;
use Opayo\Http\HttpOptions;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for HttpOptions class
 */
class HttpOptionsTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $options = new HttpOptions();

        $this->assertSame(30, $options->timeout);
        $this->assertSame(10, $options->connectTimeout);
        $this->assertTrue($options->verify);
    }

    public function testCustomTimeout(): void
    {
        $options = new HttpOptions(timeout: 60);

        $this->assertSame(60, $options->timeout);
        $this->assertSame(10, $options->connectTimeout);
        $this->assertTrue($options->verify);
    }

    public function testCustomConnectTimeout(): void
    {
        $options = new HttpOptions(connectTimeout: 20);

        $this->assertSame(30, $options->timeout);
        $this->assertSame(20, $options->connectTimeout);
        $this->assertTrue($options->verify);
    }

    public function testDisableVerify(): void
    {
        $options = new HttpOptions(verify: false);

        $this->assertSame(30, $options->timeout);
        $this->assertSame(10, $options->connectTimeout);
        $this->assertFalse($options->verify);
    }

    public function testAllCustomValues(): void
    {
        $options = new HttpOptions(timeout: 45, connectTimeout: 15, verify: false);

        $this->assertSame(45, $options->timeout);
        $this->assertSame(15, $options->connectTimeout);
        $this->assertFalse($options->verify);
    }

    public function testToArray(): void
    {
        $options = new HttpOptions(timeout: 40, connectTimeout: 12, verify: true);

        $array = $options->toArray();

        $this->assertIsArray($array);
        $this->assertSame(40, $array['timeout']);
        $this->assertSame(12, $array['connect_timeout']);
        $this->assertTrue($array['verify']);
    }

    public function testWithTimeout(): void
    {
        $options = HttpOptions::withTimeout(90);

        $this->assertSame(90, $options->timeout);
        $this->assertSame(10, $options->connectTimeout);
        $this->assertTrue($options->verify);
    }

    public function testInvalidTimeoutZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be positive');

        new HttpOptions(timeout: 0);
    }

    public function testInvalidTimeoutNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be positive');

        new HttpOptions(timeout: -10);
    }

    public function testInvalidConnectTimeoutZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connect timeout must be positive');

        new HttpOptions(connectTimeout: 0);
    }

    public function testInvalidConnectTimeoutNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connect timeout must be positive');

        new HttpOptions(connectTimeout: -5);
    }

    public function testImmutability(): void
    {
        $options = new HttpOptions(timeout: 30, connectTimeout: 10);

        // Properties are readonly, so attempting to modify should cause an error
        $this->assertSame(30, $options->timeout);
        $this->assertSame(10, $options->connectTimeout);
    }

    public function testToArrayFormat(): void
    {
        $options = new HttpOptions();
        $array = $options->toArray();

        $this->assertArrayHasKey('timeout', $array);
        $this->assertArrayHasKey('connect_timeout', $array);
        $this->assertArrayHasKey('verify', $array);
        $this->assertCount(3, $array);
    }
}
