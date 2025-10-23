<?php

namespace Opayo\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Opayo\Client;
use Opayo\Config;
use Opayo\Crypto\OpayoCrypto;
use Opayo\Http\ResponseParser;
use Opayo\Http\TransactionRequestBuilder;
use Opayo\Logger\OpayoLogger;
use Opayo\NotificationHandler;
use Opayo\NotificationResponse;
use Opayo\Validator\TransactionValidator;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Opayo payment gateway
 * Tests the interaction between multiple components
 */
class OpayoIntegrationTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/opayo_integration_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    private function createClient(Config $config, $logger, $httpClient): Client
    {
        $crypto = new OpayoCrypto();
        $requestBuilder = new TransactionRequestBuilder($crypto, $config);
        $responseParser = new ResponseParser();
        return new Client($config, $logger, $httpClient, $requestBuilder, $responseParser);
    }

    public function testCompleteTransactionFlow(): void
    {
        // Setup
        $config = Config::sandbox('TestVendor', '1234567890123456');
        $logger = new OpayoLogger($this->logFile);

        $responseBody = "Status=OK&" .
                       "StatusDetail=Transaction successful&" .
                       "VPSTxId={12345678-1234-1234-1234-123456789012}&" .
                       "SecurityKey=TESTSECURITYKEY&" .
                       "NextURL=https://test.sagepay.com/next";

        $mock = new MockHandler([
            new Response(200, [], $responseBody),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = $this->createClient($config, $logger, $httpClient);

        // Execute
        $transaction = [
            'Amount' => '99.99',
            'Currency' => 'GBP',
            'Description' => 'Integration test transaction',
            'CustomerEMail' => 'test@example.com',
        ];

        $response = $client->registerTransaction($transaction);

        // Assert
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('OK', $response->getStatus());
        $this->assertSame('{12345678-1234-1234-1234-123456789012}', $response->getVPSTxId());
        $this->assertFileExists($this->logFile);
    }

    public function testEncryptionDecryptionIntegration(): void
    {
        $crypto = new OpayoCrypto();
        $key = '1234567890123456';
        $originalData = 'Amount=10.00&Currency=GBP&Description=Test';

        $encrypted = $crypto->encrypt($originalData, $key);
        $decrypted = $crypto->decrypt($encrypted, $key);

        $this->assertSame($originalData, $decrypted);
        $this->assertStringStartsWith('@', $encrypted);
    }

    public function testConfigAndClientIntegration(): void
    {
        $config = Config::fromArray([
            'vendor' => 'IntegrationVendor',
            'encryption_password' => '1234567890123456',
            'endpoint' => Config::ENDPOINT_TEST,
        ]);

        $logger = new OpayoLogger($this->logFile);

        $mock = new MockHandler([
            new Response(200, [], "Status=OK&StatusDetail=OK&VPSTxId=123&SecurityKey=KEY&"),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = $this->createClient($config, $logger, $httpClient);

        $response = $client->registerTransaction([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ]);

        $this->assertSame('OK', $response->getStatus());
    }

    public function testValidatorIntegration(): void
    {
        $validator = new TransactionValidator();
        $config = Config::sandbox('TestVendor', '1234567890123456');
        $logger = new OpayoLogger($this->logFile);

        $mock = new MockHandler([
            new Response(200, [], "Status=OK&StatusDetail=OK&VPSTxId=123&SecurityKey=KEY&"),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $crypto = new OpayoCrypto();
        $requestBuilder = new TransactionRequestBuilder($crypto, $config);
        $responseParser = new ResponseParser();
        $client = new Client($config, $logger, $httpClient, $requestBuilder, $responseParser, $validator);

        $validTransaction = [
            'Amount' => '50.00',
            'Currency' => 'USD',
            'Description' => 'Valid transaction',
        ];

        $response = $client->registerTransaction($validTransaction);
        $this->assertTrue($response->isSuccessful());
    }

    public function testNotificationHandlerIntegration(): void
    {
        $logger = new OpayoLogger($this->logFile);
        $vendorName = 'testvendor';
        $handler = new NotificationHandler('1234567890123456', $logger, 'https://example.com', $vendorName);

        $securityKey = 'test-security-key';
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'OK',
            'TxAuthNo' => '12345',
            'AVSCV2' => 'ALL MATCH',
            'AddressResult' => 'MATCHED',
            'PostCodeResult' => 'MATCHED',
            'CV2Result' => 'MATCHED',
            'GiftAid' => '0',
            '3DSecureStatus' => 'OK',
            'CAVV' => '',
            'AddressStatus' => 'NONE',
            'PayerStatus' => 'VERIFIED',
            'CardType' => 'VISA',
            'Last4Digits' => '1234',
            'DeclineCode' => '00',
            'ExpiryDate' => '1225',
            'FraudResponse' => 'ACCEPT',
            'BankAuthCode' => 'ABC123',
        ];

        // Generate valid signature using all 21 fields in correct order
        $signatureFields = [
            'VPSTxId', 'VendorTxCode', 'Status', 'TxAuthNo', 'VendorName',
            'AVSCV2', 'SecurityKey', 'AddressResult', 'PostCodeResult', 'CV2Result',
            'GiftAid', '3DSecureStatus', 'CAVV', 'AddressStatus', 'PayerStatus',
            'CardType', 'Last4Digits', 'DeclineCode', 'ExpiryDate', 'FraudResponse', 'BankAuthCode',
        ];

        $signatureString = '';
        foreach ($signatureFields as $field) {
            if ($field === 'VendorName') {
                $signatureString .= strtolower($vendorName);
            } elseif ($field === 'SecurityKey') {
                $signatureString .= $securityKey;
            } else {
                $signatureString .= $data[$field] ?? '';
            }
        }
        $data['VPSSignature'] = strtoupper(md5($signatureString));

        $successCalled = false;
        $response = $handler->handle(
            $data,
            fn($txCode) => $securityKey,
            fn($vpsTxId) => false,
            fn($txCode) => '/success',
            function ($txCode, $data) use (&$successCalled) {
                $successCalled = true;
            },
            fn() => null,
            fn() => null
        );

        $this->assertInstanceOf(NotificationResponse::class, $response);
        $this->assertTrue($successCalled);
        $this->assertSame(NotificationResponse::STATUS_OK, $response->getStatus());
    }

    public function testLoggingAcrossComponents(): void
    {
        $logger = new OpayoLogger($this->logFile);
        $config = Config::sandbox('TestVendor', '1234567890123456');

        $mock = new MockHandler([
            new Response(200, [], "Status=OK&StatusDetail=Success&VPSTxId=123&SecurityKey=KEY&"),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = $this->createClient($config, $logger, $httpClient);

        $client->registerTransaction([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => 'Test',
        ]);

        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('Registering Opayo transaction', $logContent);
        $this->assertStringContainsString('Opayo registration response', $logContent);
    }

    public function test3DSecureWorkflow(): void
    {
        $config = Config::sandbox('TestVendor', '1234567890123456');
        $logger = new OpayoLogger($this->logFile);

        $responseBody = "Status=3DAUTH&" .
                       "StatusDetail=3D Secure authentication required&" .
                       "VPSTxId={12345678-1234-1234-1234-123456789012}&" .
                       "NextURL=https://test.sagepay.com/3dsecure&" .
                       "SecurityKey=3DSKEY&";

        $mock = new MockHandler([
            new Response(200, [], $responseBody),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = $this->createClient($config, $logger, $httpClient);

        $response = $client->registerTransaction([
            'Amount' => '10.00',
            'Currency' => 'GBP',
            'Description' => '3D Secure test',
        ]);

        $this->assertTrue($response->requires3DSecure());
        $this->assertSame('3DAUTH', $response->getStatus());
        $this->assertNotEmpty($response->getNextURL());
        $this->assertNotEmpty($response->getSecurityKey());
    }

    public function testSandboxVsLiveConfiguration(): void
    {
        $sandboxConfig = Config::sandbox('SandboxVendor', '1234567890123456');
        $liveConfig = Config::live('LiveVendor', '1234567890123456');

        $this->assertTrue($sandboxConfig->isSandbox());
        $this->assertFalse($sandboxConfig->isLive());
        $this->assertSame(Config::ENDPOINT_TEST, $sandboxConfig->endpoint);

        $this->assertTrue($liveConfig->isLive());
        $this->assertFalse($liveConfig->isSandbox());
        $this->assertSame(Config::ENDPOINT_LIVE, $liveConfig->endpoint);
    }

    public function testEndToEndTransactionWithLogging(): void
    {
        $config = Config::fromArray([
            'vendor' => 'E2ETestVendor',
            'encryption_password' => '1234567890123456',
            'endpoint' => Config::ENDPOINT_TEST,
        ]);

        $logger = new OpayoLogger($this->logFile);

        $responseBody = "Status=OK&" .
                       "StatusDetail=2000 : AuthCode: 123456&" .
                       "VPSTxId={AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}&" .
                       "SecurityKey=RANDOMKEY&" .
                       "TxAuthNo=123456&";

        $mock = new MockHandler([
            new Response(200, [], $responseBody),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = $this->createClient($config, $logger, $httpClient);

        $transaction = [
            'VendorTxCode' => 'E2E-TX-' . time(),
            'Amount' => '149.99',
            'Currency' => 'EUR',
            'Description' => 'End-to-end integration test',
            'CustomerEMail' => 'e2e@test.com',
        ];

        $response = $client->registerTransaction($transaction);

        // Verify response
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('OK', $response->getStatus());
        $this->assertNotEmpty($response->getVPSTxId());
        $this->assertNotEmpty($response->getSecurityKey());

        // Verify logging
        $this->assertFileExists($this->logFile);
        $logContent = file_get_contents($this->logFile);
        $this->assertStringContainsString('E2E-TX-', $logContent);
        $this->assertStringContainsString('149.99', $logContent);
    }
}
