<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Exceptions;

use RuntimeException;

/**
 * Exception thrown when Xdebug rejects a breakpoint_set command.
 *
 * Replaces the previous silent 'error' string sentinel returned by
 * DebugServer::setBreakpoint(), so a rejected breakpoint surfaces as a real
 * failure that the caller can decide to skip (see setupConditionalBreakpoints)
 * rather than an empty ["breaks":[]] result with no diagnostic.
 */
class BreakpointException extends RuntimeException
{
}
