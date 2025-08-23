<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\Exceptions\SocketException;
use Koriym\XdebugMcp\Exceptions\XdebugConnectionException;
use SimpleXMLElement;
use Throwable;

use function array_map;
use function base64_encode;
use function date;
use function error_log;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function glob;
use function implode;
use function ini_get;
use function is_array;
use function json_decode;
use function json_encode;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function phpversion;
use function simplexml_load_string;
use function socket_accept;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_listen;
use function socket_read;
use function socket_recv;
use function socket_select;
use function socket_set_option;
use function socket_strerror;
use function socket_write;
use function spl_object_id;
use function str_contains;
use function strlen;
use function time;
use function trim;
use function unlink;

use const AF_INET;
use const JSON_PRETTY_PRINT;
use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;
use const MSG_PEEK;
use const SO_RCVTIMEO;
use const SO_REUSEADDR;
use const SOCK_STREAM;
use const SOL_SOCKET;
use const SOL_TCP;

class XdebugClient
{
    private const string GLOBAL_STATE_FILE = '/tmp/xdebug_session_global.json';

    private $socket = null;
    private int $transactionId = 1;
    private bool $connected = false;

    public function __construct(private string $host = '127.0.0.1', private int $port = 9004)
    {
        $this->loadGlobalState();
    }

    public function connect(): array
    {
        // Check for existing global session
        if ($this->isGlobalSessionAvailable()) {
            $sessionInfo = $this->reconnectToGlobalSession();
            if ($sessionInfo !== null) {
                return [
                    'status' => 'reopened',
                    'session' => $sessionInfo,
                    'host' => $this->host,
                    'port' => $this->port,
                ];
            }
        }

        // Create new connection
        $sessionInfo = $this->createNewConnection();
        $this->saveGlobalState($sessionInfo);

        return [
            'status' => 'opened',
            'session' => $sessionInfo,
            'host' => $this->host,
            'port' => $this->port,
        ];
    }

    private function createNewConnection(): array
    {
        error_log("[XdebugClient] Starting connection to {$this->host}:{$this->port}");

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new SocketException('Failed to create socket: ' . socket_strerror(socket_last_error()));
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);

        error_log("[XdebugClient] Binding to {$this->host}:{$this->port}");
        if (! socket_bind($this->socket, $this->host, $this->port)) {
            $error = socket_strerror(socket_last_error($this->socket));

            throw new SocketException("Failed to bind socket to {$this->host}:{$this->port}: {$error}");
        }

        error_log('[XdebugClient] Listening for connections...');
        if (! socket_listen($this->socket, 1)) {
            throw new SocketException('Failed to listen on socket');
        }

        error_log('[XdebugClient] Waiting for Xdebug connection...');
        $clientSocket = socket_accept($this->socket);
        if ($clientSocket === false) {
            $error = socket_strerror(socket_last_error($this->socket));

            throw new SocketException("Failed to accept connection (timeout or no connection): {$error}");
        }

        error_log('[XdebugClient] Xdebug connected! Reading initial data...');
        socket_close($this->socket);
        $this->socket = $clientSocket;

        $initData = $this->readResponse();
        $this->connected = true;

        error_log('[XdebugClient] Connection established successfully');

        // Save socket information for session persistence
        $this->saveSocketInfo();

        return $this->parseXml($initData);
    }

    public function disconnect(): void
    {
        if ($this->socket && $this->connected) {
            try {
                // Check if socket is still writable before sending detach
                if ($this->isSocketConnected()) {
                    $this->sendCommand('detach');
                }
            } catch (SocketException $e) {
                // Ignore socket errors during disconnect - connection may already be closed
                error_log('Disconnect warning: ' . $e->getMessage());
            }

            socket_close($this->socket);
            $this->socket = null;
            $this->connected = false;

            // Clear global state when explicitly disconnecting
            $this->clearGlobalState();
        }
    }

    private function isSocketConnected(): bool
    {
        if (! $this->socket) {
            return false;
        }

        // Check if socket is still connected by attempting to peek at data
        $read = [$this->socket];
        $write = null;
        $except = null;
        $result = socket_select($read, $write, $except, 0);

        // If socket_select returns 0, no activity (good)
        // If it returns 1 but socket_recv with PEEK returns 0, connection closed
        if ($result === 1) {
            $peek = socket_recv($this->socket, $buffer, 1, MSG_PEEK);

            return $peek !== 0; // 0 means connection closed
        }

        return $result !== false; // false means error
    }

    private function getAffordances(array $response): array
    {
        if (! $this->connected) {
            return ['reconnect'];
        }

        // Check debug session status from response
        $status = $response['@attributes']['status'] ?? 'unknown';

        switch ($status) {
            case 'break':
                // At breakpoint - can step, continue, inspect
                return [
                    'step_into',
                    'step_over',
                    'step_out',
                    'continue',
                    'get_variables',
                    'get_stack',
                    'eval_expression',
                ];

            case 'running':
                // Execution running - can only break or wait
                return ['break', 'wait'];

            case 'stopping':
            case 'stopped':
                // Session ended
                return ['reconnect'];

            default:
                // Unknown state - offer basic actions
                return [
                    'continue',
                    'get_variables',
                    'disconnect',
                ];
        }
    }

    public function getSessionStatus(): array
    {
        if (! $this->connected) {
            return [
                'status' => 'disconnected',
                'message' => 'No active debug session',
                '_affordances' => ['connect'],
            ];
        }

        if (! $this->isSocketConnected()) {
            $this->connected = false;

            return [
                'status' => 'disconnected',
                'message' => 'Debug session lost',
                '_affordances' => ['reconnect'],
            ];
        }

        return [
            'status' => 'connected',
            'message' => 'Debug session active',
            '_affordances' => ['step_into', 'step_over', 'continue', 'get_variables', 'disconnect'],
        ];
    }

    public function setBreakpoint(string $filename, int $line, string $condition = ''): string
    {
        $this->ensureConnected();

        $args = [
            't' => 'line',
            'f' => $filename,
            'n' => $line,
        ];

        if (! empty($condition)) {
            $args['--'] = base64_encode($condition);
        }

        $response = $this->sendCommand('breakpoint_set', $args);
        $parsed = $this->parseXml($response);

        return $parsed['@attributes']['id'] ?? 'unknown';
    }

    public function removeBreakpoint(string $breakpointId): void
    {
        $this->ensureConnected();

        $this->sendCommand('breakpoint_remove', ['d' => $breakpointId]);
    }

    public function stepInto(): array
    {
        $this->ensureConnected();

        try {
            $response = $this->sendCommand('step_into');
            $result = $this->parseXml($response);

            // Add affordances (available next actions)
            $result['_affordances'] = $this->getAffordances($result);

            return $result;
        } catch (SocketException $e) {
            if (str_contains($e->getMessage(), 'Connection lost')) {
                return [
                    'status' => 'disconnected',
                    'message' => 'Debug session ended',
                    '_affordances' => ['reconnect'],
                ];
            }

            throw $e;
        }
    }

    public function stepOver(): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('step_over');

        return $this->parseXml($response);
    }

    public function stepOut(): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('step_out');

        return $this->parseXml($response);
    }

    public function continue(): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('run');

        return $this->parseXml($response);
    }

    public function getStack(): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('stack_get');

        return $this->parseXml($response);
    }

    public function getVariables(int $context = 0): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('context_get', ['c' => $context]);

        return $this->parseXml($response);
    }

    public function eval(string $expression): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('eval', ['--' => base64_encode($expression)]);

        return $this->parseXml($response);
    }

    public function getStatus(): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('status');

        return $this->parseXml($response);
    }

    public function getFeatures(): array
    {
        $this->ensureConnected();
        $features = [];

        $commonFeatures = [
            'language_supports_threads',
            'language_name',
            'language_version',
            'encoding',
            'protocol_version',
            'supports_async',
            'data_encoding',
            'breakpoint_languages',
            'breakpoint_types',
            'multiple_sessions',
            'max_children',
            'max_data',
            'max_depth',
        ];

        foreach ($commonFeatures as $feature) {
            try {
                $response = $this->sendCommand('feature_get', ['n' => $feature]);
                $parsed = $this->parseXml($response);
                $features[$feature] = $parsed['#text'] ?? $parsed['@attributes']['supported'] ?? null;
            } catch (Throwable) {
                // Skip failed features
            }
        }

        return $features;
    }

    private function ensureConnected(): void
    {
        if (! $this->connected || ! $this->socket) {
            throw new XdebugConnectionException('Not connected to Xdebug session');
        }
    }

    private function sendCommand(string $command, array $args = []): string
    {
        $transactionId = $this->transactionId++;

        $commandString = "{$command} -i {$transactionId}";

        foreach ($args as $key => $value) {
            if ($key === '--') {
                $commandString .= " -- {$value}";
            } else {
                $commandString .= " -{$key} {$value}";
            }
        }

        $commandString .= "\0";

        $bytesWritten = socket_write($this->socket, $commandString, strlen($commandString));
        if ($bytesWritten === false) {
            $error = socket_last_error($this->socket);
            $errorMsg = socket_strerror($error);

            // If it's a broken pipe or connection reset, mark as disconnected
            if ($error === 32 || $error === 54) { // EPIPE or ECONNRESET
                $this->connected = false;

                throw new SocketException("Connection lost: $errorMsg");
            }

            throw new SocketException("Failed to send command: $errorMsg");
        }

        return $this->readResponse();
    }

    private function readResponse(): string
    {
        $lengthData = '';
        $char = '';

        while ($char !== "\0") {
            $result = socket_read($this->socket, 1);
            if ($result === false) {
                throw new SocketException('Failed to read from socket: ' . socket_strerror(socket_last_error($this->socket)));
            }

            $char = $result;
            if ($char !== "\0") {
                $lengthData .= $char;
            }
        }

        $length = (int) $lengthData;
        if ($length <= 0) {
            throw new XdebugConnectionException('Invalid response length: ' . $length);
        }

        $response = '';
        $bytesRead = 0;

        while ($bytesRead < $length) {
            $chunk = socket_read($this->socket, $length - $bytesRead);
            if ($chunk === false) {
                throw new SocketException('Failed to read response data: ' . socket_strerror(socket_last_error($this->socket)));
            }

            $response .= $chunk;
            $bytesRead += strlen($chunk);
        }

        socket_read($this->socket, 1);

        return $response;
    }

    private function parseXml(string $xml): array
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        $xmlDoc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        if ($xmlDoc === false) {
            $errors = libxml_get_errors();
            libxml_use_internal_errors($previousUseErrors);

            throw new XdebugConnectionException('Failed to parse XML response: ' . implode(', ', array_map(static fn ($e) => $e->message, $errors)));
        }

        libxml_use_internal_errors($previousUseErrors);

        return $this->xmlToArray($xmlDoc);
    }

    private function xmlToArray(SimpleXMLElement $xml): array
    {
        $result = [];

        foreach ($xml->attributes() as $key => $value) {
            $result['@attributes'][$key] = (string) $value;
        }

        if ($xml->count() > 0) {
            foreach ($xml->children() as $child) {
                $childArray = $this->xmlToArray($child);
                $childName = $child->getName();

                if (isset($result[$childName])) {
                    if (! is_array($result[$childName]) || ! isset($result[$childName][0])) {
                        $result[$childName] = [$result[$childName]];
                    }

                    $result[$childName][] = $childArray;
                } else {
                    $result[$childName] = $childArray;
                }
            }
        } else {
            $content = trim((string) $xml);
            if (! empty($content)) {
                $result['#text'] = $content;
            }
        }

        return $result;
    }

    public function startProfiling(string $outputFile = ''): array
    {
        $this->ensureConnected();
        $this->sendCommand('profiler_enable');

        return ['status' => 'profiling_started', 'output_file' => $outputFile];
    }

    public function stopProfiling(): array
    {
        $this->ensureConnected();
        $this->sendCommand('profiler_disable');

        return ['status' => 'profiling_stopped'];
    }

    public function getProfileInfo(): array
    {
        return [
            'profiler_status' => 'active',
            'output_dir' => ini_get('xdebug.output_dir'),
            'output_name' => ini_get('xdebug.profiler_output_name'),
        ];
    }

    public function startCoverage(array $options = []): array
    {
        $flags = XDEBUG_CC_UNUSED;
        if (isset($options['track_unused']) && ! $options['track_unused']) {
            $flags = 0;
        }

        if (function_exists('xdebug_start_code_coverage')) {
            xdebug_start_code_coverage($flags);
        }

        return ['status' => 'coverage_started', 'flags' => $flags];
    }

    public function stopCoverage(): array
    {
        if (function_exists('xdebug_stop_code_coverage')) {
            xdebug_stop_code_coverage();
        }

        return ['status' => 'coverage_stopped'];
    }

    public function getCoverage(): array
    {
        if (function_exists('xdebug_get_code_coverage')) {
            return xdebug_get_code_coverage();
        }

        return [];
    }

    public function getCoverageInfo(): array
    {
        return [
            'coverage_enabled' => function_exists('xdebug_start_code_coverage'),
            'xdebug_version' => phpversion('xdebug'),
            'coverage_mode' => ini_get('xdebug.mode'),
        ];
    }

    public function listBreakpoints(): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('breakpoint_list');

        return $this->parseXml($response);
    }

    public function setExceptionBreakpoint(string $exceptionName, string $state = 'enabled'): string
    {
        $this->ensureConnected();

        $args = [
            't' => 'exception',
            'x' => $exceptionName,
            's' => $state,
        ];

        $response = $this->sendCommand('breakpoint_set', $args);
        $parsed = $this->parseXml($response);

        return $parsed['@attributes']['id'] ?? 'unknown';
    }

    public function setWatchBreakpoint(string $expression, string $type = 'write'): string
    {
        $this->ensureConnected();

        $args = [
            't' => 'watch',
            '--' => base64_encode($expression),
        ];

        $response = $this->sendCommand('breakpoint_set', $args);
        $parsed = $this->parseXml($response);

        return $parsed['@attributes']['id'] ?? 'unknown';
    }

    public function getBreakpointInfo(string $breakpointId): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('breakpoint_get', ['d' => $breakpointId]);

        return $this->parseXml($response);
    }

    public function setProperty(string $name, string $value, int $context = 0): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('property_set', [
            'n' => $name,
            'c' => $context,
            '--' => base64_encode($value),
        ]);

        return $this->parseXml($response);
    }

    public function getProperty(string $name, int $context = 0): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('property_get', [
            'n' => $name,
            'c' => $context,
        ]);

        return $this->parseXml($response);
    }

    public function setFeature(string $featureName, string $value): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('feature_set', [
            'n' => $featureName,
            'v' => $value,
        ]);

        return $this->parseXml($response);
    }

    public function getFeature(string $featureName): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('feature_get', ['n' => $featureName]);

        return $this->parseXml($response);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function loadGlobalState(): void
    {
        if (! file_exists(self::GLOBAL_STATE_FILE)) {
            return;
        }

        try {
            $stateData = file_get_contents(self::GLOBAL_STATE_FILE);
            $state = json_decode($stateData, true);

            if ($state && $this->isValidGlobalState($state)) {
                $this->host = $state['host'];
                $this->port = $state['port'];
            }
        } catch (Throwable) {
            // Ignore invalid state file
        }
    }

    private function saveGlobalState(array $sessionInfo): void
    {
        $state = [
            'host' => $this->host,
            'port' => $this->port,
            'connected' => true,
            'last_activity' => time(),
            'session_info' => $sessionInfo,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        file_put_contents(self::GLOBAL_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function isGlobalSessionAvailable(): bool
    {
        if (! file_exists(self::GLOBAL_STATE_FILE)) {
            return false;
        }

        try {
            $stateData = file_get_contents(self::GLOBAL_STATE_FILE);
            $state = json_decode($stateData, true);

            return $this->isValidGlobalState($state) && $this->isSessionAlive($state);
        } catch (Throwable) {
            return false;
        }
    }

    private function isValidGlobalState(mixed $state): bool
    {
        return is_array($state)
            && isset($state['host'], $state['port'], $state['connected'], $state['last_activity'])
            && $state['connected'] === true;
    }

    private function isSessionAlive(array $state): bool
    {
        // Check if session is too old (5 minutes timeout)
        $maxAge = 300; // 5 minutes

        return time() - $state['last_activity'] < $maxAge;
    }

    private function reconnectToGlobalSession(): array|null
    {
        try {
            $stateData = file_get_contents(self::GLOBAL_STATE_FILE);
            $state = json_decode($stateData, true);

            if (! $this->isValidGlobalState($state)) {
                return null;
            }

            // Update host/port from saved state
            $this->host = $state['host'];
            $this->port = $state['port'];

            // Validate the existing socket connection is still active
     private function validateSocketConnection(array $state): bool
     {
         // Check if we have socket information in the state
         if (! isset($state['socket_info'])) {
             return false;
         }

         // Use the same timeout as isSessionAlive() for consistency
         return $this->isSessionAlive($state);
     }

            // Socket is valid, mark as connected
            $this->connected = true;

            // Update last activity
            $state['last_activity'] = time();
            file_put_contents(self::GLOBAL_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));

            return $state['session_info'];
        } catch (Throwable) {
            // If reconnection fails, remove stale state
            if (file_exists(self::GLOBAL_STATE_FILE)) {
                unlink(self::GLOBAL_STATE_FILE);
            }

            return null;
        }
    }

    public function clearGlobalState(): void
    {
        if (file_exists(self::GLOBAL_STATE_FILE)) {
            unlink(self::GLOBAL_STATE_FILE);
        }
    }

    public function cleanupExpiredSessions(): int
    {
        $cleaned = 0;

        // Clean up main global state if expired
        if (file_exists(self::GLOBAL_STATE_FILE)) {
            try {
                $stateData = file_get_contents(self::GLOBAL_STATE_FILE);
                $state = json_decode($stateData, true);

                if (! $this->isValidGlobalState($state) || ! $this->isSessionAlive($state)) {
                    unlink(self::GLOBAL_STATE_FILE);
                    $cleaned++;
                }
            } catch (Throwable) {
                // Invalid file, remove it
                unlink(self::GLOBAL_STATE_FILE);
                $cleaned++;
            }
        }

        // Clean up other temporary session files (pattern: /tmp/xdebug_session_*.json)
        $tempFiles = glob('/tmp/xdebug_session_*.json');
        foreach ($tempFiles as $file) {
            try {
                $data = file_get_contents($file);
                $sessionData = json_decode($data, true);

                if (! $sessionData || ! isset($sessionData['last_activity'])) {
                    unlink($file);
                    $cleaned++;
                    continue;
                }

                // Remove sessions older than 5 minutes
                if (time() - $sessionData['last_activity'] > 300) {
                    unlink($file);
                    $cleaned++;
                }
            } catch (Throwable) {
                // Invalid file, remove it
                if (file_exists($file)) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }

    private function validateSocketConnection(array $state): bool
    {
        // Check if we have socket information in the state
        if (! isset($state['socket_info'])) {
            return false;
        }

        // For now, use a simple timeout check
        // In a complete implementation, we would try to ping the socket
        $maxInactivity = 60; // 1 minute of inactivity before assuming socket is stale
        $lastActivity = $state['last_activity'] ?? 0;

        return time() - $lastActivity < $maxInactivity;
    }

    private function saveSocketInfo(): void
    {
        if (! $this->socket || ! $this->connected) {
            return;
        }

        // Get existing state or create new one
        $state = [];
        if (file_exists(self::GLOBAL_STATE_FILE)) {
            $stateData = file_get_contents(self::GLOBAL_STATE_FILE);
            $state = json_decode($stateData, true) ?: [];
        }

        // Add socket information
        $state['socket_info'] = [
            'resource_id' => 'socket_' . spl_object_id($this->socket),
            'connected_at' => time(),
            'transaction_id' => $this->transactionId,
        ];
        $state['last_activity'] = time();

        file_put_contents(self::GLOBAL_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
    }
}
