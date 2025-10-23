<?php

namespace Opayo\Exception;

use Exception;
use Throwable;

/**
 * Base exception for all Opayo-related errors
 */
class OpayoException extends Exception
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get context data associated with this exception
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
