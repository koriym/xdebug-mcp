<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Exceptions;

use RuntimeException;

/**
 * Exception thrown when command output doesn't contain valid JSON
 */
class InvalidOutputException extends RuntimeException
{
}
