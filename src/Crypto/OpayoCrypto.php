<?php

namespace Opayo\Crypto;

use Opayo\Exception\OpayoCryptographyException;

/**
 * Encryption and decryption for Opayo API communication
 *
 * SECURITY WARNING: This implementation uses the encryption key as the IV,
 * which is required by Opayo's legacy protocol but is not cryptographically secure
 * by modern standards. This is maintained for backward compatibility with Opayo's API.
 */
class OpayoCrypto implements CryptoInterface
{
    /**
     * Encrypt data for Opayo API transmission
     *
     * Uses AES-128-CBC with PKCS7 padding. The key is used as both the encryption key
     * and IV as required by Opayo's protocol.
     *
     * @param string $data Data to encrypt
     * @param string $key Encryption key (must be exactly 16 bytes for AES-128)
     * @return string Encrypted data prefixed with '@' and hex-encoded
     * @throws OpayoCryptographyException
     */
    public function encrypt(string $data, string $key): string
    {
        $this->validateKey($key);

        // Apply PKCS7 padding manually as required by Opayo
        $pad = 16 - (mb_strlen($data) % 16);
        $data .= str_repeat(chr($pad), $pad);

        // Encrypt using key as IV (Opayo protocol requirement)
        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $key);

        if ($encrypted === false) {
            throw new OpayoCryptographyException(
                'Encryption failed: ' . openssl_error_string(),
                OpayoCryptographyException::ENCRYPTION_FAILED
            );
        }

        return '@' . mb_strtoupper(bin2hex($encrypted));
    }

    /**
     * Decrypt data received from Opayo API
     *
     * @param string $crypt Encrypted data (with '@' prefix and hex-encoded)
     * @param string $key Encryption key
     * @return string Decrypted data
     * @throws OpayoCryptographyException
     */
    public function decrypt(string $crypt, string $key): string
    {
        $this->validateKey($key);

        // Remove '@' prefix and decode hex
        $hex = ltrim($crypt, '@');
        $data = @hex2bin($hex);

        if ($data === false) {
            throw new OpayoCryptographyException(
                'Invalid encrypted data format: not valid hexadecimal',
                OpayoCryptographyException::DECRYPTION_FAILED
            );
        }

        // Decrypt using key as IV (Opayo protocol requirement)
        $decrypted = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $key);

        if ($decrypted === false) {
            throw new OpayoCryptographyException(
                'Decryption failed: ' . openssl_error_string(),
                OpayoCryptographyException::DECRYPTION_FAILED
            );
        }

        // Remove PKCS7 padding
        $pad = ord(mb_substr($decrypted, -1));

        if ($pad < 1 || $pad > 16) {
            throw new OpayoCryptographyException(
                'Invalid padding in decrypted data',
                OpayoCryptographyException::DECRYPTION_FAILED
            );
        }

        return mb_substr($decrypted, 0, -$pad);
    }

    /**
     * Validate encryption key
     *
     * @param string $key
     * @return void
     * @throws OpayoCryptographyException
     */
    private function validateKey(string $key): void
    {
        $keyLength = mb_strlen($key, '8bit');

        if ($keyLength !== 16) {
            throw new OpayoCryptographyException(
                "Invalid key length: expected 16 bytes, got {$keyLength} bytes",
                OpayoCryptographyException::INVALID_KEY
            );
        }
    }
}
