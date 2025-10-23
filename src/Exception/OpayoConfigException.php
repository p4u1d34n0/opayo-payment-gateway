<?php

namespace Opayo\Exception;

/**
 * Exception thrown for configuration-related errors
 */
class OpayoConfigException extends OpayoException
{
    public const MISSING_VENDOR = 1001;
    public const MISSING_PASSWORD = 1002;
    public const MISSING_ENDPOINT = 1003;
    public const INVALID_ENVIRONMENT = 1004;
}
