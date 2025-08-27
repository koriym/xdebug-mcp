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
use Koriym\XdebugMcp\Exceptions\DebugSessionException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

use function Amp\async;
use function Amp\Socket\listen;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function base64_decode;
use function base64_encode;
use function basename;
use function bin2hex;
use function count;
use function date;
use function escapeshellarg;
use function explode;
use function fclose;
use function fgets;
use function file;
use function file_exists;
use function filemtime;
use function filesize;
use function flush;
use function fopen;
use function fwrite;
use function glob;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function json_encode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function ltrim;
use function preg_match;
use function realpath;
use function round;
use function shell_exec;
use function simplexml_load_string;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;
use function usort;

use const DIRECTORY_SEPARATOR;
use const FILE_IGNORE_NEW_LINES;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const STDERR;

/**
 * AMP-based Interactive Debugger
 * Streamlined for single-use debugging sessions
 *
 * @see https://xdebug.org/docs/step_debug
 */
final class DebugServer
{
    private const float DEFAULT_CONNECTION_TIMEOUT = 30.0;  // Initial connection only
    private const float DEFAULT_EXECUTION_TIMEOUT = 3600.0;  // 1 hour for long debugging sessions
    private const float DEFAULT_STEP_TIMEOUT = 0.0;  // No timeout for interactive debugging

    private DeferredFuture|null $listenerReady = null;
    private DeferredFuture|null $xdebugConnected = null;
    private ResourceSocket|null $xdebugSocket = null;
    private ServerSocket|null $server = null;
    private Process|null $process = null;
    private int $transactionId = 1;
    private string|null $traceFile = null;

    public function __construct(
        private string $targetScript,
        private int $debugPort,
        private int|null $initialBreakpointLine = null,
        private array $options = [],
        private bool $jsonMode = false,
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

        $this->log("🚀 Starting AMP Interactive Debugger");
        $this->log("📁 Target: {$this->targetScript}");
        $this->log("🔌 Debug port: {$this->debugPort}");

        // 3 parallel tasks (Opus pattern)
        $tasks = [
            'listener' => async(fn () => $this->startXdebugListener()),
            'executor' => async(fn () => $this->executeTargetScript()),
            'handler' => async(fn () => $this->handleDebugSession()),
        ];

        try {
            Future\awaitAll($tasks);
        } catch (Throwable $e) {
            $this->log('❌ Debug session failed: ' . $e->getMessage());

            throw new DebugSessionException('Debug session failed', 0, $e);
        } finally {
            $this->cleanup();
            // Force exit after cleanup to prevent AMP event loop from continuing
            exit(0);
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

            // Check if custom command is provided
            if (isset($this->options['command']) && ! empty($this->options['command'])) {
                $command = $this->options['command'];
                // Insert Xdebug parameters into the php command
                if ($command[0] === 'php') {
                    $scriptName = basename($this->targetScript, '.php');
                    $traceFile = '/tmp/trace-%t-' . $scriptName . '.xt';
                    $cmd = sprintf(
                        'XDEBUG_TRIGGER=1 php -dzend_extension=xdebug ' .
                        '-dxdebug.mode=debug,trace ' .
                        '-dxdebug.client_host=127.0.0.1 ' .
                        '-dxdebug.client_port=%d ' .
                        '-dxdebug.start_with_request=trigger ' .
                        '-dxdebug.trace_output_name=trace-%%s.xt ' .
                        '-dxdebug.trace_format=1 ' .
                        '-dxdebug.log=/tmp/xdebug.log ' .
                        '-dxdebug.log_level=7 ' .
                        '-dxdebug.connect_timeout_ms=5000 ' .
                        '%s',
                        $this->debugPort,
                        implode(' ', array_map('escapeshellarg', array_slice($command, 1))),
                    );
                    $this->traceFile = $traceFile;
                } else {
                    throw new RuntimeException("Custom command must start with 'php'");
                }
            } else {
                // Default: simple script execution
                $scriptName = basename($this->targetScript, '.php');
                $traceFile = '/tmp/trace-%t-' . $scriptName . '.xt';
                $cmd = sprintf(
                    'XDEBUG_TRIGGER=1 php -dzend_extension=xdebug ' .
                    '-dxdebug.mode=debug,trace ' .
                    '-dxdebug.client_host=127.0.0.1 ' .
                    '-dxdebug.client_port=%d ' .
                    '-dxdebug.start_with_request=trigger ' .
                    '-dxdebug.trace_output_name=trace-%%s.xt ' .
                    '-dxdebug.trace_format=1 ' .
                    '-dxdebug.log=/tmp/xdebug.log ' .
                    '-dxdebug.log_level=7 ' .
                    '-dxdebug.connect_timeout_ms=5000 ' .
                    '%s',
                    $this->debugPort,
                    escapeshellarg($this->targetScript),
                );
                $this->traceFile = $traceFile;
            }

            $this->log('🚀 Executing target script');
            $this->log("Command: {$cmd}");

            // Execute with AMP Process
            $this->process = Process::start($cmd);
            $this->log('📋 Process started, PID: ' . $this->process->getPid());

            // Skip process waiting for interactive debugging to avoid connection issues
            if (($this->options['traceOnly'] ?? false) || ! $this->isConnected()) {
                // Wait for process completion with timeout
                $executionTimeout = $this->options['executionTimeout'] ?? self::DEFAULT_EXECUTION_TIMEOUT;
                $cancellation = new TimeoutCancellation($executionTimeout);
                $result = $this->process->join($cancellation);
            } else {
                // For interactive debugging, don't wait for process completion
                $result = 0; // Mock exit code
            }

            // Handle case where join() returns int instead of ProcessResult
            if (is_int($result)) {
                $exitCode = $result;
                $stdout = '';
                $stderr = '';
            } else {
                $stdout = $result->getStdout();
                $stderr = $result->getStderr();
                $exitCode = $result->getExitCode();
            }

            if ($stdout) {
                $this->log("\n[SCRIPT OUTPUT]\n{$stdout}");
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

            // Set up conditional breakpoints if provided
            $this->setupConditionalBreakpoints();

            // Check if exit-on-break mode
            if ($this->options['traceOnly'] ?? false) {
                $this->processMultipleBreakpoints();
                exit(0);
            }

            // Interactive mode: Use step_into to stop at first executable line
            $this->log('🚶 Starting with step_into to stop at first executable line...');
            $response = $this->stepInto();

            // Check if already completed (empty script?)
            if ($this->isExecutionComplete($response)) {
                $this->log('✅ Script completed immediately');

                return;
            }

            $this->log('⏸️ Stopped at first executable line');

            // Start interactive debugging session
            $this->startInteractiveSession();
        } catch (Throwable $e) {
            $this->log('Debug sequence error: ' . $e->getMessage());
        }
    }

    /**
     * Legacy: Perform step-by-step tracing with variable inspection (DISABLED)
     * This method has been replaced by startInteractiveSession() for true interactive debugging
     */
    /*
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
    */

    /**
     * Send a DBGp command and wait for the response
     */
    private function sendCommand(string $command, array $params = []): string
    {
        if (! $this->isConnected()) {
            throw new RuntimeException('No active Xdebug connection');
        }

        // Additional check for connection readability
        if (! $this->xdebugSocket->isReadable()) {
            throw new RuntimeException('Xdebug connection lost or not readable');
        }

        // Build full command with transaction ID
        $transactionId = $this->getNextTransactionId();
        $fullCommand = "{$command} -i {$transactionId}";

        // Add additional parameters
        foreach ($params as $key => $value) {
            $fullCommand .= " -{$key} {$value}";
        }

        $fullCommand .= "\0";

        try {
            $this->xdebugSocket->write($fullCommand);
        } catch (Throwable $writeError) {
            throw new RuntimeException('Failed to write to stream: ' . $writeError->getMessage(), 0, $writeError);
        }

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
        return $this->xdebugSocket !== null
            && ! $this->xdebugSocket->isClosed()
            && $this->xdebugSocket->isWritable();
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
            if (preg_match('/^([a-zA-Z]):\/(.*)$/', $real, $m)) {
                $drive = strtoupper($m[1]) . ':/';
                $rest = $m[2];
                $parts = explode('/', $rest);
                $encoded = array_map('rawurlencode', $parts);

                return 'file:///' . $drive . implode('/', $encoded);
            }
        }

        // POSIX: encode each segment
        $parts = explode('/', ltrim($real, '/'));
        $encoded = array_map('rawurlencode', $parts);

        return 'file:///' . implode('/', $encoded);
    }

    /**
     * Set breakpoint
     */
    private function setBreakpoint(string $filename, int $line, string|null $condition = null): string
    {
        $fileUri = $this->toFileUri($filename);
        $params = [
            't' => 'line',
            's' => 'enabled',
            'f' => $fileUri,
            'n' => $line,
        ];

        // Add condition if provided
        if ($condition !== null && trim($condition) !== '') {
            $params['o'] = trim($condition);
        }

        $response = $this->sendCommand('breakpoint_set', $params);

        // Check for error
        if (str_contains($response, '<error')) {
            // Log the error for debugging
            $this->log('⚠️ Breakpoint error: ' . $response);

            return 'error';
        }

        // Parse breakpoint ID from response
        if (preg_match('/id="([^"]*)"/', $response, $matches)) {
            $breakpointId = $matches[1];
            $conditionText = $condition ? " (condition: {$condition})" : '';
            $this->log("✅ Breakpoint set: {$filename}:{$line}{$conditionText} [ID: {$breakpointId}]");

            return $breakpointId;
        }

        return 'unknown';
    }

    /**
     * Set up conditional breakpoints from options
     */
    private function setupConditionalBreakpoints(): void
    {
        if (! isset($this->options['breakpoints']) || ! is_array($this->options['breakpoints'])) {
            return;
        }

        foreach ($this->options['breakpoints'] as $breakpoint) {
            if (! isset($breakpoint['file'], $breakpoint['line'])) {
                continue;
            }

            $file = $breakpoint['file'];
            $line = (int) $breakpoint['line'];
            $condition = $breakpoint['condition'] ?? null;

            // Set the breakpoint with condition
            $breakpointId = $this->setBreakpoint($file, $line, $condition);

            if ($breakpointId === 'error') {
                $this->log("❌ Failed to set breakpoint: {$file}:{$line}");
            }
        }
    }

    /**
     * Continue execution
     */
    private function continueExecution(): string
    {
        $this->log('🔄 Sending continue command...');
        $response = $this->sendCommand('run');

        if ($response) {
            $this->log('✅ Continue response: ' . substr($response, 0, 100) . '...');
        } else {
            $this->log('⚠️ Continue response was empty');
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
        try {
            $response = $this->sendCommand('step_into');
            if ($response) {
                $this->log('✅ Step into completed');
            }

            return $response;
        } catch (Throwable $e) {
            // Re-throw the exception to be handled by caller
            throw $e;
        }
    }

    /**
     * Step out
     */
    private function stepOut(): string
    {
        $response = $this->sendCommand('step_out');
        if ($response) {
            $this->log('✅ Step out completed');
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
     * Evaluate expression
     */
    private function evaluateExpression(string $expression): string
    {
        // For simple variables, use property_get instead of eval
        if (preg_match('/^\$\w+$/', $expression)) {
            return $this->sendCommand('property_get', ['n' => $expression]);
        }

        // For array access like $items[0], $data['key'], $items[0][name], or $items[0][1] (multi-dimensional), use property_get
        if (preg_match('/^\$\w+(\[[\w\'"]+\])+$/', $expression)) {
            return $this->sendCommand('property_get', ['n' => $expression]);
        }

        // For complex expressions, use eval with proper encoding
        $encoded = base64_encode($expression);

        return $this->sendCommand('eval', ['--' => $encoded]);
    }

    /**
     * Check if response indicates breakpoint hit
     */
    private function didBreak(string $resp): bool
    {
        // <response ... status="break" ...> のときに true
        return $resp !== '' && str_contains($resp, 'status="break"');
    }

    /**
     * Finalize trace when breakpoint is hit
     */
    private function finalizeTraceOnBreak(): string|null
    {
        // いま開いているトレースを閉じて、ファイル名を返す
        $code = base64_encode('return function_exists("xdebug_stop_trace") ? xdebug_stop_trace() : null;');
        $resp = $this->sendCommand('eval', ['--' => $code]);
        $xml  = $this->parseXmlResponse($resp);
        if (! $xml || ! isset($xml->property)) {
            return null;
        }

        $prop   = $xml->property;
        $value  = (string) $prop;
        $value  = (string) ($prop['encoding'] ?? '') === 'base64' ? base64_decode($value) : $value;
        $path   = trim($value);

        // すぐ次の区間のために再開しておくと連続収集が楽
        $restart = base64_encode('return function_exists("xdebug_start_trace") ? xdebug_start_trace() : null;');
        $this->sendCommand('eval', ['--' => $restart]);

        return $path !== '' ? $path : null;
    }

    /**
     * Start interactive debugging session
     */
    private function startInteractiveSession(): void
    {
        $this->log('🎮 Starting interactive debugging session');
        $this->log('Available commands: s(tep), o(ver), out, c(ontinue), p <var>, bt, l(ist), claude, q(uit)');

        while (true) {
            $this->displayPrompt();
            $input = $this->readUserInputWithTimeout();

            if ($input === null) {
                $this->log('❌ Failed to read user input, exiting');
                break;
            }

            $command = trim($input);
            if (empty($command)) {
                continue;
            }

            if ($this->executeUserCommand($command)) {
                $this->outputTraceFile();
                exit(0);
            }
        }
    }

    /**
     * Display debugger prompt
     */
    private function displayPrompt(): void
    {
        if ($this->jsonMode) {
            return;
        }
        echo '(Xdebug) ';
        flush();
    }

    /**
     * Read user input from stdin (blocking)
     */
    private function readUserInputWithTimeout(): string|null
    {
        // Use blocking read from STDIN - let the user interact normally
        $handle = fopen('php://stdin', 'r');
        if ($handle === false) {
            return null;
        }

        $input = fgets($handle);
        fclose($handle);

        return $input !== false ? $input : null;
    }

    /**
     * Check if target process is still running
     */
    private function isTargetProcessRunning(): bool
    {
        return $this->process !== null && $this->process->isRunning();
    }

    /**
     * Output trace file information when session ends
     */
    private function outputTraceFile(): void
    {
        // Look for trace files with various patterns
        $patterns = [
            '/tmp/trace.*.xt',  // Default Xdebug pattern
            '/tmp/trace-*-' . basename($this->targetScript, '.php') . '.xt',  // Our custom pattern
            '/tmp/trace-*.xt',  // Any trace file pattern
        ];

        $allTraceFiles = [];
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if ($files) {
                $allTraceFiles = array_merge($allTraceFiles, $files);
            }
        }

        if (! empty($allTraceFiles)) {
            // Remove duplicates and sort by modification time, get the most recent
            $allTraceFiles = array_unique($allTraceFiles);
            usort($allTraceFiles, static function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $latestTrace = $allTraceFiles[0];

            // For exit-on-break mode: simple message with filename and size for AI analysis
            if ($this->options['traceOnly'] ?? false) {
                if (file_exists($latestTrace)) {
                    $lines = count(file($latestTrace, FILE_IGNORE_NEW_LINES));
                    $size = filesize($latestTrace);
                    $sizeKB = round($size / 1024, 1);

                    if ($this->options['jsonOutput'] ?? false) {
                        // JSON output for AI consumption
                        $commandParts = $this->options['command'] ?? ['php', $this->targetScript];
                        $escapedCommandParts = array_map('escapeshellarg', $commandParts);
                        $command = implode(' ', $escapedCommandParts);

                        echo json_encode([
                            'trace_file' => $latestTrace,
                            'lines' => $lines,
                            'size' => $sizeKB,
                            'command' => $command,
                        ]) . "\n";
                    } else {
                        $this->log("📊 Trace file generated up to conditional breakpoint: {$latestTrace} ({$lines} lines, {$sizeKB}KB)");
                    }
                } else {
                    if ($this->options['jsonOutput'] ?? false) {
                        echo json_encode([
                            'trace_file' => $latestTrace,
                            'lines' => 0,
                            'size' => 0,
                            'command' => implode(' ', $this->options['command'] ?? ['php', $this->targetScript]),
                        ]) . "\n";
                    } else {
                        $this->log("📊 Trace file generated up to conditional breakpoint: {$latestTrace}");
                    }
                }
            } else {
                // For interactive mode: show detailed info
                $this->log("📈 Trace file available: {$latestTrace}");
                if (file_exists($latestTrace)) {
                    $lines = count(file($latestTrace, FILE_IGNORE_NEW_LINES));
                    $size = filesize($latestTrace);
                    $this->log("📊 Trace contains {$lines} lines ({$size} bytes)");
                }

                $this->log('✅ Debug session complete');
            }
        } else {
            if (! ($this->options['traceOnly'] ?? false)) {
                $this->log('⚠️ No trace file found');
                $this->log('💡 Trace files are typically saved as /tmp/trace.*.xt');
                $this->log('✅ Debug session complete');
            }
        }
    }

    /**
     * Execute user command and return true if session should exit
     */
    private function executeUserCommand(string $command): bool
    {
        $parts = explode(' ', $command, 2);
        $cmd = strtolower($parts[0]);
        $args = $parts[1] ?? '';

        switch ($cmd) {
            case 's':
            case 'step':
                return $this->handleStepCommand();

            case 'o':
            case 'over':
                return $this->handleStepOverCommand();

            case 'out':
                return $this->handleStepOutCommand();

            case 'c':
            case 'continue':
                return $this->handleContinueCommand();

            case 'p':
            case 'print':
                $this->handlePrintCommand($args);

                return false;

            case 'bt':
            case 'backtrace':
                $this->handleBacktraceCommand();

                return false;

            case 'l':
            case 'list':
                $this->handleListCommand();

                return false;

            case 'q':
            case 'quit':
                $this->log('🔚 Exiting debugger');

                return true;

            case 'claude':
                $this->handleClaudeCommand($args);

                return false;

            case 'h':
            case 'help':
                $this->displayHelp();

                return false;

            default:
                $this->log("❌ Unknown command: {$cmd}. Type 'h' for help.");

                return false;
        }
    }

    /**
     * Handle step command
     */
    private function handleStepCommand(): bool
    {
        $this->log('👣 Stepping into next instruction...');

        try {
            $response = $this->stepInto();

            // Note: Trace finalization disabled as it causes eval parse errors
            // Trace is already enabled via Xdebug configuration and continues throughout debug session

            if ($this->isExecutionComplete($response)) {
                $this->log('✅ Execution completed');

                return true;
            }

            // Display current state after step
            $this->displayCurrentState();

            return false;
        } catch (Throwable $e) {
            $this->log('⚠️ Step command encountered error: ' . $e->getMessage());

            // Check if this is a connection error (broken pipe, connection closed, etc.)
            if (
                str_contains($e->getMessage(), 'Broken pipe') ||
                str_contains($e->getMessage(), 'Connection closed') ||
                str_contains($e->getMessage(), 'Failed to write to stream')
            ) {
                $this->log('🔚 Debug session ended due to connection issues');

                return true; // End session gracefully
            }

            // Try to recover only for non-connection errors
            if ($this->isConnected()) {
                $this->log('🔄 Connection still active, trying continue to next executable line...');
                try {
                    $response = $this->continueExecution();
                    if ($this->isExecutionComplete($response)) {
                        $this->log('✅ Execution completed');

                        return true;
                    }

                    $this->displayCurrentState();

                    return false;
                } catch (Throwable $retryException) {
                    $this->log('❌ Recovery failed: ' . $retryException->getMessage());
                }
            }

            $this->log('🔚 Debug session ended due to connection issues');

            return true;
        }
    }

    /**
     * Handle step over command
     */
    private function handleStepOverCommand(): bool
    {
        $this->log('👣 Stepping over current instruction...');

        try {
            $response = $this->stepOver();

            if ($this->isExecutionComplete($response)) {
                $this->log('✅ Execution completed');

                return true;
            }

            // Display current state after step
            $this->displayCurrentState();

            return false;
        } catch (Throwable $e) {
            $this->log('⚠️ Step over command encountered error: ' . $e->getMessage());

            // Check if this is a connection error
            if (
                str_contains($e->getMessage(), 'Broken pipe') ||
                str_contains($e->getMessage(), 'Connection closed') ||
                str_contains($e->getMessage(), 'Failed to write to stream')
            ) {
                $this->log('🔚 Debug session ended due to connection issues');

                return true;
            }

            // Try to recover only for non-connection errors
            if ($this->isConnected()) {
                $this->log('🔄 Connection still active, trying continue to next executable line...');
                try {
                    $response = $this->continueExecution();
                    if ($this->isExecutionComplete($response)) {
                        $this->log('✅ Execution completed');

                        return true;
                    }

                    $this->displayCurrentState();

                    return false;
                } catch (Throwable $retryException) {
                    $this->log('❌ Recovery failed: ' . $retryException->getMessage());
                }
            }

            $this->log('🔚 Debug session ended due to connection issues');

            return true;
        }
    }

    /**
     * Handle step out command
     */
    private function handleStepOutCommand(): bool
    {
        $this->log('👣 Stepping out of current function...');

        try {
            $response = $this->stepOut();

            if ($this->isExecutionComplete($response)) {
                $this->log('✅ Execution completed');

                return true;
            }

            // Display current state after step out
            $this->displayCurrentState();

            return false;
        } catch (Throwable $e) {
            $this->log('⚠️ Step out command encountered error: ' . $e->getMessage());

            // Check if this is a connection error
            if (
                str_contains($e->getMessage(), 'Broken pipe') ||
                str_contains($e->getMessage(), 'Connection closed') ||
                str_contains($e->getMessage(), 'Failed to write to stream')
            ) {
                $this->log('🔚 Debug session ended due to connection issues');

                return true;
            }

            // Try to recover only for non-connection errors
            if ($this->isConnected()) {
                $this->log('🔄 Connection still active, trying continue to next executable line...');
                try {
                    $response = $this->continueExecution();
                    if ($this->isExecutionComplete($response)) {
                        $this->log('✅ Execution completed');

                        return true;
                    }

                    $this->displayCurrentState();

                    return false;
                } catch (Throwable $retryException) {
                    $this->log('❌ Recovery failed: ' . $retryException->getMessage());
                }
            }

            $this->log('🔚 Debug session ended due to connection issues');

            return true;
        }
    }

    /**
     * Handle continue command
     */
    private function handleContinueCommand(): bool
    {
        $this->log('▶️ Continuing execution...');
        $response = $this->continueExecution();

        // Check if we hit a breakpoint and finalize trace
        if ($this->didBreak($response)) {
            if ($path = $this->finalizeTraceOnBreak()) {
                $this->log("📊 Trace finalized at break: {$path}");
            }
        }

        if ($this->isExecutionComplete($response)) {
            $this->log('✅ Execution completed');

            return true;
        }

        $this->log('⏸️ Stopped (breakpoint or end)');
        $this->displayCurrentState();

        return false;
    }

    /**
     * Handle print variable command
     */
    private function handlePrintCommand(string $variable): void
    {
        if (empty($variable)) {
            $this->log('❌ Usage: p <variable_name>');

            return;
        }

        $this->log("🔍 Evaluating: {$variable}");
        try {
            $result = $this->evaluateExpression($variable);

            // Parse and format the result
            $xml = $this->parseXmlResponse($result);
            if ($xml !== null) {
                $this->displayPropertyResult($xml, $variable);
            } else {
                $this->log("📋 Raw result: {$result}");
            }
        } catch (Throwable $e) {
            $this->log("❌ Error evaluating '{$variable}': " . $e->getMessage());
        }
    }

    /**
     * Display property result from XML
     */
    private function displayPropertyResult(SimpleXMLElement $xml, string $variable): void
    {
        // Check for error first
        if (isset($xml->error)) {
            $errorMsg = (string) $xml->error->message;
            $this->log("❌ Error: {$errorMsg}");

            return;
        }

        // Handle property response
        if (isset($xml->property)) {
            $property = $xml->property;
            $type = (string) $property['type'];
            $encoding = (string) ($property['encoding'] ?? '');
            $rawValue = (string) $property;

            $value = $encoding === 'base64' ? base64_decode($rawValue) : $rawValue;

            if ($type === 'array' || $type === 'object') {
                $childCount = count($property->property ?? []);
                $this->log("📋 {$variable} ({$type}[{$childCount}]):");

                // Show ALL elements for AI client - no pagination needed
                if ($childCount > 0) {
                    foreach ($property->property as $child) {
                        $childName = (string) $child['name'];
                        $childType = (string) $child['type'];
                        $childEncoding = (string) ($child['encoding'] ?? '');
                        $childRawValue = (string) $child;
                        $childValue = $childEncoding === 'base64' ? base64_decode($childRawValue) : $childRawValue;

                        if ($childType === 'string') {
                            $childValue = '"' . $childValue . '"';
                        }

                        $this->log("  [{$childName}] ({$childType}): {$childValue}");
                    }
                }
            } else {
                $displayValue = $this->formatVariableValue($value, $type);
                $this->log("📋 {$variable} ({$type}): {$displayValue}");
            }
        }
    }

    /**
     * Handle backtrace command
     */
    private function handleBacktraceCommand(): void
    {
        $this->log('📋 Call stack:');
        $stackInfo = $this->getStack();
        $this->displayStackInfo($stackInfo);
    }

    /**
     * Handle list command
     */
    private function handleListCommand(): void
    {
        if (! $this->isConnected()) {
            $this->log('📄 Cannot list: Debug session ended');

            return;
        }

        $this->log('📄 Current location:');
        $this->displayCurrentState();
    }

    /**
     * Display help information
     */
    private function displayHelp(): void
    {
        $this->log('🆘 Available commands:');
        $this->log('  s, step     - Execute next line (step into)');
        $this->log('  o, over     - Execute next line (step over)');
        $this->log('  out         - Step out of current function');
        $this->log('  c, continue - Continue execution');
        $this->log('  p <var>     - Print variable value');
        $this->log('  bt          - Show backtrace');
        $this->log('  l, list     - Show current location');
        $this->log('  claude      - Analyze execution trace with AI');
        $this->log('  q, quit     - Exit debugger');
        $this->log('  h, help     - Show this help');
    }

    /**
     * Display current execution state
     */
    private function displayCurrentState(): void
    {
        // Skip display if connection is not available
        if (! $this->isConnected()) {
            $this->log('📍 Cannot display state: Connection not available');

            return;
        }

        try {
            // Get and display stack info
            $stackInfo = $this->getStack();
            if (! empty($stackInfo)) {
                $this->displayStackInfo($stackInfo);
            }
        } catch (Throwable $e) {
            $this->log('⚠️ Unable to get stack info: ' . $e->getMessage());
        }

        try {
            // Get and display variables
            $variables = $this->getVariables();
            if (! empty($variables)) {
                $this->displayVariables($variables);
            }
        } catch (Throwable $e) {
            $this->log('⚠️ Unable to get variables: ' . $e->getMessage());
        }
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
            $encoding = (string) ($property['encoding'] ?? '');
            $rawValue = (string) $property;

            // Decode base64 if needed
            $value = $encoding === 'base64' ? base64_decode($rawValue) : $rawValue;

            // Handle arrays and objects
            if ($type === 'array' || $type === 'object') {
                $childCount = count($property->property ?? []);
                // Fix double $ issue - remove prefix if already present
                $displayName = str_starts_with($name, '$') ? $name : '$' . $name;
                $this->log("  📋 {$displayName} ({$type}[{$childCount}]): <expandable>");
            } else {
                // Format the value display based on type
                $displayValue = $this->formatVariableValue($value, $type);
                // Fix double $ issue - remove prefix if already present
                $displayName = str_starts_with($name, '$') ? $name : '$' . $name;
                $this->log("  📋 {$displayName} ({$type}): {$displayValue}");
            }
        }
    }

    /**
     * Format variable value for display
     */
    private function formatVariableValue(string $value, string $type): string
    {
        switch ($type) {
            case 'string':
                return '"' . $value . '"';

            case 'int':
            case 'float':
                return $value;

            case 'bool':
                return $value === '1' ? 'true' : 'false';

            case 'null':
                return 'null';

            default:
                return $value;
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
        $timeoutValue = $this->options['readTimeout'] ?? self::DEFAULT_STEP_TIMEOUT;
        // If timeout is 0, don't use timeout cancellation (wait indefinitely)
        $timeout = $timeoutValue > 0 ? new TimeoutCancellation($timeoutValue) : null;

        try {
            // Read length header until NULL byte
            // FIXED: Correct argument order - Cancellation first, then length
            $lengthStr = '';
            while (true) {
                $char = $timeout !== null ? $socket->read($timeout, 1) : $socket->read(null, 1);
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
                $chunk = $timeout !== null ? $socket->read($timeout, $remaining) : $socket->read(null, $remaining);
                if ($chunk === null || $chunk === '') {
                    throw new RuntimeException('Connection closed while reading response data');
                }

                $response .= $chunk;
                $remaining -= strlen($chunk);
            }

            // Read the trailing NULL byte
            // FIXED: Correct argument order
            $trailingNull = $timeout !== null ? $socket->read($timeout, 1) : $socket->read(null, 1);
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
        if ($this->jsonMode) {
            return;
        }
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
                // Only send detach if connection is still writable
                if ($this->xdebugSocket->isWritable()) {
                    $this->sendCommand('detach');
                }
            } catch (Throwable) {
                // Ignore cleanup errors - connection may already be broken
            }

            $this->xdebugSocket->close();
        }

        $this->log('🧹 Cleanup completed');
    }

    /**
     * Handle Claude analysis command
     */
    private function handleClaudeCommand(string $args): void
    {
        $this->log('🤖 Analyzing execution trace with Claude...');

        try {
            // Get current breakpoint context
            $context = $this->getCurrentDebugContext();

            // Build analysis prompt
            $prompt = $this->buildClaudeAnalysisPrompt($context, $args);

            // Execute Claude analysis
            $claudeCommand = 'claude --print ' . escapeshellarg($prompt);
            $this->log('💭 Executing: ' . $claudeCommand);

            // Run Claude analysis in background and show output
            $output = shell_exec($claudeCommand . ' 2>&1');

            if ($output) {
                $this->log('📊 Claude Analysis Result:');
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (! empty(trim($line))) {
                        $this->log('   ' . $line);
                    }
                }
            } else {
                $this->log('❌ Claude analysis failed or produced no output');
            }
        } catch (Throwable $e) {
            $this->log('❌ Claude analysis error: ' . $e->getMessage());
        }
    }

    /**
     * Get current debug context for Claude analysis
     */
    private function getCurrentDebugContext(): array
    {
        $context = [
            'target_script' => $this->targetScript,
            'debug_port' => $this->debugPort,
            'trace_file' => $this->traceFile,
            'breakpoint_line' => $this->initialBreakpointLine,
        ];

        // Try to get current variables if possible
        try {
            $variables = $this->getCurrentVariables();
            if (! empty($variables)) {
                $context['current_variables'] = $variables;
            }
        } catch (Throwable) {
            // Variables not available, continue without them
        }

        // Try to get stack trace
        try {
            $stack = $this->getStackTrace();
            if (! empty($stack)) {
                $context['current_stack'] = $stack;
            }
        } catch (Throwable) {
            // Stack not available, continue without it
        }

        return $context;
    }

    /**
     * Build Claude analysis prompt with context
     */
    private function buildClaudeAnalysisPrompt(array $context, string $userArgs): string
    {
        $targetScript = basename($context['target_script']);

        $prompt = "Analyze PHP debugging session for {$targetScript}:\n\n";

        // Add trace file analysis
        if (! empty($context['trace_file']) && file_exists($context['trace_file'])) {
            $prompt .= "## Trace Analysis\n";
            $prompt .= "Please analyze the execution trace: {$context['trace_file']}\n\n";

            // Include last 20 lines of trace for context
            $traceLines = file($context['trace_file']);
            if ($traceLines && count($traceLines) > 0) {
                $lastLines = array_slice($traceLines, -20);
                $prompt .= "Recent trace data:\n```\n" . implode('', $lastLines) . "```\n\n";
            }
        }

        // Add current variables if available
        if (! empty($context['current_variables'])) {
            $prompt .= "## Current Variables\n";
            foreach ($context['current_variables'] as $var => $value) {
                $prompt .= "- \${$var} = {$value}\n";
            }

            $prompt .= "\n";
        }

        // Add breakpoint context
        if (! empty($context['breakpoint_line'])) {
            $prompt .= "## Breakpoint Context\n";
            $prompt .= "Stopped at line {$context['breakpoint_line']} in {$targetScript}\n\n";
        }

        // Add user-specific analysis request
        if (! empty($userArgs)) {
            $prompt .= "## Specific Analysis Request\n";
            $prompt .= $userArgs . "\n\n";
        }

        $prompt .= "## Analysis Focus\n";
        $prompt .= "Please provide:\n";
        $prompt .= "1. Call chain analysis leading to current breakpoint\n";
        $prompt .= "2. Variable state analysis and any anomalies\n";
        $prompt .= "3. Root cause identification if this is a bug investigation\n";
        $prompt .= "4. Performance insights from trace data\n";
        $prompt .= "5. Suggested next debugging steps or code fixes\n";

        return $prompt;
    }

    /**
     * Get current variables from debugger session
     */
    private function getCurrentVariables(): array
    {
        try {
            // Send context_get command to get local variables
            $response = $this->sendCommand('context_get', ['c' => '0']); // Local context

            if (! $response) {
                return [];
            }

            $variables = [];
            $xml = simplexml_load_string($response);
            if ($xml && isset($xml->property)) {
                foreach ($xml->property as $prop) {
                    $name = (string) $prop['name'];
                    $type = (string) $prop['type'];
                    $encoding = (string) ($prop['encoding'] ?? '');
                    $raw = (string) $prop; // element text content
                    
                    // Get better info for arrays and objects using print_r
                    if ($type === 'array' || $type === 'object') {
                        $numChildren = (int) ($prop['numchildren'] ?? 0);
                        $value = $encoding === 'base64' ? base64_decode($raw) : $raw;
                        
                        if ($numChildren > 0) {
                            // Try json_encode first for detailed display
                            $jsonOutput = $this->getJsonEncodeOutput($name);
                            if ($jsonOutput) {
                                $variables[$name] = "{$type}: {$jsonOutput}";
                            } else {
                                // Try to get detailed contents from child properties in the response
                                $details = $this->extractChildDetails($prop, $type);
                                if ($details) {
                                    $variables[$name] = "{$type}: {$details}";
                                } else {
                                    // Fallback to basic info
                                    if ($type === 'array') {
                                        $variables[$name] = "array: [{$numChildren} items]";
                                    } else {
                                        $className = (string) ($prop['classname'] ?? 'object');
                                        $variables[$name] = "object: {$className} [{$numChildren} properties]";
                                    }
                                }
                            }
                        } else {
                            $variables[$name] = $type === 'array' ? "array: []" : "object: {}";
                        }
                    } else {
                        $value = $encoding === 'base64' ? base64_decode($raw) : $raw;
                        $variables[$name] = "{$type}: {$value}";
                    }
                }
            }

            return $variables;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Extract child details from XML property node
     */
    private function extractChildDetails(\SimpleXMLElement $prop, string $type): ?string
    {
        try {
            // Check if property has child elements
            if (!isset($prop->property)) {
                return null;
            }
            
            $items = [];
            $maxItems = $type === 'array' ? 5 : 3; // Show more for arrays, less for objects
            
            foreach ($prop->property as $child) {
                $key = (string) $child['name'];
                $childType = (string) $child['type'];
                $encoding = (string) ($child['encoding'] ?? '');
                $value = (string) $child;
                
                if ($encoding === 'base64') {
                    $value = base64_decode($value);
                }
                
                // Format key-value pairs
                if ($childType === 'string') {
                    $displayValue = strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value;
                    $items[] = "{$key}: \"{$displayValue}\"";
                } elseif ($childType === 'int' || $childType === 'float') {
                    $items[] = "{$key}: {$value}";
                } elseif ($childType === 'bool') {
                    $boolValue = $value === '1' ? 'true' : 'false';
                    $items[] = "{$key}: {$boolValue}";
                } elseif ($childType === 'array') {
                    $childCount = (int) ($child['numchildren'] ?? 0);
                    $items[] = "{$key}: array[{$childCount}]";
                } elseif ($childType === 'object') {
                    $className = (string) ($child['classname'] ?? 'object');
                    $items[] = "{$key}: {$className}";
                } else {
                    $items[] = "{$key}: {$childType}";
                }
                
                // Limit number of items shown
                if (count($items) >= $maxItems) {
                    $totalCount = (int) ($prop['numchildren'] ?? 0);
                    if ($totalCount > $maxItems) {
                        $items[] = "... ({$totalCount} total)";
                    }
                    break;
                }
            }
            
            if (empty($items)) {
                return null;
            }
            
            // Format based on type
            if ($type === 'array') {
                return '[' . implode(', ', $items) . ']';
            } else {
                return '{' . implode(', ', $items) . '}';
            }
            
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get json_encode output for a variable using Xdebug eval
     */
    private function getJsonEncodeOutput(string $varName): ?string
    {
        try {
            // Use eval to execute json_encode($var, JSON_UNESCAPED_UNICODE)
            $expression = "json_encode({$varName}, JSON_UNESCAPED_UNICODE)";
            $response = $this->sendCommand('eval', [base64_encode($expression)]);
            
            if (!$response) {
                return null;
            }
            
            $xml = simplexml_load_string($response);
            if (!$xml || !isset($xml->property)) {
                return null;
            }
            
            $property = $xml->property;
            $type = (string) ($property['type'] ?? '');
            $encoding = (string) ($property['encoding'] ?? '');
            $output = (string) $property;
            
            // Skip if it's not a string result
            if ($type !== 'string') {
                return null;
            }
            
            if ($encoding === 'base64') {
                $output = base64_decode($output);
            }
            
            // Clean up and format JSON output
            $output = trim($output);
            if (strlen($output) > 200) {
                $output = substr($output, 0, 200) . '... (truncated)';
            }
            
            // Make it more readable by adding spaces after colons and commas
            $output = preg_replace('/([,:])\s*/', '$1 ', $output);
            
            return $output;
            
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Expand complex variables (arrays/objects) for better debugging information
     */
    private function expandComplexVariable(string $name, string $type): string
    {
        try {
            // Get detailed information about the variable
            $response = $this->sendCommand('property_get', ['n' => $name, 'd' => '2']); // depth 2
            
            if (!$response) {
                return "{$type}: (unable to expand)";
            }

            $xml = simplexml_load_string($response);
            if (!$xml || !isset($xml->property)) {
                return "{$type}: (no data)";
            }

            $property = $xml->property;
            $numChildren = (int) ($property['numchildren'] ?? 0);
            
            if ($type === 'array') {
                if ($numChildren === 0) {
                    return "array: []";
                }
                
                $items = [];
                if (isset($property->property)) {
                    foreach ($property->property as $child) {
                        $key = (string) $child['name'];
                        $childType = (string) $child['type'];
                        $encoding = (string) ($child['encoding'] ?? '');
                        $value = (string) $child;
                        
                        if ($encoding === 'base64') {
                            $value = base64_decode($value);
                        }
                        
                        // Limit recursion for safety
                        if ($childType === 'array' || $childType === 'object') {
                            $items[] = "{$key} => {$childType}";
                        } else {
                            $items[] = "{$key} => {$value}";
                        }
                        
                        // Limit number of items shown for safety
                        if (count($items) >= 10) {
                            $items[] = "... ({$numChildren} total items)";
                            break;
                        }
                    }
                }
                
                return "array: [" . implode(", ", $items) . "]";
            } 
            
            if ($type === 'object') {
                $className = (string) ($property['classname'] ?? 'object');
                if ($numChildren === 0) {
                    return "object: {$className} {}";
                }
                
                $properties = [];
                if (isset($property->property)) {
                    foreach ($property->property as $child) {
                        $key = (string) $child['name'];
                        $childType = (string) $child['type'];
                        $encoding = (string) ($child['encoding'] ?? '');
                        $value = (string) $child;
                        
                        if ($encoding === 'base64') {
                            $value = base64_decode($value);
                        }
                        
                        // Limit recursion for safety
                        if ($childType === 'array' || $childType === 'object') {
                            $properties[] = "{$key}: {$childType}";
                        } else {
                            $properties[] = "{$key}: {$value}";
                        }
                        
                        // Limit number of properties shown for safety
                        if (count($properties) >= 5) {
                            $properties[] = "... (more properties)";
                            break;
                        }
                    }
                }
                
                return "object: {$className} {" . implode(", ", $properties) . "}";
            }
            
            return "{$type}: (unknown format)";
            
        } catch (Throwable $e) {
            return "{$type}: (expansion error: {$e->getMessage()})";
        }
    }

    /**
     * Get current stack trace
     */
    private function getStackTrace(): array
    {
        try {
            $response = $this->sendCommand('stack_get');

            if (! $response) {
                return [];
            }

            $stack = [];
            $xml = simplexml_load_string($response);
            if ($xml && isset($xml->stack)) {
                foreach ($xml->stack as $frame) {
                    $function = (string) $frame['where'];
                    $file = (string) $frame['filename'];
                    $line = (string) $frame['lineno'];
                    $stack[] = "{$function} at {$file}:{$line}";
                }
            }

            return $stack;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Output comprehensive debug state with variables, location, and trace content
     */
    private function outputComprehensiveDebugState(): void
    {
        $debugState = [];
        
        // Add context if provided
        if (!empty($this->options['context'])) {
            $debugState['context'] = $this->options['context'];
        }

        // Get current location and variables
        try {
            $stack = $this->getStack();
            $variables = $this->getCurrentVariables(); // Get variables from current context

            if (! empty($stack)) {
                $currentFrame = $stack[0];
                $debugState['breaks'] = [
                    [
                        'location' => [
                            'file' => $currentFrame['file'] ?? 'unknown',
                            'line' => (int) ($currentFrame['line'] ?? 1),
                        ],
                        'variables' => $variables,
                    ],
                ];
            } else {
                // Fallback if no stack info
                $debugState['breaks'] = [
                    [
                        'location' => [
                            'file' => $this->targetScript,
                            'line' => 1,
                        ],
                        'variables' => $variables,
                    ],
                ];
            }
        } catch (Throwable) {
            // Fallback for any errors
            $debugState['breaks'] = [
                [
                    'location' => [
                        'file' => $this->targetScript,
                        'line' => 1,
                    ],
                    'variables' => [],
                ],
            ];
        }

        // Add trace file information
        $debugState['trace'] = $this->getTraceInfo();

        // Output format based on jsonMode or jsonOutput option
        if ($this->jsonMode || ($this->options['jsonOutput'] ?? false)) {
            echo json_encode($debugState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            // Human-readable format
            $this->log("\n" . str_repeat('=', 60));
            $this->log("🎯 DEBUG STATE");
            $this->log(str_repeat('=', 60));

            foreach ($debugState['breaks'] as $break) {
                $loc = $break['location'];
                $this->log("📍 Location: {$loc['file']}:{$loc['line']}");

                if (! empty($break['variables'])) {
                    $this->log("📊 Variables:");
                    foreach ($break['variables'] as $name => $value) {
                        $displayValue = is_string($value) ? $value : json_encode($value);
                        $this->log("  {$name} = {$displayValue}");
                    }
                }
            }

            if (isset($debugState['trace']['file'])) {
                $this->log("📈 Trace file: {$debugState['trace']['file']}");
                $this->log('📊 Trace lines: ' . count($debugState['trace']['content']));
            }
        }
    }

    /**
     * Get trace file information with content
     */
    private function getTraceInfo(): array
    {
        // Look for trace files with various patterns
        $patterns = [
            '/tmp/trace.*.xt',
            '/tmp/trace-*-' . basename($this->targetScript, '.php') . '.xt',
            '/tmp/trace-*.xt',
        ];

        $allTraceFiles = [];
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if ($files) {
                $allTraceFiles = array_merge($allTraceFiles, $files);
            }
        }

        if (empty($allTraceFiles)) {
            return [
                'file' => '',
                'tail' => [],
            ];
        }

        // Remove duplicates and sort by modification time, get the most recent
        $allTraceFiles = array_unique($allTraceFiles);
        usort($allTraceFiles, static function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latestTrace = $allTraceFiles[0];

        // Read trace file content (last 1000 lines maximum)
        $traceLines = [];
        if (file_exists($latestTrace)) {
            $lines = file($latestTrace, FILE_IGNORE_NEW_LINES);
            if ($lines !== false) {
                $traceLines = array_slice($lines, -1000);
            }
        }

        return [
            'file' => $latestTrace,
            'content' => $traceLines,
        ];
    }

    /**
     * Process multiple breakpoints with safety limits
     */
    private function processMultipleBreakpoints(): void
    {
        $this->log('📊 exit-on-break mode: processing breakpoints...');
        $breaks = [];
        $maxBreaks = 20; // Safety limit
        $executionStartTime = microtime(true);
        $maxExecutionTime = 30.0; // 30 seconds
        $lastLocation = null;
        $sameLocationCount = 0;
        $breakCount = 0;

        try {
            // Start execution
            $response = $this->sendCommand('run');

            while (!$this->isExecutionComplete($response) && $breakCount < $maxBreaks) {
                // Check execution time limit
                if ((microtime(true) - $executionStartTime) > $maxExecutionTime) {
                    $this->log('⚠️ Execution time limit reached (30s)');
                    break;
                }

                if ($this->didBreak($response)) {
                    $breakCount++;
                    $this->log("🎯 Breakpoint #{$breakCount} hit");

                    // Get current location from break response
                    $this->log("📋 Break response: " . substr($response, 0, 200) . "...");
                    $currentLocation = $this->extractLocationFromBreakResponse($response);

                    // Check for infinite loop (same location repeatedly)  
                    if ($currentLocation === $lastLocation) {
                        $sameLocationCount++;
                        if ($sameLocationCount >= 3) {
                            $this->log('⚠️ Infinite loop detected (same location hit 3 times)');
                            break;
                        }
                    } else {
                        $sameLocationCount = 1;
                        $lastLocation = $currentLocation;
                    }

                    $this->log("📍 Current location: $currentLocation");

                    // Capture debug state at breakpoint (before step)
                    $debugState = $this->captureCurrentDebugState($breakCount);
                    if ($debugState !== null) {
                        $breaks[] = $debugState;
                        $this->log("✅ Debug state captured for step $breakCount");
                    } else {
                        $this->log("❌ Failed to capture debug state for step $breakCount");
                    }

                    // Continue to next breakpoint
                    $response = $this->sendCommand('run');
                } else {
                    // Unexpected state, continue
                    $response = $this->sendCommand('run');
                }
            }

            if ($breakCount >= $maxBreaks) {
                $this->log("⚠️ Maximum breakpoints limit reached ({$maxBreaks})");
            }

        } catch (Throwable $e) {
            $this->log('❌ Error during breakpoint processing: ' . $e->getMessage());
        }

        // Output final result
        $this->outputMultipleBreakResults($breaks);
    }

    /**
     * Capture current debug state for a breakpoint
     */
    private function captureCurrentDebugState(int $breakNumber): ?array
    {
        try {
            $stackXml = $this->getStack();
            $this->log("📋 Stack info: " . json_encode($stackXml));
            
            $variables = $this->getCurrentVariables();
            $this->log("📋 Variables: " . json_encode($variables));

            // Parse stack XML to get current location
            $location = $this->parseStackLocation($stackXml);
            
            return [
                'step' => $breakNumber,
                'location' => $location,
                'variables' => $variables,
            ];
        } catch (Throwable $e) {
            $this->log("❌ Error in captureCurrentDebugState: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse stack XML response to extract current location
     */
    private function parseStackLocation(string $stackXml): array
    {
        try {
            $xml = simplexml_load_string($stackXml);
            if ($xml && isset($xml->stack) && count($xml->stack) > 0) {
                $currentFrame = $xml->stack[0];
                $filename = (string) $currentFrame['filename'];
                $line = (int) $currentFrame['lineno'];
                
                // Clean up file:// protocol from filename
                $file = str_replace('file://', '', $filename);
                
                return [
                    'file' => basename($file),
                    'line' => $line
                ];
            }
        } catch (Throwable $e) {
            $this->log("❌ Error parsing stack location: " . $e->getMessage());
        }
        
        // Fallback
        return [
            'file' => basename($this->targetScript),
            'line' => 1
        ];
    }

    /**
     * Output results for multiple breakpoints
     */
    private function outputMultipleBreakResults(array $breaks): void
    {
        $debugState = [
            '$schema' => 'https://koriym.github.io/xdebug-mcp/schema/xdebug-debug.json',
            'breaks' => $breaks,
            'trace' => $this->getTraceInfo(),
        ];
        
        // Add context if provided
        if (!empty($this->options['context'])) {
            $debugState['context'] = $this->options['context'];
        }

        // Output format based on jsonMode or jsonOutput option
        if ($this->jsonMode || ($this->options['jsonOutput'] ?? false)) {
            echo json_encode($debugState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            // Human-readable format
            $this->log("\n" . str_repeat('=', 60));
            $this->log("🎯 MULTIPLE BREAKPOINTS DEBUG RESULT");
            $this->log(str_repeat('=', 60));

            foreach ($breaks as $break) {
                $loc = $break['location'];
                $this->log("📍 Step {$break['step']}: {$loc['file']}:{$loc['line']}");

                if (!empty($break['variables'])) {
                    $this->log("📊 Variables:");
                    foreach ($break['variables'] as $name => $value) {
                        $displayValue = is_string($value) ? $value : json_encode($value);
                        $this->log("  {$name} = {$displayValue}");
                    }
                }
                $this->log("");
            }

            if (isset($debugState['trace']['file'])) {
                $this->log("📈 Trace file: {$debugState['trace']['file']}");
                $this->log('📊 Trace lines: ' . count($debugState['trace']['content']));
            }
        }
    }

    /**
     * Extract location information from break response
     */
    private function extractLocationFromBreakResponse(string $response): string
    {
        try {
            $xml = simplexml_load_string($response);
            if ($xml) {
                // Register xdebug namespace
                $xml->registerXPathNamespace('xdebug', 'https://xdebug.org/dbgp/xdebug');
                $messages = $xml->xpath('//xdebug:message');
                
                if (!empty($messages)) {
                    $message = $messages[0];
                    $filename = (string)$message['filename'];
                    $lineno = (string)$message['lineno'];
                    // Remove file:// prefix if present
                    $filename = str_replace('file://', '', $filename);
                    return basename($filename) . ':' . $lineno;
                }
            }
        } catch (Throwable $e) {
            $this->log("❌ Error parsing break response: " . $e->getMessage());
        }
        return 'unknown:0';
    }
}
