<?php

namespace Opayo\Tests\Http;

use Opayo\Config;
use Opayo\Crypto\CryptoInterface;
use Opayo\Http\TransactionRequestBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for TransactionRequestBuilder class
 */
class TransactionRequestBuilderTest extends TestCase
{
    private CryptoInterface $crypto;
    private Config $config;
    private TransactionRequestBuilder $builder;

    protected function setUp(): void
    {
        $this->crypto = $this->createMock(CryptoInterface::class);
        $this->config = new Config(
            vendor: 'testvendor',
            encryptionPassword: '1234567890123456',
            endpoint: 'https://test.opayo.example.com'
        );
        $this->builder = new TransactionRequestBuilder($this->crypto, $this->config);
    }

    public function testBuildWithAllRequiredFields(): void
    {
        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->with(
                $this->stringContains('VendorTxCode=TX123'),
                '1234567890123456'
            )
            ->willReturn('@ENCRYPTED');

        $fields = [
            'VendorTxCode' => 'TX123',
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test transaction',
        ];

        $result = $this->builder->build($fields);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('VPSProtocol', $result);
        $this->assertArrayHasKey('TxType', $result);
        $this->assertArrayHasKey('Vendor', $result);
        $this->assertArrayHasKey('Crypt', $result);
        $this->assertSame('3.00', $result['VPSProtocol']);
        $this->assertSame('PAYMENT', $result['TxType']);
        $this->assertSame('testvendor', $result['Vendor']);
        $this->assertSame('@ENCRYPTED', $result['Crypt']);
    }

    public function testBuildGeneratesVendorTxCodeIfNotProvided(): void
    {
        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->with(
                $this->stringContains('VendorTxCode=TX-'),
                '1234567890123456'
            )
            ->willReturn('@ENCRYPTED');

        $fields = [
            'Amount' => '10.00',
            'Currency' => 'GBP',
        ];

        $result = $this->builder->build($fields);

        $this->assertArrayHasKey('Crypt', $result);
    }

    public function testBuildGeneratesVendorTxCodeIfEmpty(): void
    {
        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->with(
                $this->stringContains('VendorTxCode=TX-'),
                '1234567890123456'
            )
            ->willReturn('@ENCRYPTED');

        $fields = [
            'VendorTxCode' => '',
            'Amount' => '10.00',
        ];

        $result = $this->builder->build($fields);

        $this->assertArrayHasKey('Crypt', $result);
    }

    public function testBuildUsesProvidedVendorTxCode(): void
    {
        $capturedQuery = '';
        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->willReturnCallback(function ($query, $key) use (&$capturedQuery) {
                $capturedQuery = $query;
                return '@ENCRYPTED';
            });

        $fields = [
            'VendorTxCode' => 'CUSTOM-TX-001',
            'Amount' => '25.50',
        ];

        $this->builder->build($fields);

        $this->assertStringContainsString('VendorTxCode=CUSTOM-TX-001', $capturedQuery);
    }

    public function testBuildEncryptsAllFields(): void
    {
        $capturedQuery = '';
        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->willReturnCallback(function ($query, $key) use (&$capturedQuery) {
                $capturedQuery = $query;
                return '@ENCRYPTED';
            });

        $fields = [
            'VendorTxCode' => 'TX123',
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test transaction',
            'BillingFirstnames' => 'John',
            'BillingSurname' => 'Doe',
        ];

        $this->builder->build($fields);

        parse_str($capturedQuery, $parsed);

        $this->assertSame('TX123', $parsed['VendorTxCode']);
        $this->assertSame('10.00', $parsed['Amount']);
        $this->assertSame('GBP', $parsed['Currency']);
        $this->assertSame('Test transaction', $parsed['Description']);
        $this->assertSame('John', $parsed['BillingFirstnames']);
        $this->assertSame('Doe', $parsed['BillingSurname']);
    }

    public function testGetVendorTxCodeReturnsProvidedCode(): void
    {
        $fields = ['VendorTxCode' => 'MYTX001'];

        $code = $this->builder->getVendorTxCode($fields);

        $this->assertSame('MYTX001', $code);
    }

    public function testGetVendorTxCodeGeneratesCodeIfNotProvided(): void
    {
        $fields = ['Amount' => '10.00'];

        $code = $this->builder->getVendorTxCode($fields);

        $this->assertStringStartsWith('TX-', $code);
    }

    public function testBuildUsesConfigVendor(): void
    {
        $this->crypto->method('encrypt')->willReturn('@ENCRYPTED');

        $result = $this->builder->build(['VendorTxCode' => 'TX123']);

        $this->assertSame('testvendor', $result['Vendor']);
    }

    public function testBuildUsesConfigEncryptionPassword(): void
    {
        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->with(
                $this->anything(),
                '1234567890123456'
            )
            ->willReturn('@ENCRYPTED');

        $this->builder->build(['VendorTxCode' => 'TX123']);
    }

    public function testBuildReturnsCorrectStructure(): void
    {
        $this->crypto->method('encrypt')->willReturn('@ENCRYPTED');

        $result = $this->builder->build(['VendorTxCode' => 'TX123']);

        $this->assertCount(4, $result);
        $this->assertArrayHasKey('VPSProtocol', $result);
        $this->assertArrayHasKey('TxType', $result);
        $this->assertArrayHasKey('Vendor', $result);
        $this->assertArrayHasKey('Crypt', $result);
    }

    public function testVendorTxCodePrefixFormat(): void
    {
        $code = $this->builder->getVendorTxCode([]);

        // uniqid with more_entropy includes a dot: TX-[hex].[hex]
        $this->assertMatchesRegularExpression('/^TX-[a-f0-9]+\.[a-f0-9]+$/', $code);
    }

    public function testBuildHandlesSpecialCharactersInFields(): void
    {
        $capturedQuery = '';
        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->willReturnCallback(function ($query, $key) use (&$capturedQuery) {
                $capturedQuery = $query;
                return '@ENCRYPTED';
            });

        $fields = [
            'VendorTxCode' => 'TX123',
            'Description' => 'Test & Special <chars>',
        ];

        $this->builder->build($fields);

        $this->assertNotEmpty($capturedQuery);
    }

    public function testMultipleBuildsGenerateUniqueVendorTxCodes(): void
    {
        $this->crypto->method('encrypt')->willReturn('@ENCRYPTED');

        $code1 = $this->builder->getVendorTxCode([]);
        $code2 = $this->builder->getVendorTxCode([]);

        $this->assertNotSame($code1, $code2);
    }

    public function testBuildWithNumericValues(): void
    {
        $capturedQuery = '';
        $this->crypto->expects($this->once())
            ->method('encrypt')
            ->willReturnCallback(function ($query, $key) use (&$capturedQuery) {
                $capturedQuery = $query;
                return '@ENCRYPTED';
            });

        $fields = [
            'VendorTxCode' => 'TX123',
            'Amount' => 99.99,
            'Quantity' => 5,
        ];

        $this->builder->build($fields);

        parse_str($capturedQuery, $parsed);

        $this->assertSame('99.99', $parsed['Amount']);
        $this->assertSame('5', $parsed['Quantity']);
    }
}
