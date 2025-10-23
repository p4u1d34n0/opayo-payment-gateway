<?php

namespace Opayo;

/**
 * Value object representing a response to Opayo notification callback
 */
class NotificationResponse
{
    public const STATUS_OK = 'OK';
    public const STATUS_INVALID = 'INVALID';
    public const STATUS_ERROR = 'ERROR';

    private function __construct(
        private readonly string $status,
        private readonly string $statusDetail,
        private readonly string $redirectURL
    ) {
    }

    /**
     * Create a successful response
     *
     * @param string $redirectURL Where to redirect the customer
     * @param string $detail Optional status detail message
     * @return self
     */
    public static function success(string $redirectURL, string $detail = 'Payment successful'): self
    {
        return new self(self::STATUS_OK, $detail, $redirectURL);
    }

    /**
     * Create an invalid response (e.g., signature mismatch)
     *
     * @param string $redirectURL Where to redirect the customer
     * @param string $detail Status detail message
     * @return self
     */
    public static function invalid(string $redirectURL, string $detail = 'Invalid request'): self
    {
        return new self(self::STATUS_INVALID, $detail, $redirectURL);
    }

    /**
     * Create an error response (e.g., internal server error)
     *
     * @param string $redirectURL Where to redirect the customer
     * @param string $detail Status detail message
     * @return self
     */
    public static function error(string $redirectURL, string $detail = 'Internal server error'): self
    {
        return new self(self::STATUS_ERROR, $detail, $redirectURL);
    }

    /**
     * Get the status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get the status detail message
     *
     * @return string
     */
    public function getStatusDetail(): string
    {
        return $this->statusDetail;
    }

    /**
     * Get the redirect URL
     *
     * @return string
     */
    public function getRedirectURL(): string
    {
        return $this->redirectURL;
    }

    /**
     * Format response for Opayo protocol output
     *
     * @return string
     */
    public function format(): string
    {
        return "Status={$this->status}\r\n" .
               "StatusDetail={$this->statusDetail}\r\n" .
               "RedirectURL={$this->redirectURL}\r\n";
    }

    /**
     * Output response and terminate script
     *
     * @return never
     */
    public function send(): never
    {
        echo $this->format();
        exit;
    }
}
