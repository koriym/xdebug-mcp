<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\Process\Process;
use Amp\Socket\ResourceSocket;
use Amp\Socket\ServerSocket;
use Amp\Socket\SocketException;
use Amp\TimeoutCancellation;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

use function Amp\async;
use function Amp\delay;
use function Amp\Socket\listen;
use function array_map;
use function base64_decode;
use function basename;
use function bin2hex;
use function count;
use function date;
use function escapeshellarg;
use function explode;
use function file_exists;
use function fwrite;
use function implode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function realpath;
use function simplexml_load_string;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;
use const STDERR;

/**
 * Custom exceptions for better error handling
 */
class DebugSessionException extends RuntimeException
{
}
class ProcessException extends RuntimeException
{
}

/**
 * AMP-based Interactive Debugger
 * Streamlined for single-use debugging sessions
 */
final class DebugServer
{
    private const int MAX_STEPS = 20;
    private const int DEFAULT_DEBUG_PORT = 9004;
    private const float DEFAULT_CONNECTION_TIMEOUT = 15.0;
    private const float DEFAULT_EXECUTION_TIMEOUT = 60.0;  // Increased from 30s
    private const float DEFAULT_STEP_TIMEOUT = 10.0;  // Increased from 5s

    private DeferredFuture|null $listenerReady = null;
    private DeferredFuture|null $xdebugConnected = null;
    private ResourceSocket|null $xdebugSocket = null;
    private ServerSocket|null $server = null;
    private int $transactionId = 1;

    public function __construct(
        private string $targetScript,
        private int $debugPort = self::DEFAULT_DEBUG_PORT,
        private int|null $initialBreakpointLine = null,
        private array $options = [],
    ) {
        if (! file_exists($targetScript)) {
            throw new InvalidArgumentException("Script not found: {$targetScript}");
        }
    }

    /**
     * Start the debug session with 3 parallel tasks
     */
    public function __invoke(): void
    {
        $this->listenerReady = new DeferredFuture();
        $this->xdebugConnected = new DeferredFuture();

        echo "🚀 Starting AMP Interactive Debugger\n";
        echo "📁 Target: {$this->targetScript}\n";
        echo "🔌 Debug port: {$this->debugPort}\n";

        // 3 parallel tasks (Opus pattern)
        $tasks = [
            'listener' => async(fn () => $this->startXdebugListener()),
            'executor' => async(fn () => $this->executeTargetScript()),
            'handler' => async(fn () => $this->handleDebugSession()),
        ];

        try {
            Future\awaitAll($tasks);
        } catch (Throwable $e) {
            echo '❌ Debug session failed: ' . $e->getMessage() . "\n";

            throw new DebugSessionException('Debug session failed', 0, $e);
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Start Xdebug listener with timeout
     */
    private function startXdebugListener(): void
    {
        try {
            $this->server = listen("127.0.0.1:{$this->debugPort}");
            $this->log("📡 Listener ready on port {$this->debugPort}");
            $this->log('⏳ Waiting for Xdebug connection...');

            // Notify listener ready (Opus pattern)
            $this->listenerReady->complete(true);

            // Accept connection with timeout
            $connectionTimeout = $this->options['connectionTimeout'] ?? self::DEFAULT_CONNECTION_TIMEOUT;
            $cancellation = new TimeoutCancellation($connectionTimeout);
            $socket = $this->server->accept($cancellation);

            if (! $socket) {
                throw new SocketException(sprintf(
                    'No Xdebug connection within %.1f seconds',
                    $connectionTimeout,
                ));
            }

            $this->log('✅ Xdebug connected!');
            $this->xdebugSocket = $socket;

            // Close server socket after accepting connection
            $this->server->close();
            $this->server = null;

            // Read init packet
            $this->log('📨 Reading initial Xdebug packet...');
            try {
                $initData = $this->readDbgpFrame($socket);
                $this->log('📨 Session initialized: ' . substr($initData ?? 'NO DATA', 0, 100) . '...');
            } catch (Throwable $e) {
                $this->log('⚠️ Init packet read warning: ' . $e->getMessage());
                // Continue anyway
            }

            // Notify connection established
            $this->xdebugConnected->complete($socket);
        } catch (Throwable $e) {
            if ($this->listenerReady && ! $this->listenerReady->isComplete()) {
                $this->listenerReady->error($e);
            }

            if ($this->xdebugConnected && ! $this->xdebugConnected->isComplete()) {
                $this->xdebugConnected->error($e);
            }

            throw $e;
        }
    }

    /**
     * Execute target script with Xdebug enabled
     */
    private function executeTargetScript(): void
    {
        try {
            // Wait for listener ready
            $cancellation = new TimeoutCancellation(3.0);
            $this->listenerReady->getFuture()->await($cancellation);

            $cmd = sprintf(
                'XDEBUG_TRIGGER=1 php -dzend_extension=xdebug ' .
                '-dxdebug.mode=debug,trace ' .
                '-dxdebug.client_host=127.0.0.1 ' .
                '-dxdebug.client_port=%d ' .
                '-dxdebug.start_with_request=trigger %s',
                $this->debugPort,
                escapeshellarg($this->targetScript),
            );

            $this->log('🚀 Executing target script');
            $this->log("Command: {$cmd}");

            // Execute with AMP Process
            $process = Process::start($cmd);
            $this->log('📋 Process started, PID: ' . $process->getPid());

            // Wait for process completion with timeout
            $executionTimeout = $this->options['executionTimeout'] ?? self::DEFAULT_EXECUTION_TIMEOUT;
            $cancellation = new TimeoutCancellation($executionTimeout);
            $result = $process->join($cancellation);

            $stdout = $result->getStdout();
            $stderr = $result->getStderr();
            $exitCode = $result->getExitCode();

            if ($stdout) {
                echo "\n[SCRIPT OUTPUT]\n{$stdout}\n";
            }

            if ($stderr) {
                fwrite(STDERR, "\n[SCRIPT STDERR]\n{$stderr}\n");
            }

            if ($exitCode !== 0) {
                $this->log("⚠️ Script exited with code: {$exitCode}");
            }
        } catch (Throwable $e) {
            $this->log('❌ Script execution error: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle interactive debug session
     */
    private function handleDebugSession(): void
    {
        try {
            // Wait for connection
            $cancellation = new TimeoutCancellation(30.0);
            $socket = $this->xdebugConnected->getFuture()->await($cancellation);

            $this->log('🎯 Starting debug session');

            // Demo debug sequence
            $this->performDebugSequence();

            $this->log('✅ Debug session complete');
        } catch (Throwable $e) {
            $this->log('❌ Debug session error: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Perform step-by-step trace debugging sequence
     */
    private function performDebugSequence(): void
    {
        try {
            $this->log('🔍 Starting step trace debugging session');

            if ($this->initialBreakpointLine !== null) {
                // Set initial breakpoint
                $this->log("🔴 Setting initial breakpoint at line {$this->initialBreakpointLine}");
                $breakpointId = $this->setBreakpoint($this->targetScript, $this->initialBreakpointLine);

                if ($breakpointId !== 'error') {
                    $this->log("✅ Breakpoint set: ID {$breakpointId}");
                    $this->log('▶️ Continuing to first breakpoint...');
                } else {
                    $this->log('❌ Failed to set breakpoint');
                }

                // Continue to first breakpoint
                $this->continueExecution();
            } else {
                // No breakpoint - run once to start execution
                $this->log('▶️ No initial breakpoint. Running until first break or completion...');
                $response = $this->continueExecution();

                // Check if already completed
                if ($this->isExecutionComplete($response)) {
                    $this->log('✅ Script completed without breakpoints');

                    return;
                }
            }

            // Perform step trace through execution
            $this->performStepTrace();
        } catch (Throwable $e) {
            $this->log('Debug sequence error: ' . $e->getMessage());
        }
    }

    /**
     * Perform step-by-step tracing with variable inspection
     */
    private function performStepTrace(): void
    {
        $stepCount = 0;
        $maxSteps = $this->options['maxSteps'] ?? self::MAX_STEPS;

        $this->log('🚶 Starting step-by-step execution trace');

        while ($stepCount < $maxSteps) {
            $stepCount++;

            // Get current position and variables
            $this->log("\n--- Step {$stepCount} ---");

            $stackInfo = $this->getStack();
            if (empty($stackInfo)) {
                $this->log('⚠️ No stack info available, execution may have completed');
                break;
            }

            $this->displayStackInfo($stackInfo);

            $variables = $this->getVariables();
            $this->displayVariables($variables);

            // Step into next instruction
            $this->log('👣 Step into...');
            $stepResponse = $this->stepInto();

            // Check if execution completed
            if ($this->isExecutionComplete($stepResponse)) {
                $this->log("✅ Execution completed after {$stepCount} steps");
                break;
            }

            // Small delay for readability
            delay(0.1);
        }

        if ($stepCount >= $maxSteps) {
            $this->log("⚠️ Maximum steps ({$maxSteps}) reached, continuing to completion");
            $this->continueExecution();
        }
    }

    /**
     * Send a DBGp command and wait for the response
     */
    private function sendCommand(string $command, array $params = []): string
    {
        if (! $this->isConnected()) {
            throw new RuntimeException('No active Xdebug connection');
        }

        // Build full command with transaction ID
        $transactionId = $this->getNextTransactionId();
        $fullCommand = "{$command} -i {$transactionId}";

        // Add additional parameters
        foreach ($params as $key => $value) {
            $fullCommand .= " -{$key} {$value}";
        }

        $fullCommand .= "\0";

        $this->xdebugSocket->write($fullCommand);

        try {
            $response = $this->readDbgpFrame($this->xdebugSocket);

            // Check for error in response
            if (str_contains($response, '<error')) {
                $this->log("⚠️ Command '{$command}' returned error");
                if (preg_match('/<message>([^<]+)<\/message>/', $response, $matches)) {
                    $this->log("  Error message: {$matches[1]}");
                }
            }

            return $response;
        } catch (Throwable $e) {
            $this->log("⚠️ Error receiving response for '{$command}' (ID: {$transactionId}): " . $e->getMessage());

            return '';
        }
    }

    /**
     * Get next transaction ID atomically
     */
    private function getNextTransactionId(): int
    {
        return $this->transactionId++;
    }

    /**
     * Check if connected to Xdebug
     */
    private function isConnected(): bool
    {
        return $this->xdebugSocket !== null && ! $this->xdebugSocket->isClosed();
    }

    /**
     * Convert file path to properly encoded file:// URI
     */
    private function toFileUri(string $path): string
    {
        $real = realpath($path) ?: $path;

        // Handle Windows paths
        if (DIRECTORY_SEPARATOR === '\\') {
            $real = str_replace('\\', '/', $real);
            // Windows drive letter handling
            if (preg_match('/^([a-zA-Z]):\//', $real, $matches)) {
                $real = '/' . $matches[1] . ':' . substr($real, 2);
            }
        }

        // Encode path components
        $parts = explode('/', $real);
        $encoded = array_map('rawurlencode', $parts);

        return 'file://' . implode('/', $encoded);
    }

    /**
     * Set breakpoint
     */
    private function setBreakpoint(string $filename, int $line): string
    {
        $fileUri = $this->toFileUri($filename);
        $params = [
            't' => 'line',
            's' => 'enabled',
            'f' => $fileUri,
            'n' => $line,
        ];

        $response = $this->sendCommand('breakpoint_set', $params);

        // Check for error
        if (str_contains($response, '<error')) {
            return 'error';
        }

        // Parse breakpoint ID from response
        if (preg_match('/id="([^"]*)"/', $response, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    /**
     * Continue execution
     */
    private function continueExecution(): string
    {
        $response = $this->sendCommand('run');
        if ($response) {
            $this->log('✅ Continue response: ' . substr($response, 0, 100) . '...');
        }

        return $response;
    }

    /**
     * Step over
     */
    private function stepOver(): string
    {
        $response = $this->sendCommand('step_over');
        if ($response) {
            $this->log('✅ Step over completed');
        }

        return $response;
    }

    /**
     * Step into
     */
    private function stepInto(): string
    {
        $response = $this->sendCommand('step_into');
        if ($response) {
            $this->log('✅ Step into completed');
        }

        return $response;
    }

    /**
     * Get stack trace
     */
    private function getStack(): string
    {
        return $this->sendCommand('stack_get');
    }

    /**
     * Get variables (local context)
     */
    private function getVariables(): string
    {
        return $this->sendCommand('context_get', ['c' => '0']);  // 0 = locals
    }

    /**
     * Display stack information
     */
    private function displayStackInfo(string $xmlResponse): void
    {
        if (empty($xmlResponse)) {
            return;
        }

        $xml = $this->parseXmlResponse($xmlResponse);
        if (! $xml) {
            $this->log('⚠️ Failed to parse stack XML response');

            return;
        }

        $this->log('📍 Current execution position:');
        foreach ($xml->stack ?? [] as $frame) {
            $level = (string) $frame['level'];
            $type = (string) $frame['type'];
            $filename = (string) $frame['filename'];
            $lineno = (string) $frame['lineno'];
            $where = (string) $frame['where'];

            // Extract just filename from path
            $shortFilename = basename($filename);
            $this->log("  📂 Level {$level}: {$where} at {$shortFilename}:{$lineno}");
        }
    }

    /**
     * Display variables in readable format
     */
    private function displayVariables(string $xmlResponse): void
    {
        if (empty($xmlResponse)) {
            return;
        }

        $xml = $this->parseXmlResponse($xmlResponse);
        if (! $xml) {
            $this->log('⚠️ Failed to parse variables XML response');

            return;
        }

        $this->log('📊 Variables at current position:');

        // Handle both direct properties and nested in context element
        $properties = $xml->context->property ?? $xml->property ?? [];

        foreach ($properties as $property) {
            $name = (string) $property['name'];
            $type = (string) $property['type'];
            $value = isset($property['encoding']) && $property['encoding'] === 'base64'
                ? base64_decode((string) $property)
                : (string) $property;

            // Handle arrays and objects
            if ($type === 'array' || $type === 'object') {
                $childCount = count($property->property ?? []);
                $this->log("  📋 \${$name} ({$type}[{$childCount}]): <expandable>");
            } else {
                $this->log("  📋 \${$name} ({$type}): {$value}");
            }
        }
    }

    /**
     * Parse XML response safely without error suppression
     */
    private function parseXmlResponse(string $xmlString): SimpleXMLElement|null
    {
        if (empty($xmlString)) {
            return null;
        }

        // Save current libxml error handling state
        $useErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = simplexml_load_string($xmlString);

        // Get any errors that occurred
        $errors = libxml_get_errors();

        // Clear errors for next time
        libxml_clear_errors();

        // Restore original error handling
        libxml_use_internal_errors($useErrors);

        if ($xml === false) {
            // Log XML parsing errors if any
            foreach ($errors as $error) {
                $this->log('XML Parse Error: ' . trim($error->message));
            }

            return null;
        }

        return $xml;
    }

    /**
     * Check if execution has completed
     */
    private function isExecutionComplete(string $response): bool
    {
        if (empty($response)) {
            return true;  // Connection closed etc
        }

        // Check for DBGp response indicating completion (removed reason="ok")
        return str_contains($response, 'status="stopping"') ||
            str_contains($response, 'status="stopped"');
    }

    /**
     * Read DBGp frame - Fixed version with proper argument order
     */
    private function readDbgpFrame(ResourceSocket $socket): string
    {
        $timeout = new TimeoutCancellation($this->options['readTimeout'] ?? self::DEFAULT_STEP_TIMEOUT);

        try {
            // Read length header until NULL byte
            // FIXED: Correct argument order - Cancellation first, then length
            $lengthStr = '';
            while (true) {
                $char = $socket->read($timeout, 1);
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

            // Read the response data
            // FIXED: Correct argument order
            $response = '';
            $remaining = $length;
            while ($remaining > 0) {
                $chunk = $socket->read($timeout, $remaining);
                if ($chunk === null || $chunk === '') {
                    throw new RuntimeException('Connection closed while reading response data');
                }

                $response .= $chunk;
                $remaining -= strlen($chunk);
            }

            // Read the trailing NULL byte
            // FIXED: Correct argument order
            $trailingNull = $socket->read($timeout, 1);
            if ($trailingNull !== "\0") {
                $this->log('Warning: Expected trailing NULL byte, got: ' . bin2hex($trailingNull ?? ''));
            }

            return $response;
        } catch (Throwable $e) {
            throw new DebugSessionException('Failed to read DBGp frame: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Log message
     */
    private function log(string $message): void
    {
        $timestamp = date('H:i:s');
        echo "[{$timestamp}] {$message}\n";
    }

    /**
     * Cleanup resources
     */
    private function cleanup(): void
    {
        // Close server socket if still open
        if ($this->server) {
            try {
                $this->server->close();
            } catch (Throwable) {
                // Ignore cleanup errors
            }
        }

        // Close Xdebug connection
        if ($this->xdebugSocket && ! $this->xdebugSocket->isClosed()) {
            try {
                // Send detach command
                $this->sendCommand('detach');
            } catch (Throwable) {
                // Ignore cleanup errors
            }

            $this->xdebugSocket->close();
        }

        $this->log('🧹 Cleanup completed');
    }
}
