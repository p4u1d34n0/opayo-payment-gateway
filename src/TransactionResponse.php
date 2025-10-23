<?php

namespace Opayo;

use JsonSerializable;

/**
 * Immutable value object representing an Opayo transaction response
 */
class TransactionResponse implements JsonSerializable
{
    // Status constants
    public const STATUS_OK = 'OK';
    public const STATUS_3DAUTH = '3DAUTH';
    public const STATUS_NOTAUTHED = 'NOTAUTHED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_INVALID = 'INVALID';

    // Failed statuses array
    public const FAILED_STATUSES = [
        self::STATUS_NOTAUTHED,
        self::STATUS_REJECTED,
        self::STATUS_ERROR,
        self::STATUS_INVALID,
    ];

    /**
     * @param array<string, string> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * Get a field from the response data
     *
     * @param string $key Field name
     * @param string $default Default value if field not found
     * @return string
     */
    private function getField(string $key, string $default = ''): string
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get the status of the transaction
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->getField('Status');
    }

    /**
     * Get the status detail message
     *
     * @return string
     */
    public function getStatusDetail(): string
    {
        return $this->getField('StatusDetail');
    }

    /**
     * Get the VPS transaction ID
     *
     * @return string
     */
    public function getVPSTxId(): string
    {
        return $this->getField('VPSTxId');
    }

    /**
     * Get the security key for this transaction
     *
     * @return string
     */
    public function getSecurityKey(): string
    {
        return $this->getField('SecurityKey');
    }

    /**
     * Get the next URL (for 3D Secure or other redirects)
     *
     * @return string
     */
    public function getNextURL(): string
    {
        return $this->getField('NextURL');
    }

    /**
     * Check if the transaction was successful
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->getStatus() === self::STATUS_OK;
    }

    /**
     * Check if the transaction failed
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return in_array($this->getStatus(), self::FAILED_STATUSES, true);
    }

    /**
     * Check if the transaction requires 3D Secure authentication
     *
     * @return bool
     */
    public function requires3DSecure(): bool
    {
        return $this->getStatus() === self::STATUS_3DAUTH;
    }

    /**
     * Check if the transaction was accepted (successful or requires 3D Secure)
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->isSuccessful() || $this->requires3DSecure();
    }

    /**
     * Get a specific field from the response
     *
     * @param string $key
     * @return string|null
     */
    public function get(string $key): ?string
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Get all response data
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
