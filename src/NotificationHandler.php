<?php

namespace Opayo;

use Opayo\Exception\OpayoAuthenticationException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles Opayo payment notification callbacks
 *
 * Processes server notifications from Opayo after payment processing
 */
class NotificationHandler
{
    private string $encryptionPassword;
    private LoggerInterface $logger;
    private string $baseURL;
    private string $vendorName;

    /**
     * @param string $encryptionPassword Encryption password for decrypting notifications
     * @param LoggerInterface $logger
     * @param string $baseURL Base URL for redirect URLs (e.g., https://yourdomain.com)
     * @param string $vendorName Your Opayo vendor name (required for signature verification)
     */
    public function __construct(
        string $encryptionPassword,
        LoggerInterface $logger,
        string $baseURL,
        string $vendorName
    ) {
        $this->encryptionPassword = $encryptionPassword;
        $this->logger = $logger;
        $this->baseURL = rtrim($baseURL, '/');
        $this->vendorName = $vendorName;
    }

    /**
     * Handle Opayo notification callback
     *
     * @param array<string, string> $data Notification data from Opayo
     * @param callable $getKey Callback to get security key: function(string $vendorTxCode): string
     * @param callable $checkProcessed Callback to check if already processed: function(string $vpsTxId): bool
     * @param callable $getRedirectURL Callback to get redirect URL: function(string $vendorTxCode): string
     * @param callable $onSuccess Callback on successful payment: function(string $vendorTxCode, array $data): void
     * @param callable $onFailure Callback on failed payment: function(string $vendorTxCode, array $data): void
     * @param callable $onRepeat Callback on repeat notification: function(string $vendorTxCode): void
     * @return NotificationResponse
     */
    public function handle(
        array $data,
        callable $getKey,
        callable $checkProcessed,
        callable $getRedirectURL,
        callable $onSuccess,
        callable $onFailure,
        callable $onRepeat
    ): NotificationResponse {
        try {
            $txCode = $data['VendorTxCode'] ?? '';
            $vpsTxId = $data['VPSTxId'] ?? '';
            $status = $data['Status'] ?? '';

            $this->logger->info('Processing Opayo notification', [
                'vendor_tx_code' => $txCode,
                'vps_tx_id' => $vpsTxId,
                'status' => $status,
            ]);

            // Get security key for this transaction
            try {
                $key = $getKey($txCode);
            } catch (Throwable $e) {
                $this->logger->error('Failed to retrieve security key', [
                    'vendor_tx_code' => $txCode,
                    'error' => $e->getMessage(),
                ]);
                return NotificationResponse::error($this->getFailureURL(), 'Failed to retrieve security key');
            }

            // Verify signature
            if (!$this->verifySignature($data, $key)) {
                $this->logger->warning('Invalid signature', [
                    'vendor_tx_code' => $txCode,
                    'vps_tx_id' => $vpsTxId,
                ]);
                return NotificationResponse::invalid($this->getFailureURL(), 'Signature mismatch');
            }

            // Check if already processed
            if ($checkProcessed($vpsTxId)) {
                $this->logger->info('Duplicate notification', [
                    'vendor_tx_code' => $txCode,
                    'vps_tx_id' => $vpsTxId,
                ]);
                $onRepeat($txCode);
                $redirectURL = $this->buildRedirectURL($getRedirectURL($txCode));
                return NotificationResponse::success($redirectURL, 'Already processed');
            }

            // Process based on status
            if ($status === 'OK') {
                $onSuccess($txCode, $data);
                $redirectURL = $this->buildRedirectURL($getRedirectURL($txCode));
                return NotificationResponse::success($redirectURL, 'Payment successful');
            } else {
                $onFailure($txCode, $data);
                return NotificationResponse::invalid($this->getFailureURL(), 'Transaction failed');
            }
        } catch (Throwable $e) {
            $this->logger->error('Opayo notification error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return NotificationResponse::error($this->getFailureURL(), 'Internal server error');
        }
    }

    /**
     * Verify Opayo notification signature
     *
     * Verifies the MD5 signature according to Opayo Server Protocol 3.00.
     * The signature is calculated from 21 fields in exact order with the SecurityKey embedded.
     *
     * IMPORTANT: All incoming data must be URL decoded before verification.
     *
     * @param array<string, string> $data Notification data from Opayo
     * @param string $securityKey Security key for this transaction
     * @return bool True if signature is valid
     */
    private function verifySignature(array $data, string $securityKey): bool
    {
        // The exact order of fields as specified in Opayo Server Protocol 3.00
        $signatureFields = [
            'VPSTxId',
            'VendorTxCode',
            'Status',
            'TxAuthNo',
            'VendorName',        // Vendor name must be lowercase
            'AVSCV2',
            'SecurityKey',       // Security key is embedded in the signature string
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

        // URL decode all incoming data first
        $decodedData = array_map('urldecode', $data);

        // Build signature string
        $signatureString = '';
        foreach ($signatureFields as $field) {
            if ($field === 'VendorName') {
                // VendorName must be lowercase as per Opayo specification
                $signatureString .= strtolower($this->vendorName);
            } elseif ($field === 'SecurityKey') {
                // SecurityKey is embedded in the hash
                $signatureString .= $securityKey;
            } else {
                // All other fields from the notification data
                $signatureString .= $decodedData[$field] ?? '';
            }
        }

        // Calculate MD5 hash and convert to uppercase
        $expectedSignature = strtoupper(md5($signatureString));
        $receivedSignature = strtoupper($decodedData['VPSSignature'] ?? '');

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Debug helper to show signature calculation details
     *
     * Use this method to troubleshoot signature verification issues.
     * NEVER use in production - only for debugging.
     *
     * @param array<string, string> $data Notification data from Opayo
     * @param string $securityKey Security key for this transaction
     * @return array<string, mixed> Debug information
     */
    public function debugSignature(array $data, string $securityKey): array
    {
        $signatureFields = [
            'VPSTxId', 'VendorTxCode', 'Status', 'TxAuthNo', 'VendorName',
            'AVSCV2', 'SecurityKey', 'AddressResult', 'PostCodeResult', 'CV2Result',
            'GiftAid', '3DSecureStatus', 'CAVV', 'AddressStatus', 'PayerStatus',
            'CardType', 'Last4Digits', 'DeclineCode', 'ExpiryDate', 'FraudResponse', 'BankAuthCode',
        ];

        $decodedData = array_map('urldecode', $data);
        $signatureString = '';
        $fieldValues = [];

        foreach ($signatureFields as $field) {
            if ($field === 'VendorName') {
                $value = strtolower($this->vendorName);
            } elseif ($field === 'SecurityKey') {
                $value = $securityKey;
            } else {
                $value = $decodedData[$field] ?? '';
            }
            $fieldValues[$field] = $value;
            $signatureString .= $value;
        }

        $expectedSignature = strtoupper(md5($signatureString));
        $receivedSignature = strtoupper($decodedData['VPSSignature'] ?? '');

        return [
            'signature_string' => $signatureString,
            'field_values' => $fieldValues,
            'expected_signature' => $expectedSignature,
            'received_signature' => $receivedSignature,
            'match' => hash_equals($expectedSignature, $receivedSignature),
        ];
    }

    /**
     * Build a redirect URL by combining base URL with a path
     *
     * @param string $path URL path (e.g., '/success', '/order/123')
     * @return string Complete URL
     */
    private function buildRedirectURL(string $path): string
    {
        return $this->baseURL . $path;
    }

    /**
     * Get the failure URL
     *
     * @return string
     */
    private function getFailureURL(): string
    {
        return $this->buildRedirectURL('/fail');
    }
}
