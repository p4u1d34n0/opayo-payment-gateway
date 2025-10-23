<?php

namespace Opayo\Tests\Crypto;

use Opayo\Crypto\OpayoCrypto;
use Opayo\Exception\OpayoCryptographyException;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for OpayoCrypto class (instance-based)
 */
class OpayoCryptoTest extends TestCase
{
    private OpayoCrypto $crypto;
    private string $validKey;

    protected function setUp(): void
    {
        $this->crypto = new OpayoCrypto();
        // AES-128 requires exactly 16 bytes
        $this->validKey = '1234567890123456';
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $originalData = 'This is a test message';

        $encrypted = $this->crypto->encrypt($originalData, $this->validKey);
        $decrypted = $this->crypto->decrypt($encrypted, $this->validKey);

        $this->assertSame($originalData, $decrypted);
    }

    public function testEncryptDecryptRoundTripWithSpecialCharacters(): void
    {
        $originalData = 'Test with special chars: !@#$%^&*(){}[]|;:,.<>?/~`';

        $encrypted = $this->crypto->encrypt($originalData, $this->validKey);
        $decrypted = $this->crypto->decrypt($encrypted, $this->validKey);

        $this->assertSame($originalData, $decrypted);
    }

    public function testEncryptDecryptRoundTripWithUnicode(): void
    {
        $originalData = 'Unicode test: €£¥ 你好 مرحبا';

        $encrypted = $this->crypto->encrypt($originalData, $this->validKey);
        $decrypted = $this->crypto->decrypt($encrypted, $this->validKey);

        $this->assertSame($originalData, $decrypted);
    }

    public function testEncryptOutputHasAtPrefix(): void
    {
        $data = 'Test message';

        $encrypted = $this->crypto->encrypt($data, $this->validKey);

        $this->assertStringStartsWith('@', $encrypted);
    }

    public function testEncryptOutputIsUppercaseHex(): void
    {
        $data = 'Test message';

        $encrypted = $this->crypto->encrypt($data, $this->validKey);
        $hexPart = substr($encrypted, 1); // Remove @ prefix

        $this->assertMatchesRegularExpression('/^[A-F0-9]+$/', $hexPart);
    }

    public function testEncryptWithKeyTooShort(): void
    {
        $this->expectException(OpayoCryptographyException::class);
        $this->expectExceptionCode(OpayoCryptographyException::INVALID_KEY);
        $this->expectExceptionMessage('Invalid key length: expected 16 bytes, got 10 bytes');

        $this->crypto->encrypt('Test data', '1234567890');
    }

    public function testEncryptWithKeyTooLong(): void
    {
        $this->expectException(OpayoCryptographyException::class);
        $this->expectExceptionCode(OpayoCryptographyException::INVALID_KEY);
        $this->expectExceptionMessage('Invalid key length: expected 16 bytes, got 20 bytes');

        $this->crypto->encrypt('Test data', '12345678901234567890');
    }

    public function testDecryptWithKeyTooShort(): void
    {
        $this->expectException(OpayoCryptographyException::class);
        $this->expectExceptionCode(OpayoCryptographyException::INVALID_KEY);

        $this->crypto->decrypt('@ABCD1234', '12345');
    }

    public function testDecryptWithInvalidHexData(): void
    {
        $this->expectException(OpayoCryptographyException::class);
        $this->expectExceptionCode(OpayoCryptographyException::DECRYPTION_FAILED);
        $this->expectExceptionMessage('Invalid encrypted data format: not valid hexadecimal');

        $this->crypto->decrypt('@GGGGGG', $this->validKey);
    }

    public function testDecryptWithInvalidDataFormat(): void
    {
        $this->expectException(OpayoCryptographyException::class);
        $this->expectExceptionCode(OpayoCryptographyException::DECRYPTION_FAILED);

        $this->crypto->decrypt('@ZZZZZZ', $this->validKey);
    }

    public function testDecryptWithMalformedData(): void
    {
        $this->expectException(OpayoCryptographyException::class);
        $this->expectExceptionCode(OpayoCryptographyException::DECRYPTION_FAILED);

        $this->crypto->decrypt('@1234', $this->validKey);
    }

    public function testEncryptProducesDifferentOutputForDifferentData(): void
    {
        $data1 = 'Message one';
        $data2 = 'Message two';

        $encrypted1 = $this->crypto->encrypt($data1, $this->validKey);
        $encrypted2 = $this->crypto->encrypt($data2, $this->validKey);

        $this->assertNotSame($encrypted1, $encrypted2);
    }

    public function testEncryptWithEmptyString(): void
    {
        $encrypted = $this->crypto->encrypt('', $this->validKey);
        $decrypted = $this->crypto->decrypt($encrypted, $this->validKey);

        $this->assertSame('', $decrypted);
    }

    public function testDecryptWithWrongKey(): void
    {
        $data = 'Test message';
        $key1 = '1234567890123456';
        $key2 = '6543210987654321';

        $encrypted = $this->crypto->encrypt($data, $key1);

        $this->expectException(OpayoCryptographyException::class);
        $this->expectExceptionCode(OpayoCryptographyException::DECRYPTION_FAILED);

        $this->crypto->decrypt($encrypted, $key2);
    }

    public function testPaddingIsAppliedCorrectly(): void
    {
        // Test with data that's exactly one block (16 bytes)
        $data = '1234567890123456'; // Exactly 16 bytes

        $encrypted = $this->crypto->encrypt($data, $this->validKey);
        $decrypted = $this->crypto->decrypt($encrypted, $this->validKey);

        $this->assertSame($data, $decrypted);
    }

    public function testEncryptDecryptLongMessage(): void
    {
        $data = str_repeat('This is a long message. ', 100);

        $encrypted = $this->crypto->encrypt($data, $this->validKey);
        $decrypted = $this->crypto->decrypt($encrypted, $this->validKey);

        $this->assertSame($data, $decrypted);
    }

    public function testMultipleInstancesProduceSameOutput(): void
    {
        $crypto1 = new OpayoCrypto();
        $crypto2 = new OpayoCrypto();
        $data = 'Test message';

        $encrypted1 = $crypto1->encrypt($data, $this->validKey);
        $encrypted2 = $crypto2->encrypt($data, $this->validKey);

        // Both instances should produce identical output
        $this->assertSame($encrypted1, $encrypted2);
    }

    public function testImplementsCryptoInterface(): void
    {
        $this->assertInstanceOf(\Opayo\Crypto\CryptoInterface::class, $this->crypto);
    }
}
