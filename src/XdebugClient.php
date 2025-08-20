<?php

namespace XdebugMcp;

use XdebugMcp\Exceptions\SocketException;
use XdebugMcp\Exceptions\XdebugConnectionException;

class XdebugClient
{
    private string $host;
    private int $port;
    private $socket = null;
    private int $transactionId = 1;
    private bool $connected = false;

    public function __construct(string $host = '127.0.0.1', int $port = 9004)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function connect(): array
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new SocketException('Failed to create socket: ' . socket_strerror(socket_last_error()));
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 30, 'usec' => 0]);

        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new SocketException('Failed to bind socket');
        }

        if (!socket_listen($this->socket, 1)) {
            throw new SocketException('Failed to listen on socket');
        }
        
        $clientSocket = socket_accept($this->socket);
        if ($clientSocket === false) {
            throw new SocketException('Failed to accept connection');
        }

        socket_close($this->socket);
        $this->socket = $clientSocket;

        $initData = $this->readResponse();
        $this->connected = true;
        
        return $this->parseXml($initData);
    }

    public function disconnect(): void
    {
        if ($this->socket && $this->connected) {
            $this->sendCommand('detach');
            socket_close($this->socket);
            $this->socket = null;
            $this->connected = false;
        }
    }

    public function setBreakpoint(string $filename, int $line, string $condition = ''): string
    {
        $this->ensureConnected();
        
        $args = [
            't' => 'line',
            'f' => $filename,
            'n' => $line
        ];
        
        if (!empty($condition)) {
            $args['--'] = base64_encode($condition);
        }

        $response = $this->sendCommand('breakpoint_set', $args);
        $parsed = $this->parseXml($response);
        
        $breakpointId = $parsed['@attributes']['id'] ?? 'unknown';
        
        return $breakpointId;
    }

    public function removeBreakpoint(string $breakpointId): void
    {
        $this->ensureConnected();
        
        $this->sendCommand('breakpoint_remove', ['d' => $breakpointId]);
    }

    public function stepInto(): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('step_into');
        return $this->parseXml($response);
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
            'max_depth'
        ];

        foreach ($commonFeatures as $feature) {
            try {
                $response = $this->sendCommand('feature_get', ['n' => $feature]);
                $parsed = $this->parseXml($response);
                $features[$feature] = $parsed['#text'] ?? $parsed['@attributes']['supported'] ?? null;
            } catch (\Exception $e) {
                // Skip failed features
            }
        }

        return $features;
    }

    private function ensureConnected(): void
    {
        if (!$this->connected || !$this->socket) {
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
            throw new SocketException('Failed to send command: ' . socket_strerror(socket_last_error($this->socket)));
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

        $length = (int)$lengthData;
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
        $xmlDoc = simplexml_load_string($xml);
        
        if ($xmlDoc === false) {
            $errors = libxml_get_errors();
            libxml_use_internal_errors($previousUseErrors);
            throw new XdebugConnectionException('Failed to parse XML response: ' . implode(', ', array_map(fn($e) => $e->message, $errors)));
        }
        
        libxml_use_internal_errors($previousUseErrors);
        
        return $this->xmlToArray($xmlDoc);
    }

    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $result = [];
        
        foreach ($xml->attributes() as $key => $value) {
            $result['@attributes'][$key] = (string)$value;
        }

        if ($xml->count() > 0) {
            foreach ($xml->children() as $child) {
                $childArray = $this->xmlToArray($child);
                $childName = $child->getName();
                
                if (isset($result[$childName])) {
                    if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                        $result[$childName] = [$result[$childName]];
                    }
                    $result[$childName][] = $childArray;
                } else {
                    $result[$childName] = $childArray;
                }
            }
        } else {
            $content = trim((string)$xml);
            if (!empty($content)) {
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
            'output_name' => ini_get('xdebug.profiler_output_name')
        ];
    }

    public function startCoverage(array $options = []): array
    {
        $flags = XDEBUG_CC_UNUSED;
        if (isset($options['track_unused']) && !$options['track_unused']) {
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
            'coverage_mode' => ini_get('xdebug.mode')
        ];
    }

    public function listBreakpoints(): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('breakpoint_list');
        return $this->parseXml($response);
    }

    public function setExceptionBreakpoint(string $exceptionName, string $state = 'all'): string
    {
        $this->ensureConnected();
        
        $args = [
            't' => 'exception',
            'x' => $exceptionName,
            's' => $state
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
            '--' => base64_encode($expression)
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
            '--' => base64_encode($value)
        ]);
        return $this->parseXml($response);
    }

    public function getProperty(string $name, int $context = 0): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('property_get', [
            'n' => $name,
            'c' => $context
        ]);
        return $this->parseXml($response);
    }

    public function setFeature(string $featureName, string $value): array
    {
        $this->ensureConnected();
        $response = $this->sendCommand('feature_set', [
            'n' => $featureName,
            'v' => $value
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
}