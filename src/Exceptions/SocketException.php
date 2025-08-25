<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Exceptions;

use RuntimeException;
use Throwable;

use function in_array;
use function str_contains;
use function strtolower;

use const SOCKET_ECONNRESET;
use const SOCKET_EPIPE;

/**
 * Exception thrown when socket operations fail
 */
class SocketException extends RuntimeException
{
    private const array CONNECTION_LOST_ERROR_CODES = [SOCKET_EPIPE, SOCKET_ECONNRESET];

    public function __construct(string $message = '', int $code = 0, private int|null $socketErrorCode = null, Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function isConnectionLost(): bool
    {
        if ($this->socketErrorCode !== null) {
            return in_array($this->socketErrorCode, self::CONNECTION_LOST_ERROR_CODES, true);
        }

        // Improved fallback with standardized connection error patterns
        $message = strtolower($this->getMessage());
        $connectionLostPatterns = [
            'connection lost',
            'connection reset',
            'broken pipe',
            'connection refused',
            'connection aborted',
        ];

        foreach ($connectionLostPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function getSocketErrorCode(): int|null
    {
        return $this->socketErrorCode;
    }
}
