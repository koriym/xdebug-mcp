<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Exceptions;

use RuntimeException;

/**
 * Exception thrown when Xdebug cannot be loaded into the target PHP binary
 */
class XdebugNotAvailableException extends RuntimeException
{
}
