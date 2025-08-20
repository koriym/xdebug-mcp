<?php

namespace Koriym\XdebugMcp\Exceptions;

use RuntimeException;

/**
 * Exception thrown when Xdebug extension is not loaded or not available
 */
class XdebugNotAvailableException extends RuntimeException
{
}