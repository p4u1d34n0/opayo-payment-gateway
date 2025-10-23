<?php

namespace Opayo\Crypto;

use Opayo\Exception\OpayoCryptographyException;

/**
 * Interface for encryption/decryption operations
 *
 * Defines the contract for cryptographic operations required by Opayo API
 */
interface CryptoInterface
{
    /**
     * Encrypt data for Opayo API transmission
     *
     * @param string $data Data to encrypt
     * @param string $key Encryption key (must be exactly 16 bytes for AES-128)
     * @return string Encrypted data prefixed with '@' and hex-encoded
     * @throws OpayoCryptographyException
     */
    public function encrypt(string $data, string $key): string;

    /**
     * Decrypt data received from Opayo API
     *
     * @param string $crypt Encrypted data (with '@' prefix and hex-encoded)
     * @param string $key Encryption key
     * @return string Decrypted data
     * @throws OpayoCryptographyException
     */
    public function decrypt(string $crypt, string $key): string;
}
