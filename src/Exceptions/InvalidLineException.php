<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when an invalid line number is specified for breakpoints or file operations
 */
final class InvalidLineException extends InvalidArgumentException
{
}
