<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Amp\Socket\Socket;
use Amp\TimeoutCancellation;
use Closure;
use Koriym\XdebugMcp\Exceptions\DebugSessionException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

use function bin2hex;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function simplexml_load_string;
use function str_contains;
use function strlen;
use function trim;

final class DbgpClient
{
    public function __construct(
        private readonly Socket $socket,
        private readonly float $readTimeout = 0.0,
        private readonly Closure|null $logger = null,
        private int $transactionId = 1,
    ) {
    }

    public function isConnected(): bool
    {
        return ! $this->socket->isClosed()
            && $this->socket->isWritable();
    }

    public function getNextTransactionId(): int
    {
        return $this->transactionId++;
    }

    /** @param array<string, string|int> $params */
    public function sendCommand(string $command, array $params = [], string|null $data = null): string
    {
        if (! $this->isConnected()) {
            throw new RuntimeException('No active Xdebug connection');
        }

        if (! $this->socket->isReadable()) {
            throw new RuntimeException('Xdebug connection lost or not readable');
        }

        $transactionId = $this->getNextTransactionId();
        $fullCommand = "{$command} -i {$transactionId}";

        foreach ($params as $key => $value) {
            $fullCommand .= " -{$key} {$value}";
        }

        if ($data !== null) {
            $fullCommand .= " -- {$data}";
        }

        $fullCommand .= "\0";

        try {
            $this->socket->write($fullCommand);
        } catch (Throwable $writeError) {
            throw new RuntimeException('Failed to write to stream: ' . $writeError->getMessage(), 0, $writeError);
        }

        try {
            $response = $this->readFrame();

            if (str_contains($response, '<error')) {
                $this->log("⚠️ Command '{$command}' returned error");
                if (preg_match('/<message>([^<]+)<\/message>/', $response, $matches) === 1) {
                    $this->log("  Error message: {$matches[1]}");
                }
            }

            return $response;
        } catch (Throwable $e) {
            $this->log("⚠️ Error receiving response for '{$command}' (ID: {$transactionId}): " . $e->getMessage());

            return '';
        }
    }

    public function readFrame(): string
    {
        $timeout = $this->readTimeout > 0 ? new TimeoutCancellation($this->readTimeout) : null;

        try {
            $lengthStr = '';
            while (true) {
                $char = $timeout instanceof TimeoutCancellation ? $this->socket->read($timeout, 1) : $this->socket->read(null, 1);
                if ($char === null || $char === '') {
                    throw new RuntimeException('Connection closed while reading length');
                }

                if ($char === "\0") {
                    break;
                }

                $lengthStr .= $char;
            }

            $length = (int) $lengthStr;
            if ($length <= 0) {
                throw new RuntimeException("Invalid response length: {$length}");
            }

            $response = '';
            $remaining = $length;
            while ($remaining > 0) {
                $chunk = $timeout instanceof TimeoutCancellation ? $this->socket->read($timeout, $remaining) : $this->socket->read(null, $remaining);
                if ($chunk === null || $chunk === '') {
                    throw new RuntimeException('Connection closed while reading response data');
                }

                $response .= $chunk;
                $remaining -= strlen($chunk);
            }

            $trailingNull = $timeout instanceof TimeoutCancellation ? $this->socket->read($timeout, 1) : $this->socket->read(null, 1);
            if ($trailingNull !== "\0") {
                $this->log('Warning: Expected trailing NULL byte, got: ' . bin2hex($trailingNull ?? ''));
            }

            return $response;
        } catch (Throwable $e) {
            throw new DebugSessionException('Failed to read DBGp frame: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function parseXmlResponse(string $xmlString, Closure|null $logger = null): SimpleXMLElement|null
    {
        if ($xmlString === '') {
            return null;
        }

        $useErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = simplexml_load_string($xmlString);
        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        if ($xml !== false) {
            return $xml;
        }

        if (! $logger instanceof Closure) {
            return null;
        }

        foreach ($errors as $error) {
            $logger('XML Parse Error: ' . trim($error->message));
        }

        return null;
    }

    private function log(string $message): void
    {
        if (! $this->logger instanceof Closure) {
            return;
        }

        ($this->logger)($message);
    }
}
