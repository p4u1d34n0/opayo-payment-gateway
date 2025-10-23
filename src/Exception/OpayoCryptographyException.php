<?php

namespace Opayo\Exception;

/**
 * Exception thrown for encryption/decryption errors
 */
class OpayoCryptographyException extends OpayoException
{
    public const ENCRYPTION_FAILED = 5001;
    public const DECRYPTION_FAILED = 5002;
    public const INVALID_KEY = 5003;
}
