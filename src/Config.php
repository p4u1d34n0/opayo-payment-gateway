<?php

namespace Opayo;

use Opayo\Exception\OpayoConfigException;

/**
 * Opayo configuration class
 *
 * Stores vendor credentials and endpoint configuration for Opayo API integration.
 * Supports both sandbox and live environments.
 */
class Config
{
    public const ENDPOINT_TEST = 'https://test.sagepay.com/gateway/service/vspserver-register.vsp';
    public const ENDPOINT_LIVE = 'https://live.sagepay.com/gateway/service/vspserver-register.vsp';

    public readonly string $vendor;
    public readonly string $encryptionPassword;
    public readonly string $endpoint;

    /**
     * @param string $vendor Opayo vendor name
     * @param string $encryptionPassword Encryption password provided by Opayo
     * @param string $endpoint Full endpoint URL
     * @throws OpayoConfigException If required fields are missing
     */
    public function __construct(string $vendor, string $encryptionPassword, string $endpoint)
    {
        $this->validate($vendor, $encryptionPassword, $endpoint);

        $this->vendor = $vendor;
        $this->encryptionPassword = $encryptionPassword;
        $this->endpoint = $endpoint;
    }

    /**
     * Create configuration from array
     *
     * @param array<string, mixed> $config Configuration array
     * @return self
     * @throws OpayoConfigException
     */
    public static function fromArray(array $config): self
    {
        $vendor = $config['vendor'] ?? '';
        $password = $config['encryption_password'] ?? '';
        $endpoint = $config['endpoint'] ?? '';

        return new self($vendor, $password, $endpoint);
    }

    /**
     * Create configuration from environment variables
     *
     * Reads OPAYO_VENDOR, OPAYO_ENCRYPTION_PASSWORD, and OPAYO_ENVIRONMENT
     *
     * @return self
     * @throws OpayoConfigException
     */
    public static function fromEnvironment(): self
    {
        $vendor = $_ENV['OPAYO_VENDOR'] ?? getenv('OPAYO_VENDOR');
        $password = $_ENV['OPAYO_ENCRYPTION_PASSWORD'] ?? getenv('OPAYO_ENCRYPTION_PASSWORD');
        $environment = $_ENV['OPAYO_ENVIRONMENT'] ?? getenv('OPAYO_ENVIRONMENT') ?: 'sandbox';

        if (!is_string($vendor) || empty($vendor)) {
            throw new OpayoConfigException(
                'OPAYO_VENDOR environment variable is not set',
                OpayoConfigException::MISSING_VENDOR
            );
        }

        if (!is_string($password) || empty($password)) {
            throw new OpayoConfigException(
                'OPAYO_ENCRYPTION_PASSWORD environment variable is not set',
                OpayoConfigException::MISSING_PASSWORD
            );
        }

        $endpoint = match ($environment) {
            'live', 'production' => self::ENDPOINT_LIVE,
            'sandbox', 'test' => self::ENDPOINT_TEST,
            default => throw new OpayoConfigException(
                "Invalid environment: $environment. Must be 'sandbox' or 'live'",
                OpayoConfigException::INVALID_ENVIRONMENT
            )
        };

        return new self($vendor, $password, $endpoint);
    }

    /**
     * Create sandbox/test configuration
     *
     * @param string $vendor
     * @param string $encryptionPassword
     * @return self
     */
    public static function sandbox(string $vendor, string $encryptionPassword): self
    {
        return new self($vendor, $encryptionPassword, self::ENDPOINT_TEST);
    }

    /**
     * Create live/production configuration
     *
     * @param string $vendor
     * @param string $encryptionPassword
     * @return self
     */
    public static function live(string $vendor, string $encryptionPassword): self
    {
        return new self($vendor, $encryptionPassword, self::ENDPOINT_LIVE);
    }

    /**
     * Check if configuration is for sandbox environment
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->endpoint === self::ENDPOINT_TEST;
    }

    /**
     * Check if configuration is for live environment
     *
     * @return bool
     */
    public function isLive(): bool
    {
        return $this->endpoint === self::ENDPOINT_LIVE;
    }

    /**
     * Validate configuration values
     *
     * @param string $vendor
     * @param string $encryptionPassword
     * @param string $endpoint
     * @return void
     * @throws OpayoConfigException
     */
    private function validate(string $vendor, string $encryptionPassword, string $endpoint): void
    {
        if (empty($vendor)) {
            throw new OpayoConfigException(
                'Vendor name is required',
                OpayoConfigException::MISSING_VENDOR
            );
        }

        if (empty($encryptionPassword)) {
            throw new OpayoConfigException(
                'Encryption password is required',
                OpayoConfigException::MISSING_PASSWORD
            );
        }

        if (empty($endpoint)) {
            throw new OpayoConfigException(
                'Endpoint URL is required',
                OpayoConfigException::MISSING_ENDPOINT
            );
        }
    }
}
