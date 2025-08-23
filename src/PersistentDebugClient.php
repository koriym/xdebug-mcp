<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_read;
use function socket_write;
use function str_starts_with;

use const AF_INET;
use const SOCK_STREAM;
use const SOL_TCP;

/**
 * Client for communicating with the persistent debug server
 */
class PersistentDebugClient
{
    private string $host = '127.0.0.1';
    private int $controlPort = 9005;

    public function sendCommand(string $command): string
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! $socket) {
            return 'ERROR: Failed to create socket';
        }

        $connected = socket_connect($socket, $this->host, $this->controlPort);
        if (! $connected) {
            socket_close($socket);

            return "ERROR: Cannot connect to persistent debug server on {$this->host}:{$this->controlPort}";
        }

        socket_write($socket, $command);
        $response = socket_read($socket, 4096);
        socket_close($socket);

        return $response ?: 'ERROR: No response from server';
    }

    public function setBreakpoint(string $filename, int $line): string
    {
        return $this->sendCommand("breakpoint $filename:$line");
    }

    public function continueExecution(): string
    {
        return $this->sendCommand('continue');
    }

    public function stepInto(): string
    {
        return $this->sendCommand('step');
    }

    public function getStatus(): string
    {
        return $this->sendCommand('status');
    }

    public function getVariables(): string
    {
        return $this->sendCommand('variables');
    }

    public function isServerRunning(): bool
    {
        $status = $this->getStatus();

        return ! str_starts_with($status, 'ERROR:');
    }
}
