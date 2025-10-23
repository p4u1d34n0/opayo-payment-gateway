<?php

namespace Opayo\Http;

use InvalidArgumentException;

/**
 * HTTP client configuration options
 *
 * Encapsulates HTTP client settings for API requests
 */
class HttpOptions
{
    /**
     * @param int $timeout Request timeout in seconds
     * @param int $connectTimeout Connection timeout in seconds
     * @param bool $verify Enable SSL verification
     */
    public function __construct(
        public readonly int $timeout = 30,
        public readonly int $connectTimeout = 10,
        public readonly bool $verify = true
    ) {
        $this->validate();
    }

    /**
     * Convert to array format for HTTP client
     *
     * @return array<string, int|bool>
     */
    public function toArray(): array
    {
        return [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'verify' => $this->verify,
        ];
    }

    /**
     * Create with custom timeout
     *
     * @param int $timeout
     * @return self
     */
    public static function withTimeout(int $timeout): self
    {
        return new self(timeout: $timeout);
    }

    /**
     * Validate configuration values
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        if ($this->connectTimeout <= 0) {
            throw new InvalidArgumentException('Connect timeout must be positive');
        }
    }
}
