<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a required file is not found
 */
class FileNotFoundException extends RuntimeException
{
}