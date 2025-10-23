<?php

namespace Opayo\Tests\Exception;

use Opayo\Exception\OpayoAuthenticationException;
use Opayo\Exception\OpayoConfigException;
use Opayo\Exception\OpayoCryptographyException;
use Opayo\Exception\OpayoException;
use Opayo\Exception\OpayoNetworkException;
use Opayo\Exception\OpayoValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for all Opayo exception classes
 */
class OpayoExceptionTest extends TestCase
{
    public function testOpayoExceptionBasic(): void
    {
        $exception = new OpayoException('Test error');

        $this->assertSame('Test error', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertSame([], $exception->getContext());
    }

    public function testOpayoExceptionWithContext(): void
    {
        $context = ['field' => 'value', 'error_code' => 123];
        $exception = new OpayoException('Test error', 0, null, $context);

        $this->assertSame($context, $exception->getContext());
    }

    public function testOpayoExceptionWithCode(): void
    {
        $exception = new OpayoException('Test error', 42);

        $this->assertSame(42, $exception->getCode());
    }

    public function testOpayoExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new OpayoException('Test error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testOpayoConfigExceptionConstants(): void
    {
        $this->assertSame(1001, OpayoConfigException::MISSING_VENDOR);
        $this->assertSame(1002, OpayoConfigException::MISSING_PASSWORD);
        $this->assertSame(1003, OpayoConfigException::MISSING_ENDPOINT);
        $this->assertSame(1004, OpayoConfigException::INVALID_ENVIRONMENT);
    }

    public function testOpayoConfigExceptionInheritance(): void
    {
        $exception = new OpayoConfigException('Config error');

        $this->assertInstanceOf(OpayoException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testOpayoConfigExceptionWithCode(): void
    {
        $exception = new OpayoConfigException('Missing vendor', OpayoConfigException::MISSING_VENDOR);

        $this->assertSame(OpayoConfigException::MISSING_VENDOR, $exception->getCode());
    }

    public function testOpayoNetworkExceptionConstants(): void
    {
        $this->assertSame(2001, OpayoNetworkException::CONNECTION_FAILED);
        $this->assertSame(2002, OpayoNetworkException::TIMEOUT);
        $this->assertSame(2003, OpayoNetworkException::HTTP_ERROR);
        $this->assertSame(2004, OpayoNetworkException::INVALID_RESPONSE);
    }

    public function testOpayoNetworkExceptionInheritance(): void
    {
        $exception = new OpayoNetworkException('Network error');

        $this->assertInstanceOf(OpayoException::class, $exception);
    }

    public function testOpayoValidationExceptionConstants(): void
    {
        $this->assertSame(3001, OpayoValidationException::MISSING_REQUIRED_FIELD);
        $this->assertSame(3002, OpayoValidationException::INVALID_FIELD_FORMAT);
        $this->assertSame(3003, OpayoValidationException::FIELD_TOO_LONG);
        $this->assertSame(3004, OpayoValidationException::INVALID_AMOUNT);
        $this->assertSame(3005, OpayoValidationException::INVALID_CURRENCY);
    }

    public function testOpayoValidationExceptionInheritance(): void
    {
        $exception = new OpayoValidationException('Validation error');

        $this->assertInstanceOf(OpayoException::class, $exception);
    }

    public function testOpayoValidationExceptionWithContext(): void
    {
        $context = ['field' => 'Amount', 'value' => 'invalid'];
        $exception = new OpayoValidationException(
            'Invalid amount',
            OpayoValidationException::INVALID_AMOUNT,
            null,
            $context
        );

        $this->assertSame($context, $exception->getContext());
        $this->assertSame(OpayoValidationException::INVALID_AMOUNT, $exception->getCode());
    }

    public function testOpayoAuthenticationExceptionConstants(): void
    {
        $this->assertSame(4001, OpayoAuthenticationException::INVALID_VENDOR);
        $this->assertSame(4002, OpayoAuthenticationException::INVALID_SIGNATURE);
        $this->assertSame(4003, OpayoAuthenticationException::UNAUTHORIZED);
    }

    public function testOpayoAuthenticationExceptionInheritance(): void
    {
        $exception = new OpayoAuthenticationException('Auth error');

        $this->assertInstanceOf(OpayoException::class, $exception);
    }

    public function testOpayoCryptographyExceptionConstants(): void
    {
        $this->assertSame(5001, OpayoCryptographyException::ENCRYPTION_FAILED);
        $this->assertSame(5002, OpayoCryptographyException::DECRYPTION_FAILED);
        $this->assertSame(5003, OpayoCryptographyException::INVALID_KEY);
    }

    public function testOpayoCryptographyExceptionInheritance(): void
    {
        $exception = new OpayoCryptographyException('Crypto error');

        $this->assertInstanceOf(OpayoException::class, $exception);
    }

    public function testExceptionCodeUniqueness(): void
    {
        $codes = [
            OpayoConfigException::MISSING_VENDOR,
            OpayoConfigException::MISSING_PASSWORD,
            OpayoConfigException::MISSING_ENDPOINT,
            OpayoConfigException::INVALID_ENVIRONMENT,
            OpayoNetworkException::CONNECTION_FAILED,
            OpayoNetworkException::TIMEOUT,
            OpayoNetworkException::HTTP_ERROR,
            OpayoNetworkException::INVALID_RESPONSE,
            OpayoValidationException::MISSING_REQUIRED_FIELD,
            OpayoValidationException::INVALID_FIELD_FORMAT,
            OpayoValidationException::FIELD_TOO_LONG,
            OpayoValidationException::INVALID_AMOUNT,
            OpayoValidationException::INVALID_CURRENCY,
            OpayoAuthenticationException::INVALID_VENDOR,
            OpayoAuthenticationException::INVALID_SIGNATURE,
            OpayoAuthenticationException::UNAUTHORIZED,
            OpayoCryptographyException::ENCRYPTION_FAILED,
            OpayoCryptographyException::DECRYPTION_FAILED,
            OpayoCryptographyException::INVALID_KEY,
        ];

        $uniqueCodes = array_unique($codes);
        $this->assertCount(count($codes), $uniqueCodes, 'All exception codes should be unique');
    }

    public function testAllExceptionsCanBeCaught(): void
    {
        $exceptions = [
            new OpayoException('Base exception'),
            new OpayoConfigException('Config exception'),
            new OpayoNetworkException('Network exception'),
            new OpayoValidationException('Validation exception'),
            new OpayoAuthenticationException('Auth exception'),
            new OpayoCryptographyException('Crypto exception'),
        ];

        foreach ($exceptions as $exception) {
            try {
                throw $exception;
            } catch (OpayoException $e) {
                $this->assertInstanceOf(OpayoException::class, $e);
            }
        }
    }

    public function testExceptionMessagePreservation(): void
    {
        $message = 'Custom error message with details';
        $exception = new OpayoException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionChaining(): void
    {
        $original = new \RuntimeException('Original error');
        $wrapped = new OpayoNetworkException('Wrapped error', 0, $original);
        $final = new OpayoException('Final error', 0, $wrapped);

        $this->assertSame($wrapped, $final->getPrevious());
        $this->assertSame($original, $final->getPrevious()->getPrevious());
    }
}
