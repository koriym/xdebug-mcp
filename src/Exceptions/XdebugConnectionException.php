<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Exceptions;

use RuntimeException;

/**
 * Exception thrown when Xdebug connection fails or is not available
 */
class XdebugConnectionException extends RuntimeException
{
}