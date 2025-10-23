<?php

namespace Opayo\Exception;

/**
 * Exception thrown for authentication and authorization errors
 */
class OpayoAuthenticationException extends OpayoException
{
    public const INVALID_VENDOR = 4001;
    public const INVALID_SIGNATURE = 4002;
    public const UNAUTHORIZED = 4003;
}
