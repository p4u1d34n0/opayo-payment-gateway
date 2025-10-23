<?php

namespace Opayo\Tests;

use Opayo\TransactionResponse;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for TransactionResponse class
 */
class TransactionResponseTest extends TestCase
{
    public function testIsSuccessfulReturnsTrueForOkStatus(): void
    {
        $response = new TransactionResponse(['Status' => 'OK']);

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isFailed());
        $this->assertFalse($response->requires3DSecure());
    }

    public function testIsFailedReturnsTrueForNotAuthedStatus(): void
    {
        $response = new TransactionResponse(['Status' => 'NOTAUTHED']);

        $this->assertTrue($response->isFailed());
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->requires3DSecure());
    }

    public function testIsFailedReturnsTrueForRejectedStatus(): void
    {
        $response = new TransactionResponse(['Status' => 'REJECTED']);

        $this->assertTrue($response->isFailed());
        $this->assertFalse($response->isSuccessful());
    }

    public function testIsFailedReturnsTrueForErrorStatus(): void
    {
        $response = new TransactionResponse(['Status' => 'ERROR']);

        $this->assertTrue($response->isFailed());
    }

    public function testIsFailedReturnsTrueForInvalidStatus(): void
    {
        $response = new TransactionResponse(['Status' => 'INVALID']);

        $this->assertTrue($response->isFailed());
    }

    public function testRequires3DSecureReturnsTrueFor3DAuthStatus(): void
    {
        $response = new TransactionResponse(['Status' => '3DAUTH']);

        $this->assertTrue($response->requires3DSecure());
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isFailed());
    }

    public function testGetStatusReturnsStatus(): void
    {
        $response = new TransactionResponse(['Status' => 'OK']);

        $this->assertSame('OK', $response->getStatus());
    }

    public function testGetStatusReturnsEmptyStringWhenMissing(): void
    {
        $response = new TransactionResponse([]);

        $this->assertSame('', $response->getStatus());
    }

    public function testGetStatusDetailReturnsDetail(): void
    {
        $response = new TransactionResponse(['StatusDetail' => 'Transaction successful']);

        $this->assertSame('Transaction successful', $response->getStatusDetail());
    }

    public function testGetVPSTxIdReturnsId(): void
    {
        $response = new TransactionResponse(['VPSTxId' => '{12345678-1234-1234-1234-123456789012}']);

        $this->assertSame('{12345678-1234-1234-1234-123456789012}', $response->getVPSTxId());
    }

    public function testGetSecurityKeyReturnsKey(): void
    {
        $response = new TransactionResponse(['SecurityKey' => 'TEST-SECURITY-KEY']);

        $this->assertSame('TEST-SECURITY-KEY', $response->getSecurityKey());
    }

    public function testGetNextURLReturnsUrl(): void
    {
        $response = new TransactionResponse(['NextURL' => 'https://test.sagepay.com/next']);

        $this->assertSame('https://test.sagepay.com/next', $response->getNextURL());
    }

    public function testGetReturnsFieldValue(): void
    {
        $response = new TransactionResponse(['CustomField' => 'CustomValue']);

        $this->assertSame('CustomValue', $response->get('CustomField'));
    }

    public function testGetReturnsNullForMissingField(): void
    {
        $response = new TransactionResponse([]);

        $this->assertNull($response->get('NonExistentField'));
    }

    public function testToArrayReturnsAllData(): void
    {
        $data = [
            'Status' => 'OK',
            'StatusDetail' => 'Transaction successful',
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
        ];

        $response = new TransactionResponse($data);

        $this->assertSame($data, $response->toArray());
    }

    public function testJsonSerializeReturnsAllData(): void
    {
        $data = [
            'Status' => 'OK',
            'StatusDetail' => 'Transaction successful',
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
        ];

        $response = new TransactionResponse($data);

        $this->assertSame($data, $response->jsonSerialize());
    }

    public function testJsonEncodeWorks(): void
    {
        $data = [
            'Status' => 'OK',
            'StatusDetail' => 'Transaction successful',
        ];

        $response = new TransactionResponse($data);
        $json = json_encode($response);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame($data, $decoded);
    }

    public function testResponseIsImmutable(): void
    {
        $data = [
            'Status' => 'OK',
            'VPSTxId' => '{12345678-1234-1234-1234-123456789012}',
        ];

        $response = new TransactionResponse($data);

        // Verify we can't modify the original data and affect the response
        $data['Status'] = 'REJECTED';

        $this->assertSame('OK', $response->getStatus());
    }

    public function testEmptyResponse(): void
    {
        $response = new TransactionResponse([]);

        $this->assertSame('', $response->getStatus());
        $this->assertSame('', $response->getStatusDetail());
        $this->assertSame('', $response->getVPSTxId());
        $this->assertSame('', $response->getSecurityKey());
        $this->assertSame('', $response->getNextURL());
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isFailed());
        $this->assertFalse($response->requires3DSecure());
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('OK', TransactionResponse::STATUS_OK);
        $this->assertSame('3DAUTH', TransactionResponse::STATUS_3DAUTH);
        $this->assertSame('NOTAUTHED', TransactionResponse::STATUS_NOTAUTHED);
        $this->assertSame('REJECTED', TransactionResponse::STATUS_REJECTED);
        $this->assertSame('ERROR', TransactionResponse::STATUS_ERROR);
        $this->assertSame('INVALID', TransactionResponse::STATUS_INVALID);
    }

    public function testFailedStatusesConstant(): void
    {
        $expected = ['NOTAUTHED', 'REJECTED', 'ERROR', 'INVALID'];

        $this->assertSame($expected, TransactionResponse::FAILED_STATUSES);
    }

    public function testIsAcceptedReturnsTrueForOkStatus(): void
    {
        $response = new TransactionResponse(['Status' => 'OK']);

        $this->assertTrue($response->isAccepted());
    }

    public function testIsAcceptedReturnsTrueFor3DAuth(): void
    {
        $response = new TransactionResponse(['Status' => '3DAUTH']);

        $this->assertTrue($response->isAccepted());
    }

    public function testIsAcceptedReturnsFalseForRejected(): void
    {
        $response = new TransactionResponse(['Status' => 'REJECTED']);

        $this->assertFalse($response->isAccepted());
    }

    public function testIsAcceptedReturnsFalseForError(): void
    {
        $response = new TransactionResponse(['Status' => 'ERROR']);

        $this->assertFalse($response->isAccepted());
    }

    public function testGetFieldIsUsedInternally(): void
    {
        // Test that the private getField method works correctly through public methods
        $response = new TransactionResponse([
            'Status' => 'OK',
            'StatusDetail' => 'Success',
            'VPSTxId' => 'TX123',
            'SecurityKey' => 'KEY456',
            'NextURL' => 'https://example.com',
        ]);

        $this->assertSame('OK', $response->getStatus());
        $this->assertSame('Success', $response->getStatusDetail());
        $this->assertSame('TX123', $response->getVPSTxId());
        $this->assertSame('KEY456', $response->getSecurityKey());
        $this->assertSame('https://example.com', $response->getNextURL());
    }
}

