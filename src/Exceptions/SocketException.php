<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Exceptions;

use RuntimeException;
use Throwable;

use function in_array;
use function str_contains;

use const SOCKET_ECONNRESET;
use const SOCKET_EPIPE;

/**
 * Exception thrown when socket operations fail
 */
class SocketException extends RuntimeException
{
    private const int CONNECTION_LOST_ERROR_CODES = [SOCKET_EPIPE, SOCKET_ECONNRESET];

    public function __construct(string $message = '', int $code = 0, private int|null $socketErrorCode = null, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function isConnectionLost(): bool
    {
        if ($this->socketErrorCode !== null) {
            return in_array($this->socketErrorCode, self::CONNECTION_LOST_ERROR_CODES, true);
        }

        // Fallback to string matching for backward compatibility
        return str_contains($this->getMessage(), 'Connection lost');
    }

    public function getSocketErrorCode(): int|null
    {
        return $this->socketErrorCode;
    }
}
