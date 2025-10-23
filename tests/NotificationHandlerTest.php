<?php

namespace Opayo\Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Opayo\NotificationHandler;
use Opayo\NotificationResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test suite for NotificationHandler class
 */
class NotificationHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private LoggerInterface $logger;
    private string $encryptionPassword;
    private string $baseURL;
    private string $vendorName;
    private string $securityKey;

    protected function setUp(): void
    {
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->encryptionPassword = '1234567890123456';
        $this->baseURL = 'https://example.com';
        $this->vendorName = 'testvendor';
        $this->securityKey = 'test-security-key';

        // Default logger expectations
        $this->logger->allows('info')->byDefault();
        $this->logger->allows('warning')->byDefault();
        $this->logger->allows('error')->byDefault();
    }

    /**
     * Generate valid signature according to Opayo Server Protocol 3.00
     * Uses all 21 fields in exact order with SecurityKey embedded
     */
    private function generateValidSignature(array $data, string $securityKey, string $vendorName): string
    {
        $signatureFields = [
            'VPSTxId',
            'VendorTxCode',
            'Status',
            'TxAuthNo',
            'VendorName',
            'AVSCV2',
            'SecurityKey',
            'AddressResult',
            'PostCodeResult',
            'CV2Result',
            'GiftAid',
            '3DSecureStatus',
            'CAVV',
            'AddressStatus',
            'PayerStatus',
            'CardType',
            'Last4Digits',
            'DeclineCode',
            'ExpiryDate',
            'FraudResponse',
            'BankAuthCode',
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

        return strtoupper(md5($signatureString));
    }

    /**
     * Create complete notification data with all 21 signature fields
     */
    private function createCompleteNotificationData(): array
    {
        return [
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
    }

    public function testHandleValidSuccessfulNotification(): void
    {
        $data = $this->createCompleteNotificationData();
        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';

        $successCalled = false;
        $onSuccess = function ($txCode, $data) use (&$successCalled) {
            $successCalled = true;
            $this->assertSame('TX-123', $txCode);
            $this->assertIsArray($data);
        };
        $onFailure = fn() => $this->fail('onFailure should not be called');
        $onRepeat = fn() => $this->fail('onRepeat should not be called');

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertTrue($successCalled, 'onSuccess callback should have been called');
        $this->assertInstanceOf(NotificationResponse::class, $response);
        $this->assertSame(NotificationResponse::STATUS_OK, $response->getStatus());
        $this->assertSame('https://example.com/success', $response->getRedirectURL());
    }

    public function testHandleInvalidSignature(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'OK',
            'VPSSignature' => 'INVALID_SIGNATURE',
        ];

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => $this->fail('onSuccess should not be called');
        $onFailure = fn() => $this->fail('onFailure should not be called');
        $onRepeat = fn() => $this->fail('onRepeat should not be called');

        $this->logger->expects('warning')
            ->once()
            ->with('Invalid signature', Mockery::type('array'));

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertSame(NotificationResponse::STATUS_INVALID, $response->getStatus());
        $this->assertStringContainsString('Signature mismatch', $response->getStatusDetail());
    }

    public function testHandleFailedTransaction(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'REJECTED',
            'TxAuthNo' => '',
            'AVSCV2' => '',
            'AddressResult' => '',
            'PostCodeResult' => '',
            'CV2Result' => '',
            'GiftAid' => '',
            '3DSecureStatus' => '',
            'CAVV' => '',
            'AddressStatus' => '',
            'PayerStatus' => '',
            'CardType' => '',
            'Last4Digits' => '',
            'DeclineCode' => '',
            'ExpiryDate' => '',
            'FraudResponse' => '',
            'BankAuthCode' => '',
        ];

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => $this->fail('onSuccess should not be called');
        $failureCalled = false;
        $onFailure = function ($txCode, $data) use (&$failureCalled) {
            $failureCalled = true;
            $this->assertSame('TX-123', $txCode);
        };
        $onRepeat = fn() => $this->fail('onRepeat should not be called');

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertTrue($failureCalled);
        $this->assertSame(NotificationResponse::STATUS_INVALID, $response->getStatus());
        $this->assertStringContainsString('Transaction failed', $response->getStatusDetail());
    }

    public function testHandleDuplicateNotification(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'OK',
            'TxAuthNo' => '12345',
            'AVSCV2' => '',
            'AddressResult' => '',
            'PostCodeResult' => '',
            'CV2Result' => '',
            'GiftAid' => '',
            '3DSecureStatus' => '',
            'CAVV' => '',
            'AddressStatus' => '',
            'PayerStatus' => '',
            'CardType' => '',
            'Last4Digits' => '',
            'DeclineCode' => '',
            'ExpiryDate' => '',
            'FraudResponse' => '',
            'BankAuthCode' => '',
        ];

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => true; // Already processed
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => $this->fail('onSuccess should not be called');
        $onFailure = fn() => $this->fail('onFailure should not be called');
        $repeatCalled = false;
        $onRepeat = function ($txCode) use (&$repeatCalled) {
            $repeatCalled = true;
            $this->assertSame('TX-123', $txCode);
        };

        $this->logger->expects('info')
            ->with('Duplicate notification', Mockery::type('array'));

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertTrue($repeatCalled);
        $this->assertSame(NotificationResponse::STATUS_OK, $response->getStatus());
        $this->assertStringContainsString('Already processed', $response->getStatusDetail());
    }

    public function testHandleMissingVendorTxCode(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'Status' => 'OK',
            // VendorTxCode is missing
        ];

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => null;
        $onFailure = fn() => null;
        $onRepeat = fn() => null;

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertInstanceOf(NotificationResponse::class, $response);
    }

    public function testHandleGetKeyCallbackFailure(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'OK',
        ];

        $getKey = function ($txCode) {
            throw new \RuntimeException('Database error');
        };
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => $this->fail('onSuccess should not be called');
        $onFailure = fn() => $this->fail('onFailure should not be called');
        $onRepeat = fn() => $this->fail('onRepeat should not be called');

        $this->logger->expects('error')
            ->once()
            ->with('Failed to retrieve security key', Mockery::type('array'));

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertSame(NotificationResponse::STATUS_ERROR, $response->getStatus());
        $this->assertStringContainsString('Failed to retrieve security key', $response->getStatusDetail());
    }

    public function testHandleOnSuccessCallbackException(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'OK',
            'TxAuthNo' => '12345',
            'AVSCV2' => '',
            'AddressResult' => '',
            'PostCodeResult' => '',
            'CV2Result' => '',
            'GiftAid' => '',
            '3DSecureStatus' => '',
            'CAVV' => '',
            'AddressStatus' => '',
            'PayerStatus' => '',
            'CardType' => '',
            'Last4Digits' => '',
            'DeclineCode' => '',
            'ExpiryDate' => '',
            'FraudResponse' => '',
            'BankAuthCode' => '',
        ];

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = function ($txCode, $data) {
            throw new \RuntimeException('Callback error');
        };
        $onFailure = fn() => $this->fail('onFailure should not be called');
        $onRepeat = fn() => $this->fail('onRepeat should not be called');

        $this->logger->expects('error')
            ->once()
            ->with('Opayo notification error', Mockery::type('array'));

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertSame(NotificationResponse::STATUS_ERROR, $response->getStatus());
    }

    public function testHandleLogsNotificationDetails(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'OK',
            'TxAuthNo' => '12345',
            'AVSCV2' => '',
            'AddressResult' => '',
            'PostCodeResult' => '',
            'CV2Result' => '',
            'GiftAid' => '',
            '3DSecureStatus' => '',
            'CAVV' => '',
            'AddressStatus' => '',
            'PayerStatus' => '',
            'CardType' => '',
            'Last4Digits' => '',
            'DeclineCode' => '',
            'ExpiryDate' => '',
            'FraudResponse' => '',
            'BankAuthCode' => '',
        ];

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $this->logger->expects('info')
            ->once()
            ->with('Processing Opayo notification', Mockery::on(function ($context) {
                return $context['vendor_tx_code'] === 'TX-123'
                    && $context['vps_tx_id'] === '{12345678-1234-1234-1234-123456789012}'
                    && $context['status'] === 'OK';
            }));

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => null;
        $onFailure = fn() => null;
        $onRepeat = fn() => null;

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);
    }

    public function testHandleBaseURLTrimsTrailingSlash(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'OK',
            'TxAuthNo' => '',
            'AVSCV2' => '',
            'AddressResult' => '',
            'PostCodeResult' => '',
            'CV2Result' => '',
            'GiftAid' => '',
            '3DSecureStatus' => '',
            'CAVV' => '',
            'AddressStatus' => '',
            'PayerStatus' => '',
            'CardType' => '',
            'Last4Digits' => '',
            'DeclineCode' => '',
            'ExpiryDate' => '',
            'FraudResponse' => '',
            'BankAuthCode' => '',
        ];

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => null;
        $onFailure = fn() => null;
        $onRepeat = fn() => null;

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, 'https://example.com/', $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertSame('https://example.com/success', $response->getRedirectURL());
    }

    public function testHandleResponseFormattingForSuccessfulPayment(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'OK',
            'TxAuthNo' => '12345',
            'AVSCV2' => '',
            'AddressResult' => '',
            'PostCodeResult' => '',
            'CV2Result' => '',
            'GiftAid' => '',
            '3DSecureStatus' => '',
            'CAVV' => '',
            'AddressStatus' => '',
            'PayerStatus' => '',
            'CardType' => '',
            'Last4Digits' => '',
            'DeclineCode' => '',
            'ExpiryDate' => '',
            'FraudResponse' => '',
            'BankAuthCode' => '',
        ];

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => null;
        $onFailure = fn() => null;
        $onRepeat = fn() => null;

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $formatted = $response->format();
        $this->assertStringContainsString('Status=OK', $formatted);
        $this->assertStringContainsString('StatusDetail=Payment successful', $formatted);
        $this->assertStringContainsString('RedirectURL=https://example.com/success', $formatted);
    }

    public function testHandleEmptyStatus(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => '',
            'TxAuthNo' => '',
            'AVSCV2' => '',
            'AddressResult' => '',
            'PostCodeResult' => '',
            'CV2Result' => '',
            'GiftAid' => '',
            '3DSecureStatus' => '',
            'CAVV' => '',
            'AddressStatus' => '',
            'PayerStatus' => '',
            'CardType' => '',
            'Last4Digits' => '',
            'DeclineCode' => '',
            'ExpiryDate' => '',
            'FraudResponse' => '',
            'BankAuthCode' => '',
        ];

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => $this->fail('onSuccess should not be called');
        $failureCalled = false;
        $onFailure = function () use (&$failureCalled) {
            $failureCalled = true;
        };
        $onRepeat = fn() => $this->fail('onRepeat should not be called');

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertTrue($failureCalled);
        $this->assertSame(NotificationResponse::STATUS_INVALID, $response->getStatus());
    }

    public function testHandleNotAuthedStatus(): void
    {
        $data = [
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
            'VendorTxCode' => 'TX-123',
            'Status' => 'NOTAUTHED',
            'TxAuthNo' => '',
            'AVSCV2' => '',
            'AddressResult' => '',
            'PostCodeResult' => '',
            'CV2Result' => '',
            'GiftAid' => '',
            '3DSecureStatus' => '',
            'CAVV' => '',
            'AddressStatus' => '',
            'PayerStatus' => '',
            'CardType' => '',
            'Last4Digits' => '',
            'DeclineCode' => '',
            'ExpiryDate' => '',
            'FraudResponse' => '',
            'BankAuthCode' => '',
        ];

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/fail';
        $onSuccess = fn() => $this->fail('onSuccess should not be called');
        $failureCalled = false;
        $onFailure = function ($txCode, $data) use (&$failureCalled) {
            $failureCalled = true;
            $this->assertSame('TX-123', $txCode);
        };
        $onRepeat = fn() => $this->fail('onRepeat should not be called');

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertTrue($failureCalled);
        $this->assertSame(NotificationResponse::STATUS_INVALID, $response->getStatus());
    }

    public function testVerifySignatureWithURLEncodedData(): void
    {
        // Test that signature verification works with URL-encoded data
        $data = $this->createCompleteNotificationData();

        // URL-encode some values that might contain special characters
        $data['AVSCV2'] = urlencode('ALL MATCH');
        $data['AddressResult'] = urlencode('MATCHED');

        // Generate signature with decoded values (as Opayo does)
        $decodedData = array_map('urldecode', $data);
        $data['VPSSignature'] = $this->generateValidSignature($decodedData, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => null;
        $onFailure = fn() => null;
        $onRepeat = fn() => null;

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertSame(NotificationResponse::STATUS_OK, $response->getStatus());
    }

    public function testDebugSignatureReturnsCorrectStructure(): void
    {
        $data = $this->createCompleteNotificationData();
        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $debug = $handler->debugSignature($data, $this->securityKey);

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('signature_string', $debug);
        $this->assertArrayHasKey('field_values', $debug);
        $this->assertArrayHasKey('expected_signature', $debug);
        $this->assertArrayHasKey('received_signature', $debug);
        $this->assertArrayHasKey('match', $debug);

        $this->assertTrue($debug['match'], 'Signatures should match');
        $this->assertSame($debug['expected_signature'], $debug['received_signature']);
    }

    public function testDebugSignatureShowsFieldValues(): void
    {
        $data = $this->createCompleteNotificationData();
        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $debug = $handler->debugSignature($data, $this->securityKey);

        // Check that all 21 fields are present
        $this->assertCount(21, $debug['field_values']);

        // Verify VendorName is lowercase
        $this->assertSame(strtolower($this->vendorName), $debug['field_values']['VendorName']);

        // Verify SecurityKey is embedded
        $this->assertSame($this->securityKey, $debug['field_values']['SecurityKey']);

        // Verify data fields are present
        $this->assertSame($data['VPSTxId'], $debug['field_values']['VPSTxId']);
        $this->assertSame($data['VendorTxCode'], $debug['field_values']['VendorTxCode']);
    }

    public function testSignatureVerificationWithAll21Fields(): void
    {
        // Test with complete data including all 6 previously missing fields
        $data = $this->createCompleteNotificationData();

        // Override to set specific values for the 6 critical fields
        $data['DeclineCode'] = '00';
        $data['ExpiryDate'] = '1225';
        $data['FraudResponse'] = 'ACCEPT';
        $data['BankAuthCode'] = 'ABC123';

        $data['VPSSignature'] = $this->generateValidSignature($data, $this->securityKey, $this->vendorName);

        $getKey = fn($txCode) => $this->securityKey;
        $checkProcessed = fn($vpsTxId) => false;
        $getRedirectURL = fn($txCode) => '/success';
        $onSuccess = fn() => null;
        $onFailure = fn() => null;
        $onRepeat = fn() => null;

        $handler = new NotificationHandler($this->encryptionPassword, $this->logger, $this->baseURL, $this->vendorName);
        $response = $handler->handle($data, $getKey, $checkProcessed, $getRedirectURL, $onSuccess, $onFailure, $onRepeat);

        $this->assertSame(NotificationResponse::STATUS_OK, $response->getStatus());
    }
}
