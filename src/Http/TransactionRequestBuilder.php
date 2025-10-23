<?php

namespace Opayo\Http;

use Opayo\Config;
use Opayo\Crypto\CryptoInterface;

/**
 * Builds transaction request payload for Opayo API
 *
 * Responsible for constructing the POST fields sent to Opayo
 */
class TransactionRequestBuilder
{
    private const VPS_PROTOCOL_VERSION = '3.00';
    private const TX_TYPE_PAYMENT = 'PAYMENT';

    public function __construct(
        private readonly CryptoInterface $crypto,
        private readonly Config $config
    ) {
    }

    /**
     * Build POST fields for transaction registration
     *
     * @param array<string, mixed> $fields Transaction fields
     * @return array<string, string> POST fields ready for HTTP request
     */
    public function build(array $fields): array
    {
        // Generate VendorTxCode if not provided
        if (!isset($fields['VendorTxCode']) || empty($fields['VendorTxCode'])) {
            $fields['VendorTxCode'] = $this->generateVendorTxCode();
        }

        // Encrypt transaction data
        $crypt = $this->encryptFields($fields);

        // Build POST fields
        return [
            'VPSProtocol' => self::VPS_PROTOCOL_VERSION,
            'TxType' => self::TX_TYPE_PAYMENT,
            'Vendor' => $this->config->vendor,
            'Crypt' => $crypt,
        ];
    }

    /**
     * Generate unique vendor transaction code
     *
     * @return string
     */
    private function generateVendorTxCode(): string
    {
        return uniqid('TX-', true);
    }

    /**
     * Encrypt transaction fields
     *
     * @param array<string, mixed> $fields
     * @return string
     */
    private function encryptFields(array $fields): string
    {
        $queryString = http_build_query($fields);
        return $this->crypto->encrypt($queryString, $this->config->encryptionPassword);
    }

    /**
     * Get the generated or provided VendorTxCode from fields
     *
     * @param array<string, mixed> $fields
     * @return string
     */
    public function getVendorTxCode(array $fields): string
    {
        return $fields['VendorTxCode'] ?? $this->generateVendorTxCode();
    }
}
