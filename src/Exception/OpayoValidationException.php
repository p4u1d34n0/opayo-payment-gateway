<?php

namespace Opayo\Exception;

/**
 * Exception thrown for data validation errors
 */
class OpayoValidationException extends OpayoException
{
    public const MISSING_REQUIRED_FIELD = 3001;
    public const INVALID_FIELD_FORMAT = 3002;
    public const FIELD_TOO_LONG = 3003;
    public const INVALID_AMOUNT = 3004;
    public const INVALID_CURRENCY = 3005;
}
