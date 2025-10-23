<?php

namespace Opayo\Exception;

/**
 * Exception thrown for network and HTTP-related errors
 */
class OpayoNetworkException extends OpayoException
{
    public const CONNECTION_FAILED = 2001;
    public const TIMEOUT = 2002;
    public const HTTP_ERROR = 2003;
    public const INVALID_RESPONSE = 2004;
}
