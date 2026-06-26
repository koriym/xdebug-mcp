<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Process\Process;
use Amp\Socket\ServerSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketException;
use Amp\TimeoutCancellation;
use Koriym\XdebugMcp\Exceptions\DebugSessionException;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use Koriym\XdebugMcp\Utilities\PathNormalizer;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SimpleXMLElement;
use Stringable;
use Throwable;

use function Amp\async;
use function Amp\delay;
use function Amp\Socket\listen;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_push;
use function array_reverse;
use function array_slice;
use function array_splice;
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
use function getenv;
use function glob;
use function implode;
use function in_array;
use function intval;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function ltrim;
use function mb_strcut;
use function md5;
use function microtime;
use function parse_str;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function property_exists;
use function rawurlencode;
use function register_shutdown_function;
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
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const STDERR;

/**
 * AMP-based Interactive Debugger
 * Streamlined for single-use debugging sessions
 *
 * @codeCoverageIgnore This class requires a live Xdebug daemon connection for meaningful
 *                     testing. All 72 methods involve DBGp protocol communication over
 *                     sockets, XML parsing of Xdebug responses, and async I/O operations.
 *                     Integration tests exist but require specific Xdebug runtime setup.
 *                     Coverage: 1.39% (1/72 methods), 0.71% (9/1266 lines) - remaining
 *                     uncovered code paths require actual debugging sessions.
 * @phpstan-type StackFrame array{function: string, file: string, line: int}
 * @phpstan-type BreakpointRef array{id: string, label: string}
 * @phpstan-type ShallowKeyDiff array{added: array<string, string>, removed: array<string, string>, changed: array<string, array{before: string, after: string}>}
 * @phpstan-type VariableDiffEntry array{change: string, type: string, before?: string, after?: string, keys?: ShallowKeyDiff}
 * @phpstan-type VariableDiff array<string, VariableDiffEntry>
 * @phpstan-type WatchChange array{expression: string, value: string, previous: string|null, reason: string}
 * @phpstan-type DebugBreak array{step: int, stack: list<StackFrame>, breakpoint?: BreakpointRef, variables?: array<string, string>, recording_type?: string, diff?: VariableDiff, watches?: list<WatchChange>}
 * @phpstan-type TraceInfo array{file: string, lines: int, functions: int, max_depth: int, db_queries: int, error?: string}
 * @phpstan-type OutputDebugInfo array{trace_files_found: int, search_patterns: list<string>, latest_file: string|null}
 * @phpstan-type XstepJsonOutput array{'$schema': string, breakpoint?: BreakpointRef, breaks: list<DebugBreak>, trace?: TraceInfo, context?: string, debug?: OutputDebugInfo}
 * @phpstan-type ShallowJsonValue string|int|float|bool|array<array-key, string|int|float|bool|array<array-key, string|int|float|bool|null>|null>|null
 * @phpstan-type ShallowJsonMap array<array-key, ShallowJsonValue>
 * @see https://xdebug.org/docs/step_debug
 * @see https://xdebug.org/docs/dbgp
 */
final class DebugServer
{
    private const DEFAULT_CONNECTION_TIMEOUT = 30.0;  // Initial connection only
    private const DEFAULT_EXECUTION_TIMEOUT = 3600.0;  // 1 hour for long debugging sessions
    private const DEFAULT_STEP_TIMEOUT = 0.0;  // No timeout for interactive debugging
    private const MAX_STEPS = 100;  // Default maximum steps for step recording
    private const DEFAULT_MAX_VALUE_BYTES = 200;
    private const DEFAULT_CHILD_VALUE_BYTES = 20;
    private const STACK_CONTEXT_LIMIT = 5;

    /** @var DeferredFuture<bool>|null */
    private DeferredFuture|null $listenerReady = null;

    /** @var DeferredFuture<bool>|null */
    private DeferredFuture|null $xdebugConnected = null;
    private Socket|null $xdebugSocket = null;
    private ServerSocket|null $server = null;
    private Process|null $process = null;
    private int $transactionId = 1;
    private string|null $traceFile = null;
    private SocketHttpServer|null $httpServer = null;
    private bool $httpMode = false;
    private bool $shouldExit = false;

    /** @var list<DebugBreak> */
    private array $breaks = [];
    private bool $isDockerCommand = false;
    private bool $stepRecordingOutputDone = false;

    /** @var BreakpointRef|null Breakpoint shared by every recorded step; emitted once at top level */
    private array|null $recordedBreakpoint = null;

    /** @var list<array{id: string, label: string, file: string, line: int, condition?: string}> */
    private array $configuredBreakpoints = [];

    /** @var array{id: string, label: string, file: string, line: int, condition?: string}|null */
    private array|null $activeBreakpoint = null;

    /** @param array{command?: list<string>, context?: string, breakpoint?: string, steps?: int, connectionTimeout?: float, executionTimeout?: float, traceOnly?: bool, maxSteps?: int, jsonOutput?: bool, breakpoints?: list<array{file: string, line: int|string, condition?: string}>, readTimeout?: float, watches?: list<string>, pretty?: bool, maxValueBytes?: int|null, maxDepth?: int|null, phpBinary?: string, includeVendor?: string|null} $options */
    public function __construct(
        private readonly string $targetScript,
        private readonly int $debugPort,
        private readonly int|null $initialBreakpointLine = null,
        private array $options = [],
        private readonly bool $jsonMode = false,
    ) {
        if (! file_exists($targetScript)) {
            throw new InvalidArgumentException("Script not found: {$targetScript}");
        }

        // Detect Docker/Podman/Kubectl command early for listener configuration
        $command = $options['command'] ?? [];
        $this->isDockerCommand = ContainerHelper::isContainerCommand($command);

        // Check for existing sessions (warning only)
        $this->checkExistingSessions();

        // Register shutdown handler to ensure cleanup
        register_shutdown_function([$this, 'emergencyCleanup']);
    }

    /**
     * Start the debug session with 3 parallel tasks
     */
    public function __invoke(): void
    {
        $this->listenerReady = new DeferredFuture();
        $this->xdebugConnected = new DeferredFuture();

        $this->log('🚀 Starting AMP Interactive Debugger');
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
            $this->gracefulExit(0);
        }
    }

    /**
     * Start Xdebug listener with timeout
     */
    private function startXdebugListener(): void
    {
        try {
            // Use 0.0.0.0 for Docker to allow connections from containers
            // Use 127.0.0.1 for local commands for security
            $listenAddress = $this->isDockerCommand ? '0.0.0.0' : '127.0.0.1';
            $this->server = listen("{$listenAddress}:{$this->debugPort}");
            $this->log("📡 Listener ready on {$listenAddress}:{$this->debugPort}");
            $this->log('⏳ Waiting for Xdebug connection...');

            // Notify listener ready (Opus pattern)
            $this->listenerReady?->complete(true);

            // Accept connection with timeout
            $connectionTimeout = $this->options['connectionTimeout'] ?? self::DEFAULT_CONNECTION_TIMEOUT;
            $cancellation = new TimeoutCancellation($connectionTimeout);
            $socket = $this->server?->accept($cancellation);

            if (! $socket instanceof Socket) {
                throw new SocketException(sprintf(
                    'No Xdebug connection within %.1f seconds',
                    $connectionTimeout,
                ));
            }

            $this->log('✅ Xdebug connected!');
            $this->xdebugSocket = $socket;

            // Close server socket after accepting connection
            $this->server?->close();
            $this->server = null;

            // Read init packet
            $this->log('📨 Reading initial Xdebug packet...');
            try {
                $initData = $this->readDbgpFrame($socket);
                $this->log('📨 Session initialized: ' . substr($initData, 0, 100) . '...');
            } catch (Throwable $e) {
                $this->log('⚠️ Init packet read warning: ' . $e->getMessage());
                // Continue anyway
            }

            // Notify connection established
            $this->xdebugConnected?->complete(true);
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
            $this->listenerReady?->getFuture()->await($cancellation);

            // Check if custom command is provided
            if (isset($this->options['command']) && $this->options['command'] !== []) {
                $command = $this->options['command'];

                // Check if this is a Docker/Podman/Kubectl command
                $isDockerCommand = ContainerHelper::isContainerCommand($command);

                if ($isDockerCommand) {
                    // Docker command: Find PHP position and inject Xdebug arguments
                    $phpIndex = ContainerHelper::findPhpCommandIndex($command);
                    if ($phpIndex === false) {
                        throw new RuntimeException('PHP command not found in Docker command. Expected format: docker ... php script.php');
                    }

                    $scriptName = basename($this->targetScript, '.php');
                    $traceFile = '/tmp/trace-%t-' . $scriptName . '.xt';

                    // Build Xdebug arguments - use runtime-specific client host
                    $clientHost = ContainerHelper::getContainerClientHost($command);
                    $xdebugArgs = [
                        '-dxdebug.mode=debug,trace',
                        '-dxdebug.start_with_request=yes',
                        '-dxdebug.client_host=' . $clientHost,
                        '-dxdebug.client_port=' . $this->debugPort,
                        '-dxdebug.output_dir=/tmp',
                        '-dxdebug.trace_output_name=trace-%s',
                        '-dxdebug.trace_format=1',
                        '-dxdebug.use_compression=0',
                        '-dxdebug.log=/tmp/xdebug.log',
                        '-dxdebug.log_level=7',
                        '-dxdebug.connect_timeout_ms=5000',
                        '-dmemory_limit=1G',
                        '-derror_reporting=E_ERROR',
                        '-dlog_errors=1',
                        '-derror_log=/tmp/php.log',
                    ];

                    // Insert Xdebug arguments after PHP command
                    foreach (array_reverse($xdebugArgs) as $arg) {
                        array_splice($command, $phpIndex + 1, 0, [$arg]);
                    }

                    // Insert environment variables after 'run' or 'exec' for Docker
                    // XDEBUG_MODE=debug is required because environment variable has higher priority than -d flags
                    $envInsertIndex = ContainerHelper::findDockerEnvInsertIndex($command);
                    if ($envInsertIndex !== false) {
                        // Insert in reverse order since each splice shifts indices
                        array_splice($command, $envInsertIndex, 0, ['-e', 'XDEBUG_SESSION=xdebug-mcp']);
                        array_splice($command, $envInsertIndex, 0, ['-e', 'XDEBUG_MODE=debug']);
                    } else {
                        $this->log('⚠️  Warning: Could not find insertion point for environment variables. Xdebug may not connect properly.');
                    }

                    $cmd = implode(' ', $command);
                    $this->traceFile = $traceFile;
                } elseif ($command[0] === 'php') {
                    // Local PHP command
                    $scriptName = basename($this->targetScript, '.php');
                    $traceFile = '/tmp/trace-%t-' . $scriptName . '.xt';
                    $prependFilter = __DIR__ . '/prepend_trace.php';

                    // Get appropriate Xdebug flag (empty if already loaded)
                    $xdebugFlag = XdebugFinder::getXdebugFlag();
                    $xdebugPart = $xdebugFlag !== '' ? $xdebugFlag . ' ' : '';

                    // Allow callers (e.g. xback --php=...) to override the spawned
                    // PHP binary while keeping $command[0] as the literal 'php'.
                    $phpBinary = ($this->options['phpBinary'] ?? '') !== ''
                        ? (string) $this->options['phpBinary']
                        : 'php';
                    $includeVendorEnv = ($this->options['includeVendor'] ?? null) !== null
                        ? 'XDEBUG_MCP_INCLUDE_VENDOR=' . escapeshellarg((string) $this->options['includeVendor']) . ' '
                        : '';

                    $cmd = sprintf(
                        'XDEBUG_SESSION=xdebug-mcp %s%s %s'
                        . '-dxdebug.mode=debug,trace '
                        . '-dxdebug.start_with_request=yes '
                        . '-dxdebug.client_host=127.0.0.1 '
                        . '-dxdebug.client_port=%d '
                        . '-dxdebug.trace_output_name=trace-%%s '
                        . '-dxdebug.trace_format=1 '
                        . '-dxdebug.use_compression=0 '
                        . '-dxdebug.log=/tmp/xdebug.log '
                        . '-dxdebug.log_level=7 '
                        . '-dxdebug.connect_timeout_ms=5000 '
                        . '-dmemory_limit=1G '
                        . '-derror_reporting=E_ERROR '
                        . '-dlog_errors=1 '
                        . '-derror_log=/tmp/php.log '
                        . '-dauto_prepend_file=%s '
                        . '%s',
                        $includeVendorEnv,
                        escapeshellarg($phpBinary),
                        $xdebugPart,
                        $this->debugPort,
                        escapeshellarg($prependFilter),
                        implode(' ', array_map(escapeshellarg(...), array_slice($command, 1))),
                    );
                    $this->traceFile = $traceFile;
                } else {
                    throw new RuntimeException("Custom command must start with 'php' or be a Docker/Podman/Kubectl command");
                }
            } else {
                // Default: simple script execution
                $scriptName = basename($this->targetScript, '.php');
                $traceFile = '/tmp/trace-%t-' . $scriptName . '.xt';
                $prependFilter = __DIR__ . '/prepend_trace.php';

                // Get appropriate Xdebug flag (empty if already loaded)
                $xdebugFlag = XdebugFinder::getXdebugFlag();
                $xdebugPart = $xdebugFlag !== '' ? $xdebugFlag . ' ' : '';

                // Honor an optional PHP-binary override.
                $phpBinary = ($this->options['phpBinary'] ?? '') !== ''
                    ? (string) $this->options['phpBinary']
                    : 'php';
                $includeVendorEnv = ($this->options['includeVendor'] ?? null) !== null
                    ? 'XDEBUG_MCP_INCLUDE_VENDOR=' . escapeshellarg((string) $this->options['includeVendor']) . ' '
                    : '';

                $cmd = sprintf(
                    'XDEBUG_SESSION=xdebug-mcp %s%s %s'
                    . '-dxdebug.mode=debug,trace '
                    . '-dxdebug.start_with_request=yes '
                    . '-dxdebug.client_host=127.0.0.1 '
                    . '-dxdebug.client_port=%d '
                    . '-dxdebug.trace_output_name=trace-%%s '
                    . '-dxdebug.trace_format=1 '
                    . '-dxdebug.use_compression=0 '
                    . '-dxdebug.log=/tmp/xdebug.log '
                    . '-dxdebug.log_level=7 '
                    . '-dxdebug.connect_timeout_ms=5000 '
                    . '-dauto_prepend_file=%s '
                    . '%s',
                    $includeVendorEnv,
                    escapeshellarg($phpBinary),
                    $xdebugPart,
                    $this->debugPort,
                    escapeshellarg($prependFilter),
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
                $exitCode = $this->process?->join($cancellation);

                if ($exitCode !== 0) {
                    $this->log("⚠️ Script exited with code: {$exitCode}");
                }
            }
            // For interactive debugging, don't wait for process completion
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
            $this->xdebugConnected?->getFuture()->await($cancellation);

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
                // Check if Step Recording is enabled in exit-on-break mode
                if (isset($this->options['maxSteps']) && $this->options['maxSteps'] > 0) {
                    $this->processStepRecordingBreakpoints();
                } else {
                    $this->processMultipleBreakpoints();
                }

                $this->gracefulExit(0);

                return; // Never reached, but explicit for readability
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

            // Check if Step Recording mode is enabled
            if (isset($this->options['maxSteps']) && $this->options['maxSteps'] > 0) {
                $this->log("🎬 Starting Step Recording mode with {$this->options['maxSteps']} steps");
                $steps = $this->performStepTrace();

                if ($this->jsonMode) {
                    // Add steps data to the breaks array for JSON output
                    $this->breaks = array_merge($this->breaks, $steps);
                }
            } else {
                // Start interactive debugging session
                $this->startInteractiveSession();
            }
        } catch (Throwable $e) {
            $this->log('Debug sequence error: ' . $e->getMessage());
        }
    }

    /**
     * Perform step-by-step tracing with variable inspection for Step Recording
     * Records variable state at each step for AI analysis
     *
     * @return list<DebugBreak>
     */
    private function performStepTrace(): array
    {
        $stepCount = 0;
        $recordedCount = 0;
        $maxSteps = $this->options['maxSteps'] ?? self::MAX_STEPS;
        $steps = [];
        $previousVariables = []; // Store previous state for diff comparison

        /** @var list<string> $watches */
        $watches = $this->options['watches'] ?? [];
        $hasWatches = $watches !== [];
        /** @var array<string, string> $previousWatchValues */
        $previousWatchValues = [];

        // When watches are active, maxSteps limits recorded steps (not executed steps)
        // Use a separate safety cap for executed steps to prevent infinite loops
        $maxExecutedSteps = $hasWatches ? $maxSteps * 10 : $maxSteps;

        $watchInfo = $hasWatches ? ' with watch filtering (' . count($watches) . ' expressions)' : '';
        $this->log("🚶 Starting step-by-step execution trace with differential recording (max {$maxSteps} steps){$watchInfo}");

        while (true) {
            $stepCount++;

            // Get current position and variables
            $this->log("--- Step {$stepCount} ---");

            try {
                $stackResponse = $this->getStack();
            } catch (Throwable $e) {
                $this->log('⚠️ Error getting stack: ' . $e->getMessage());
                break;
            }

            $stackFrames = $this->parseStackFrames($stackResponse);
            if ($stackFrames === []) {
                $this->log('⚠️ No stack info available, execution may have completed');
                break;
            }

            $topFrame = $stackFrames[0];
            $location = [
                'file' => $topFrame['file'],
                'line' => $topFrame['line'],
            ];
            $breakpoint = $this->breakpointReferenceForLocation($location);

            // Get current variables
            $currentVariables = $this->getCurrentVariables();

            // Evaluate watch expressions if active
            $watchData = [];
            $watchChanged = false;
            if ($hasWatches) {
                $currentWatchValues = [];
                foreach ($watches as $expr) {
                    $currentWatchValues[$expr] = $this->evaluateWatchExpression($expr);
                }

                if ($stepCount === 1) {
                    // First step: always record with "initial" reason
                    $watchChanged = true;
                    foreach ($currentWatchValues as $expr => $value) {
                        $watchData[] = $this->createWatchChange($expr, $value, null, 'initial');
                    }
                } else {
                    // Subsequent steps: compare with previous values
                    foreach ($currentWatchValues as $expr => $value) {
                        $previous = $previousWatchValues[$expr] ?? '<unavailable>';

                        // Transition from available to unavailable → out_of_scope
                        if ($value === '<unavailable>' && $previous !== '<unavailable>') {
                            $watchChanged = true;
                            $watchData[] = $this->createWatchChange($expr, $value, $previous, 'out_of_scope');
                            continue;
                        }

                        // Skip if both unavailable
                        if ($value === '<unavailable>') {
                            continue;
                        }

                        // Value changed
                        if ($value === $previous) {
                            continue;
                        }

                        $watchChanged = true;
                        $watchData[] = $this->createWatchChange(
                            $expr,
                            $value,
                            $previous !== '<unavailable>' ? $previous : null,
                            'changed',
                        );
                    }
                }

                $previousWatchValues = $currentWatchValues;
            }

            // Implement differential recording (like video compression)
            if ($stepCount === 1) {
                // First frame: record all variables (full state)
                $variablesToRecord = $currentVariables;
                $variableDiff = [];
                $recordingType = 'full';
                $previousVariables = $currentVariables;
            } else {
                // Subsequent frames: record only differences (diff state)
                $variableDiff = $this->buildVariableDiff($previousVariables, $currentVariables);
                $variablesToRecord = [];

                // Find new or changed variables
                foreach ($currentVariables as $name => $value) {
                    if (isset($previousVariables[$name]) && $previousVariables[$name] === $value) {
                        continue;
                    }

                    $variablesToRecord[$name] = $value;
                }

                // Find deleted variables
                foreach (array_keys($previousVariables) as $name) {
                    if (isset($currentVariables[$name])) {
                        continue;
                    }

                    $variablesToRecord[$name] = '[DELETED]';
                }

                $recordingType = 'diff';
                $previousVariables = $currentVariables;
            }

            // When watches are active, only record steps where watched values changed
            if ($hasWatches && ! $watchChanged) {
                $this->log("Step {$stepCount}: {$location['file']}:{$location['line']} (watch unchanged, skipped)");

                // Still step into next instruction even when skipping recording
                $this->log('👣 Step into...');
                $stepResponse = $this->stepInto();

                if ($this->isExecutionComplete($stepResponse)) {
                    $this->log("✅ Execution completed after {$stepCount} steps");
                    if ($this->jsonMode || ($this->options['jsonOutput'] ?? false)) {
                        array_push($this->breaks, ...$steps);
                        $this->outputStepRecordingResults();
                    }

                    break;
                }

                // Safety cap for executed steps (prevents infinite loops)
                if ($stepCount >= $maxExecutedSteps) {
                    $this->log("⚠️ Maximum executed steps ({$maxExecutedSteps}) reached, stopping execution");
                    if ($this->jsonMode || ($this->options['jsonOutput'] ?? false)) {
                        array_push($this->breaks, ...$steps);
                        $this->outputStepRecordingResults();
                    }

                    break;
                }

                continue;
            }

            // Record the step (breakpoint is identical across steps, captured once)
            $this->recordedBreakpoint ??= $breakpoint;
            $step = $this->createRecordedBreak(
                $stepCount,
                $stackFrames,
                $variablesToRecord,
                $recordingType,
                $variableDiff,
                $hasWatches ? $watchData : [],
            );
            $steps[] = $step;
            $recordedCount++;

            // Check if we've reached the recorded step limit AFTER recording the step
            if ($recordedCount >= $maxSteps) {
                $this->log("⚠️ Maximum recorded steps ({$maxSteps}) reached, stopping execution");

                // Output JSON results immediately when step limit is reached
                if ($this->jsonMode || ($this->options['jsonOutput'] ?? false)) {
                    array_push($this->breaks, ...$steps);
                    $this->outputStepRecordingResults();
                }

                break;
            }

            if ($this->jsonMode) {
                $changeCount = count($variablesToRecord);
                $type = $recordingType === 'full' ? 'full state' : 'changes only';
                $watchNote = $hasWatches ? ', watch changed' : '';
                $this->log("Step {$stepCount}: {$location['file']}:{$location['line']} ({$changeCount} variables, {$type}{$watchNote})");
            } else {
                $this->displayStackInfo($stackResponse);
                $title = $recordingType === 'full'
                    ? "Step {$stepCount} Variables (Full State)"
                    : "Step {$stepCount} Variables (Changes Only)";
                $this->displayVariableArray($variablesToRecord, $title);
            }

            // Step into next instruction
            $this->log('👣 Step into...');
            $stepResponse = $this->stepInto();

            // Check if execution completed
            if ($this->isExecutionComplete($stepResponse)) {
                $this->log("✅ Execution completed after {$stepCount} steps");

                // Output JSON results immediately when execution completes
                if ($this->jsonMode || ($this->options['jsonOutput'] ?? false)) {
                    array_push($this->breaks, ...$steps);
                    $this->outputStepRecordingResults();
                }

                break;
            }

            // Small delay for readability in interactive mode
            if ($this->jsonMode) {
                continue;
            }

            delay(0.1);
        }

        return $steps;
    }

    /** @return WatchChange */
    private function createWatchChange(string $expression, string $value, string|null $previous, string $reason): array
    {
        return [
            'expression' => $expression,
            'value' => $value,
            'previous' => $previous,
            'reason' => $reason,
        ];
    }

    /**
     * @param list<StackFrame>      $stackFrames
     * @param array<string, string> $variables
     * @param VariableDiff          $variableDiff
     * @param list<WatchChange>     $watchData
     *
     * @return DebugBreak
     */
    private function createRecordedBreak(
        int $step,
        array $stackFrames,
        array $variables,
        string $recordingType,
        array $variableDiff,
        array $watchData,
    ): array {
        // location and function are dropped: both duplicate stack[0]. breakpoint is
        // identical across steps and emitted once at the top level (see outputStepRecordingResults).
        $debugBreak = [
            'step' => $step,
            'stack' => array_slice($stackFrames, 0, self::STACK_CONTEXT_LIMIT),
            'recording_type' => $recordingType,
        ];

        // Full frames carry the complete snapshot; diff frames are reconstructable
        // from the `diff` entries, so the duplicate `variables` map is omitted.
        if ($recordingType !== 'diff') {
            $debugBreak['variables'] = $variables;
        }

        if ($variableDiff !== []) {
            $debugBreak['diff'] = $variableDiff;
        }

        if ($watchData !== []) {
            $debugBreak['watches'] = $watchData;
        }

        return $debugBreak;
    }

    /**
     * Process Step Recording in exit-on-break mode
     * Combines breakpoint processing with step recording
     */
    private function processStepRecordingBreakpoints(): void
    {
        $maxSteps = $this->options['maxSteps'] ?? self::MAX_STEPS;
        $this->log("🎬 exit-on-break mode with Step Recording ({$maxSteps} steps)");

        try {
            // Start execution and wait for first breakpoint
            $response = $this->sendCommand('run');

            if ($this->didBreak($response)) {
                $this->log('🎯 Breakpoint hit, starting Step Recording...');
                $breakLocation = $this->extractLocationDataFromBreakResponse($response);
                $this->activeBreakpoint = $breakLocation !== null
                    ? $this->resolveBreakpointForLocation($breakLocation)
                    : null;

                // Perform step recording from the breakpoint
                $steps = $this->performStepTrace();

                // Store results for JSON output
                $this->breaks = array_merge($this->breaks, $steps);

                $this->log('✅ Step Recording completed: ' . count($steps) . ' steps recorded');

                // JSON output will be handled by performStepTrace() when needed
            } else {
                $this->log('⚠️ No breakpoint hit, execution completed normally');
            }
        } catch (Throwable $e) {
            $this->log('❌ Step Recording error: ' . $e->getMessage());
        }
    }

    /**
     * Send a DBGp command and wait for the response
     *
     * @param array<string, string|int> $params
     */
    private function sendCommand(string $command, array $params = []): string
    {
        if (! $this->isConnected() || ! $this->xdebugSocket instanceof Socket) {
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
    public function isConnected(): bool
    {
        return $this->xdebugSocket instanceof Socket
            && ! $this->xdebugSocket->isClosed()
            && $this->xdebugSocket->isWritable();
    }

    /**
     * Normalise a path by resolving . and .. segments.
     * Compatible with phar:// and other stream wrappers unlike realpath().
     */
    private function normalisePath(string $path): string
    {
        return PathNormalizer::normalise($path);
    }

    /**
     * Convert file path to properly encoded file:// URI
     */
    private function toFileUri(string $path): string
    {
        $real = $this->normalisePath($path);

        // Handle Windows paths
        if (DIRECTORY_SEPARATOR === '\\') {
            $real = str_replace('\\', '/', $real);
            if (preg_match('/^([a-zA-Z]):\/(.*)$/', $real, $m)) {
                $drive = strtoupper($m[1]) . ':/';
                $rest = $m[2];
                $parts = explode('/', $rest);
                $encoded = array_map(rawurlencode(...), $parts);

                return 'file:///' . $drive . implode('/', $encoded);
            }
        }

        // POSIX: encode each segment
        $parts = explode('/', ltrim($real, '/'));
        $encoded = array_map(rawurlencode(...), $parts);

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
        if (! isset($this->options['breakpoints'])) {
            return;
        }

        $this->configuredBreakpoints = [];
        $index = 0;
        foreach ($this->options['breakpoints'] as $breakpoint) {
            $index++;
            $file = $breakpoint['file'];
            $line = (int) $breakpoint['line'];
            $condition = $breakpoint['condition'] ?? null;

            // Set the breakpoint with condition
            $breakpointId = $this->setBreakpoint($file, $line, $condition);

            if ($breakpointId !== 'error') {
                $breakpointMetadata = [
                    'id' => $breakpointId,
                    'label' => $this->makeBreakpointLabel($file, $line, $condition, $index),
                    'file' => $file,
                    'line' => $line,
                ];
                if ($condition !== null && $condition !== '') {
                    $breakpointMetadata['condition'] = $condition;
                }

                $this->configuredBreakpoints[] = $breakpointMetadata;

                continue;
            }

            $this->log("❌ Failed to set breakpoint: {$file}:{$line}");
        }
    }

    private function makeBreakpointLabel(string $file, int $line, string|null $condition, int $index): string
    {
        $label = "bp{$index} " . basename($file) . ":{$line}";
        if ($condition !== null && $condition !== '') {
            $label .= " if {$condition}";
        }

        return $label;
    }

    /**
     * @param array{file: string, line: int} $location
     *
     * @return array{id: string, label: string}
     */
    private function breakpointReferenceForLocation(array $location): array
    {
        $breakpoint = $this->activeBreakpoint ?? $this->resolveBreakpointForLocation($location);
        if ($breakpoint !== null) {
            return [
                'id' => $breakpoint['id'],
                'label' => $breakpoint['label'],
            ];
        }

        return [
            'id' => 'unknown',
            'label' => "{$location['file']}:{$location['line']}",
        ];
    }

    /**
     * @param array{file: string, line: int} $location
     *
     * @return array{id: string, label: string, file: string, line: int, condition?: string}|null
     */
    private function resolveBreakpointForLocation(array $location): array|null
    {
        foreach ($this->configuredBreakpoints as $breakpoint) {
            if ((int) $breakpoint['line'] !== $location['line']) {
                continue;
            }

            if (basename($breakpoint['file']) !== $location['file']) {
                continue;
            }

            return $breakpoint;
        }

        return null;
    }

    /**
     * Continue execution
     */
    private function continueExecution(): string
    {
        $this->log('🔄 Sending continue command...');
        $response = $this->sendCommand('run');

        if ($response !== '' && $response !== '0') {
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
        if ($response !== '' && $response !== '0') {
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
        if ($response !== '' && $response !== '0') {
            $this->log('✅ Step into completed');
        }

        return $response;
    }

    /**
     * Step out
     */
    private function stepOut(): string
    {
        $response = $this->sendCommand('step_out');
        if ($response !== '' && $response !== '0') {
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
     * Evaluate a watch expression and return its string value
     *
     * @return string The evaluated value as a string, or '<unavailable>' on error
     */
    private function evaluateWatchExpression(string $expression): string
    {
        try {
            $response = $this->evaluateExpression($expression);
            if ($response === '' || str_contains($response, '<error')) {
                return '<unavailable>';
            }

            $xml = $this->parseXmlResponse($response);
            if (! $xml) {
                return '<unavailable>';
            }

            // Handle property_get response
            $prop = $xml->property ?? ($xml->children()[0] ?? null);
            if ($prop === null) {
                return '<unavailable>';
            }

            $encoding = (string) ($prop['encoding'] ?? '');
            $value = (string) $prop;

            if ($encoding === 'base64') {
                $value = base64_decode($value);
            }

            $type = (string) ($prop['type'] ?? 'string');

            // Format the value with type context
            if ($type === 'string') {
                return $this->truncateVariableDisplay("'" . $value . "'");
            }

            if ($type === 'null') {
                return 'null';
            }

            if ($type === 'bool') {
                return $value === '1' || strtolower($value) === 'true' ? 'true' : 'false';
            }

            if ($type === 'array' || $type === 'object') {
                // Use content hash to detect internal mutations
                $contentHash = $this->getWatchContentHash($expression);
                if ($contentHash !== null) {
                    if ($type === 'array') {
                        $numChildren = (string) ($prop['numchildren'] ?? '0');

                        return 'array(' . $numChildren . ')#' . $contentHash;
                    }

                    $className = (string) ($prop['classname'] ?? 'object');

                    return 'object: ' . $className . '#' . $contentHash;
                }

                // Fallback without hash
                if ($type === 'array') {
                    $numChildren = (string) ($prop['numchildren'] ?? '0');

                    return 'array(' . $numChildren . ')';
                }

                $className = (string) ($prop['classname'] ?? 'object');

                return 'object: ' . $className;
            }

            // int, float, or other types
            return $value !== '' ? $value : '<unavailable>';
        } catch (Throwable) {
            return '<unavailable>';
        }
    }

    /**
     * Get a content-based hash for array/object watch expressions
     *
     * Uses json_encode via eval to detect internal mutations.
     *
     * @return string|null Short hash string, or null if unavailable
     */
    private function getWatchContentHash(string $expression): string|null
    {
        try {
            // Use property_get to retrieve child elements (depth 0 = current frame)
            $response = $this->sendCommand('property_get', ['n' => $expression, 'd' => '0']);
            if ($response === '' || str_contains($response, '<error')) {
                return null;
            }

            $xml = $this->parseXmlResponse($response);
            if (! $xml) {
                return null;
            }

            $prop = $xml->property ?? ($xml->children()[0] ?? null);
            if ($prop === null) {
                return null;
            }

            // Build a content signature from child properties
            $signature = '';
            foreach ($prop->property as $child) {
                $childName = (string) ($child['name'] ?? '');
                $childValue = (string) $child;
                if ((string) ($child['encoding'] ?? '') === 'base64') {
                    $childValue = base64_decode($childValue);
                }

                $signature .= $childName . '=' . $childValue . ';';
            }

            if ($signature === '') {
                return null;
            }

            // Short hash (8 chars) for compact output
            return substr(md5($signature), 0, 8);
        } catch (Throwable) {
            return null;
        }
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
        if (! $xml || (! property_exists($xml, 'property') || $xml->property === null)) {
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
     * Enable HTTP API mode instead of interactive console
     */
    public function enableHttpMode(int|null $httpPort = null): void
    {
        $this->httpMode = true;
        $port = $httpPort ?: $this->debugPort + 100; // Default: debug port + 100

        // Create simple logger for AMP SocketHttpServer
        $logger = new class implements LoggerInterface {
            public function emergency(string|Stringable $message, array $context = []): void
            {
                $this->log('EMERGENCY', $message, $context);
            }

            public function alert(string|Stringable $message, array $context = []): void
            {
                $this->log('ALERT', $message, $context);
            }

            public function critical(string|Stringable $message, array $context = []): void
            {
                $this->log('CRITICAL', $message, $context);
            }

            public function error(string|Stringable $message, array $context = []): void
            {
                $this->log('ERROR', $message, $context);
            }

            public function warning(string|Stringable $message, array $context = []): void
            {
                $this->log('WARNING', $message, $context);
            }

            public function notice(string|Stringable $message, array $context = []): void
            {
                $this->log('NOTICE', $message, $context);
            }

            public function info(string|Stringable $message, array $context = []): void
            {
                $this->log('INFO', $message, $context);
            }

            public function debug(string|Stringable $message, array $context = []): void
            {
                $this->log('DEBUG', $message, $context);
            }

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $levelStr = is_string($level) ? $level : (is_int($level) || is_float($level) ? (string) $level : 'UNKNOWN');
                fwrite(STDERR, "[HTTP-Server] [{$levelStr}] {$message}\n");
            }
        };

        // Create HTTP server
        $this->httpServer = SocketHttpServer::createForDirectAccess($logger);
        $this->httpServer->expose("127.0.0.1:{$port}");

        $this->log("🌐 HTTP API enabled on port {$port}");
    }

    /**
     * Start interactive debugging session - console or HTTP mode
     */
    private function startInteractiveSession(): void
    {
        if ($this->httpMode) {
            $this->startHttpSession();
        } else {
            $this->startConsoleSession();
        }
    }

    /**
     * Start HTTP-based debugging session
     */
    private function startHttpSession(): void
    {
        $this->log('🌐 Starting HTTP debugging session');
        $this->log('Available endpoints: /debug/step, /debug/variables, /debug/continue, /debug/quit');

        $requestHandler = $this->createHttpRequestHandler();
        $errorHandler = new DefaultErrorHandler();
        $this->httpServer?->start($requestHandler, $errorHandler);

        // Keep server running (until quit is called)
        while ($this->httpServer && ! $this->shouldExit) {
            delay(0.1);
        }
    }

    /**
     * Start console-based debugging session (original)
     */
    private function startConsoleSession(): void
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
            if ($command === '') {
                continue;
            }

            if ($this->executeUserCommand($command)) {
                $this->outputTraceFile();
                $this->gracefulExit(0);

                return; // Never reached, but explicit for readability
            }
        }
    }

    /**
     * Create HTTP request handler for debug API
     */
    private function createHttpRequestHandler(): RequestHandler
    {
        return new class ($this) implements RequestHandler {
            public function __construct(private readonly DebugServer $debugServer)
            {
            }

            public function handleRequest(Request $request): Response
            {
                $path = $request->getUri()->getPath();
                $method = $request->getMethod();

                // CORS headers for development
                $headers = [
                    'Content-Type' => 'application/json',
                    'Access-Control-Allow-Origin' => '*',
                    'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Content-Type',
                ];

                // Handle OPTIONS preflight
                if ($method === 'OPTIONS') {
                    return new Response(HttpStatus::OK, $headers, '');
                }

                try {
                    $result = match ($path) {
                        '/debug/step' => $this->handleDebugStep(),
                        '/debug/over' => $this->handleDebugOver(),
                        '/debug/out' => $this->handleDebugOut(),
                        '/debug/continue' => $this->handleDebugContinue(),
                        '/debug/variables' => $this->handleDebugVariables($request),
                        '/debug/backtrace' => $this->handleDebugBacktrace(),
                        '/debug/quit' => $this->handleDebugQuit(),
                        '/debug/status' => $this->handleDebugStatus(),
                        default => [
                            'error' => 'Endpoint not found',
                            'available' => [
                                '/debug/step',
                                '/debug/over',
                                '/debug/out',
                                '/debug/continue',
                                '/debug/variables',
                                '/debug/backtrace',
                                '/debug/quit',
                                '/debug/status',
                            ],
                        ],
                    };

                    return new Response(
                        HttpStatus::OK,
                        $headers,
                        json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
                    );
                } catch (Throwable $e) {
                    $error = [
                        'error' => $e->getMessage(),
                        'endpoint' => $path,
                        'method' => $method,
                    ];

                    return new Response(
                        HttpStatus::INTERNAL_SERVER_ERROR,
                        $headers,
                        json_encode($error, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
                    );
                }
            }

            /** @return array{command: string, success: bool, location: string, should_exit: bool} */
            private function handleDebugStep(): array
            {
                $success = $this->debugServer->handleStepCommand();

                return [
                    'command' => 'step',
                    'success' => $success,
                    'location' => $this->debugServer->getCurrentLocation(),
                    'should_exit' => $success,
                ];
            }

            /** @return array{command: string, success: bool, location: string, should_exit: bool} */
            private function handleDebugOver(): array
            {
                $success = $this->debugServer->handleStepOverCommand();

                return [
                    'command' => 'over',
                    'success' => $success,
                    'location' => $this->debugServer->getCurrentLocation(),
                    'should_exit' => $success,
                ];
            }

            /** @return array{command: string, success: bool, location: string, should_exit: bool} */
            private function handleDebugOut(): array
            {
                $success = $this->debugServer->handleStepOutCommand();

                return [
                    'command' => 'out',
                    'success' => $success,
                    'location' => $this->debugServer->getCurrentLocation(),
                    'should_exit' => $success,
                ];
            }

            /** @return array{command: string, success: bool, location: string, should_exit: bool} */
            private function handleDebugContinue(): array
            {
                $success = $this->debugServer->handleContinueCommand();

                return [
                    'command' => 'continue',
                    'success' => $success,
                    'location' => $this->debugServer->getCurrentLocation(),
                    'should_exit' => $success,
                ];
            }

            /** @return array{command: string, variable: string, result: array<string, string>|string|null} */
            private function handleDebugVariables(Request $request): array
            {
                // Get variable name from query parameter or request body
                $query = $request->getUri()->getQuery();
                parse_str($query, $params);
                $rawVar = $params['var'] ?? '';
                $variable = is_string($rawVar) ? $rawVar : '';

                if ($variable !== '') {
                    // Get specific variable value using existing methods
                    $variables = $this->debugServer->getCurrentVariables();
                    $result = $variables[$variable] ?? null;
                } else {
                    // Get all variables
                    $result = $this->debugServer->getCurrentVariables();
                }

                return [
                    'command' => 'variables',
                    'variable' => $variable,
                    'result' => $result,
                ];
            }

            /** @return array{command: string, result: list<string>} */
            private function handleDebugBacktrace(): array
            {
                return [
                    'command' => 'backtrace',
                    'result' => $this->debugServer->getBacktraceResult(),
                ];
            }

            /** @return array{command: string, success: bool, message: string} */
            private function handleDebugQuit(): array
            {
                $this->debugServer->setShouldExit(true);

                return [
                    'command' => 'quit',
                    'success' => true,
                    'message' => 'Debug session terminated',
                ];
            }

            /** @return array{status: string, connected: bool, location: string, available_commands: list<string>} */
            private function handleDebugStatus(): array
            {
                return [
                    'status' => 'active',
                    'connected' => $this->debugServer->isConnected(),
                    'location' => $this->debugServer->getCurrentLocation(),
                    'available_commands' => ['step', 'over', 'out', 'continue', 'variables', 'backtrace', 'quit'],
                ];
            }
        };
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
            if (! $files) {
                continue;
            }

            array_push($allTraceFiles, ...$files);
        }

        if ($allTraceFiles !== []) {
            // Remove duplicates and sort by modification time, get the most recent
            $allTraceFiles = array_unique($allTraceFiles);
            usort($allTraceFiles, static fn ($a, $b): int => filemtime($b) - filemtime($a));
            $latestTrace = $allTraceFiles[0];
            // For exit-on-break mode: simple message with filename and size for AI analysis
            if ($this->options['traceOnly'] ?? false) {
                if (file_exists($latestTrace)) {
                    $fileLines = file($latestTrace, FILE_IGNORE_NEW_LINES);
                    $lines = $fileLines !== false ? count($fileLines) : 0;
                    $size = filesize($latestTrace);
                    $sizeKB = round($size / 1024, 1);
                    if ($this->options['jsonOutput'] ?? false) {
                        // JSON output for AI consumption
                        $commandParts = $this->options['command'] ?? ['php', $this->targetScript];
                        $escapedCommandParts = array_map(escapeshellarg(...), $commandParts);
                        $command = implode(' ', $escapedCommandParts);

                        echo json_encode([
                            'trace_file' => $latestTrace,
                            'lines' => $lines,
                            'size' => $sizeKB,
                            'command' => $command,
                        ], JSON_THROW_ON_ERROR) . "\n";
                    } else {
                        $this->log("📊 Trace file generated up to conditional breakpoint: {$latestTrace} ({$lines} lines, {$sizeKB}KB)");
                    }
                } elseif ($this->options['jsonOutput'] ?? false) {
                    echo json_encode([
                        'trace_file' => $latestTrace,
                        'lines' => 0,
                        'size' => 0,
                        'command' => implode(' ', $this->options['command'] ?? ['php', $this->targetScript]),
                    ], JSON_THROW_ON_ERROR) . "\n";
                } else {
                    $this->log("📊 Trace file generated up to conditional breakpoint: {$latestTrace}");
                }
            } else {
                // For interactive mode: show detailed info
                $this->log("📈 Trace file available: {$latestTrace}");
                if (file_exists($latestTrace)) {
                    $fileLines = file($latestTrace, FILE_IGNORE_NEW_LINES);
                    $lines = $fileLines !== false ? count($fileLines) : 0;
                    $size = filesize($latestTrace);
                    $this->log("📊 Trace contains {$lines} lines ({$size} bytes)");
                }

                $this->log('✅ Debug session complete');
            }
        } elseif (! ($this->options['traceOnly'] ?? false)) {
            $this->log('⚠️ No trace file found');
            $this->log('💡 Trace files are typically saved as /tmp/trace.*.xt');
            $this->log('✅ Debug session complete');
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
    public function handleStepCommand(): bool
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
                str_contains($e->getMessage(), 'Broken pipe')
                || str_contains($e->getMessage(), 'Connection closed')
                || str_contains($e->getMessage(), 'Failed to write to stream')
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
    public function handleStepOverCommand(): bool
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
                str_contains($e->getMessage(), 'Broken pipe')
                || str_contains($e->getMessage(), 'Connection closed')
                || str_contains($e->getMessage(), 'Failed to write to stream')
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
    public function handleStepOutCommand(): bool
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
                str_contains($e->getMessage(), 'Broken pipe')
                || str_contains($e->getMessage(), 'Connection closed')
                || str_contains($e->getMessage(), 'Failed to write to stream')
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
    public function handleContinueCommand(): bool
    {
        $this->log('▶️ Continuing execution...');
        $response = $this->continueExecution();

        // Check if we hit a breakpoint and finalize trace
        if ($this->didBreak($response)) {
            $path = $this->finalizeTraceOnBreak();
            if ($path !== null) {
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
        if ($variable === '') {
            $this->log('❌ Usage: p <variable_name>');

            return;
        }

        $this->log("🔍 Evaluating: {$variable}");
        try {
            $result = $this->evaluateExpression($variable);

            // Parse and format the result
            $xml = $this->parseXmlResponse($result);
            if ($xml instanceof SimpleXMLElement) {
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
        if (property_exists($xml, 'error') && $xml->error !== null) {
            $errorMsg = (string) $xml->error->message;
            $this->log("❌ Error: {$errorMsg}");

            return;
        }

        // Handle property response
        if (! property_exists($xml, 'property') || $xml->property === null) {
            return;
        }

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
            if ($stackInfo !== '') {
                $this->displayStackInfo($stackInfo);
            }
        } catch (Throwable $e) {
            $this->log('⚠️ Unable to get stack info: ' . $e->getMessage());
        }

        try {
            // Get and display variables
            $variables = $this->getVariables();
            if ($variables !== '') {
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
        if ($xmlResponse === '') {
            return;
        }

        $xml = $this->parseXmlResponse($xmlResponse);
        if (! $xml instanceof SimpleXMLElement) {
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
     * Display variables array with title (for step recording)
     *
     * @param array<string, string> $variables
     */
    private function displayVariableArray(array $variables, string $title): void
    {
        $this->log("📊 {$title}:");

        if ($variables === []) {
            $this->log('  (no variables)');

            return;
        }

        foreach ($variables as $name => $value) {
            if ($value === '[DELETED]') {
                $this->log("  🗑️  {$name} = [DELETED]");
            } else {
                $this->log("  📌 {$name} = {$value}");
            }
        }

        $this->log('');
    }

    /**
     * Display variables in readable format (from XML response)
     */
    private function displayVariables(string $xmlResponse): void
    {
        if ($xmlResponse === '') {
            return;
        }

        $xml = $this->parseXmlResponse($xmlResponse);
        if (! $xml instanceof SimpleXMLElement) {
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
        return match ($type) {
            'string' => '"' . $value . '"',
            'int', 'float' => $value,
            'bool' => $value === '1' ? 'true' : 'false',
            'null' => 'null',
            default => $value,
        };
    }

    /**
     * Strip XML 1.0 illegal control characters from a DBGp byte stream.
     *
     * Xdebug emits raw bytes (e.g., NUL inside anonymous-class names from PHP 8.3+)
     * and can also surface invalid numeric character references such as &#0;.
     * Both forms make libxml's strict parser reject the response.
     */
    private static function sanitizeDbgpXml(string $xml): string
    {
        $xml = preg_replace_callback(
            '/&#(x[0-9A-Fa-f]+|\d+);/',
            static function (array $matches): string {
                $value = $matches[1];
                $isHex = $value[0] === 'x';
                $digits = $isHex ? ltrim(substr($value, 1), '0') : ltrim($value, '0');
                $digits = $digits === '' ? '0' : $digits;

                if (strlen($digits) > ($isHex ? 6 : 7)) {
                    return '';
                }

                $codepoint = $isHex ? intval($digits, 16) : (int) $digits;

                return self::isXmlCharacter($codepoint) ? $matches[0] : '';
            },
            $xml,
        ) ?? $xml;

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml) ?? $xml;
    }

    private static function isXmlCharacter(int $codepoint): bool
    {
        return $codepoint === 0x09
            || $codepoint === 0x0A
            || $codepoint === 0x0D
            || ($codepoint >= 0x20 && $codepoint <= 0xD7FF)
            || ($codepoint >= 0xE000 && $codepoint <= 0xFFFD)
            || ($codepoint >= 0x10000 && $codepoint <= 0x10FFFF);
    }

    /**
     * Parse XML response safely without error suppression
     */
    private function parseXmlResponse(string $xmlString): SimpleXMLElement|null
    {
        if ($xmlString === '') {
            return null;
        }

        // Save current libxml error handling state
        $useErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = simplexml_load_string(self::sanitizeDbgpXml($xmlString));

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
        if ($response === '') {
            return true;  // Connection closed etc
        }

        // Check for DBGp response indicating completion (removed reason="ok")
        return str_contains($response, 'status="stopping"')
            || str_contains($response, 'status="stopped"');
    }

    /**
     * Read DBGp frame - Fixed version with proper argument order
     */
    private function readDbgpFrame(Socket $socket): string
    {
        $timeoutValue = $this->options['readTimeout'] ?? self::DEFAULT_STEP_TIMEOUT;
        // If timeout is 0, don't use timeout cancellation (wait indefinitely)
        $timeout = $timeoutValue > 0 ? new TimeoutCancellation($timeoutValue) : null;

        try {
            // Read length header until NULL byte
            // FIXED: Correct argument order - Cancellation first, then length
            $lengthStr = '';
            while (true) {
                $char = $timeout instanceof TimeoutCancellation ? $socket->read($timeout, 1) : $socket->read(null, 1);
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
                $chunk = $timeout instanceof TimeoutCancellation ? $socket->read($timeout, $remaining) : $socket->read(null, $remaining);
                if ($chunk === null || $chunk === '') {
                    throw new RuntimeException('Connection closed while reading response data');
                }

                $response .= $chunk;
                $remaining -= strlen($chunk);
            }

            // Read the trailing NULL byte
            // FIXED: Correct argument order
            $trailingNull = $timeout instanceof TimeoutCancellation ? $socket->read($timeout, 1) : $socket->read(null, 1);
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
     * Check for existing Xdebug sessions on our port (now session-key aware)
     */
    private function checkExistingSessions(): void
    {
        $command = sprintf('lsof -ti :%d 2>/dev/null', $this->debugPort);
        $pids = shell_exec($command);

        if (! $pids) {
            return;
        }

        $pidList = array_filter(explode("\n", trim($pids)));
        if ($pidList === []) {
            return;
        }

        $this->log(sprintf('🔌 Port %d shared with other sessions: %s', $this->debugPort, implode(', ', $pidList)));
        $this->log('🎯 Using session key "xdebug-mcp" for isolation');
        $this->log('💡 IDEs can use different session keys (PHPSTORM, vscode, etc.)');
    }

    /**
     * Graceful exit with proper cleanup
     */
    private function gracefulExit(int $code = 0): void
    {
        $this->log('🏁 Gracefully exiting with proper cleanup...');

        // Close Xdebug connection if active
        if ($this->xdebugSocket && ! $this->xdebugSocket->isClosed()) {
            $this->xdebugSocket->close();
        }

        // Perform emergency cleanup to ensure resources are freed
        $this->emergencyCleanup();

        // Exit with specified code
        exit($code);
    }

    /**
     * Emergency cleanup called by register_shutdown_function
     * This ensures cleanup even on abnormal termination
     */
    public function emergencyCleanup(): void
    {
        static $alreadyCalled = false;

        // Prevent multiple calls
        if ($alreadyCalled) {
            return;
        }

        $alreadyCalled = true;

        $this->log('🚨 Emergency cleanup triggered');
        $this->cleanup();

        // Only kill our own xdebug-mcp processes on abnormal termination
        // This preserves other sessions (IDE) using the same port with different keys
        $command = 'pkill -f "XDEBUG_SESSION=xdebug-mcp" 2>/dev/null || true';
        shell_exec($command);
    }

    /**
     * Cleanup resources
     */
    private function cleanup(): void
    {
        // Close server socket if still open
        if ($this->server instanceof ServerSocket) {
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

        // Output Step Recording results in JSON mode (always output even if no breaks hit)
        if ($this->jsonMode) {
            $this->outputStepRecordingResults();
        }

        $this->log('🧹 Cleanup completed');
    }

    /**
     * Encode xstep JSON output with user-selected formatting.
     *
     * @param XstepJsonOutput $result
     */
    private function encodeJsonOutput(array $result): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $pretty = ($this->options['pretty'] ?? false) === true;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($result, $flags);
        if (! $pretty) {
            return $json;
        }

        return preg_replace_callback(
            '/^( +)/m',
            static fn (array $matches): string => str_repeat(' ', (int) (strlen($matches[1]) / 2)),
            $json,
        ) ?? $json;
    }

    private function getMaxValueBytes(): int|null
    {
        $maxValueBytes = $this->options['maxValueBytes'] ?? null;

        return is_int($maxValueBytes) && $maxValueBytes > 0 ? $maxValueBytes : null;
    }

    private function getMaxDepth(): int|null
    {
        $maxDepth = $this->options['maxDepth'] ?? null;

        return is_int($maxDepth) && $maxDepth > 0 ? $maxDepth : null;
    }

    private function truncateStringValue(string $value, int|null $maxBytes): string
    {
        if ($maxBytes === null || strlen($value) <= $maxBytes) {
            return $value;
        }

        return mb_strcut($value, 0, $maxBytes, 'UTF-8') . "... (truncated, {$maxBytes} bytes)";
    }

    private function truncateVariableDisplay(string $value, int|null $maxBytes = null): string
    {
        return $this->truncateStringValue($value, $maxBytes ?? $this->getMaxValueBytes());
    }

    /**
     * Output Step Recording results in JSON format
     */
    private function outputStepRecordingResults(): void
    {
        if ($this->stepRecordingOutputDone) {
            return;
        }

        $this->stepRecordingOutputDone = true;

        $result = ['$schema' => 'https://koriym.github.io/xdebug-mcp/schemas/xstep.json'];

        if ($this->recordedBreakpoint !== null) {
            $result['breakpoint'] = $this->recordedBreakpoint;
        }

        $result['breaks'] = $this->breaks;

        // Preserve caller-provided context in JSON output
        if (($this->options['context'] ?? '') !== '') {
            $result['context'] = $this->options['context'];
        }

        // Add trace file if available - use the most recent trace file directly
        try {
            // Get all trace files sorted by modification time
            $allTraceFiles = array_merge(
                glob('/tmp/trace*.xt') ?: [],
                glob('/var/tmp/trace*.xt') ?: [],
                glob('/tmp/trace*.xt.gz') ?: [],
                glob('/var/tmp/trace*.xt.gz') ?: [],
            );

            // Debug: Add trace file search info to output
            if ($this->jsonMode && getenv('XDEBUG_DEBUG')) {
                $result['debug'] = [
                    'trace_files_found' => count($allTraceFiles),
                    'search_patterns' => ['/tmp/trace*.xt', '/var/tmp/trace*.xt', '/tmp/trace*.xt.gz', '/var/tmp/trace*.xt.gz'],
                    'latest_file' => $allTraceFiles !== [] ? $allTraceFiles[0] : null,
                ];
            }

            if ($allTraceFiles !== []) {
                // Sort by modification time (newest first)
                usort($allTraceFiles, static fn ($a, $b): int => filemtime($b) - filemtime($a));
                $latestTraceFile = $allTraceFiles[0];

                // Use XdebugTracer for comprehensive trace statistics
                $tracer = new XdebugTracer();
                $result['trace'] = $tracer->generateTraceStatistics($latestTraceFile);
            } else {
                // No trace files found - use token-optimized structure
                $result['trace'] = [
                    'file' => '',
                    'lines' => 0,
                    'functions' => 0,
                    'max_depth' => 0,
                    'db_queries' => 0,
                ];
            }
        } catch (Throwable $e) {
            // Error handling - still provide trace structure with error
            $result['trace'] = [
                'file' => '',
                'lines' => 0,
                'functions' => 0,
                'max_depth' => 0,
                'db_queries' => 0,
                'error' => $e->getMessage(),
            ];
        }

        echo $this->encodeJsonOutput($result) . "\n";
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
                    if (in_array(trim($line), ['', '0'], true)) {
                        continue;
                    }

                    $this->log('   ' . $line);
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
     *
     * @return array{target_script: string, debug_port: int, trace_file: string|null, breakpoint_line: int|null, current_variables?: array<string, string>, current_stack?: list<string>}
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
            if ($variables !== []) {
                $context['current_variables'] = $variables;
            }
        } catch (Throwable) {
            // Variables not available, continue without them
        }

        // Try to get stack trace
        try {
            $stack = $this->getStackTrace();
            if ($stack !== []) {
                $context['current_stack'] = $stack;
            }
        } catch (Throwable) {
            // Stack not available, continue without it
        }

        return $context;
    }

    /**
     * Build Claude analysis prompt with context
     *
     * @param array<string, string|int|array<array-key, string>|null> $context
     */
    private function buildClaudeAnalysisPrompt(array $context, string $userArgs): string
    {
        $targetScriptValue = $context['target_script'] ?? '';
        $targetScript = is_string($targetScriptValue) ? basename($targetScriptValue) : '';

        $prompt = "Analyze PHP debugging session for {$targetScript}:\n\n";

        // Add trace file analysis
        $traceFile = $context['trace_file'] ?? '';
        if (is_string($traceFile) && $traceFile !== '' && file_exists($traceFile)) {
            $prompt .= "## Trace Analysis\n";
            $prompt .= "Please analyze the execution trace: {$traceFile}\n\n";

            // Include last 20 lines of trace for context
            $traceLines = file($traceFile);
            if ($traceLines !== false && $traceLines !== []) {
                $lastLines = array_slice($traceLines, -20);
                $prompt .= "Recent trace data:\n```\n" . implode('', $lastLines) . "```\n\n";
            }
        }

        // Add current variables if available
        $currentVariables = $context['current_variables'] ?? [];
        if (is_array($currentVariables) && $currentVariables !== []) {
            $prompt .= "## Current Variables\n";
            foreach ($currentVariables as $var => $value) {
                $prompt .= "- \${$var} = {$value}\n";
            }

            $prompt .= "\n";
        }

        // Add breakpoint context
        $breakpointLineValue = $context['breakpoint_line'] ?? '';
        if (is_scalar($breakpointLineValue) && $breakpointLineValue !== '' && $breakpointLineValue !== 0) {
            $prompt .= "## Breakpoint Context\n";
            $prompt .= "Stopped at line {$breakpointLineValue} in {$targetScript}\n\n";
        }

        // Add user-specific analysis request
        if ($userArgs !== '' && $userArgs !== '0') {
            $prompt .= "## Specific Analysis Request\n";
            $prompt .= $userArgs . "\n\n";
        }

        $prompt .= "## Analysis Focus\n";
        $prompt .= "Please provide:\n";
        $prompt .= "1. Call chain analysis leading to current breakpoint\n";
        $prompt .= "2. Variable state analysis and any anomalies\n";
        $prompt .= "3. Root cause identification if this is a bug investigation\n";
        $prompt .= "4. Performance insights from trace data\n";

        return $prompt . "5. Suggested next debugging steps or code fixes\n";
    }

    /**
     * Get current variables from debugger session
     *
     * @return array<string, string>
     */
    public function getCurrentVariables(): array
    {
        try {
            // Send context_get command to get local variables
            $response = $this->sendCommand('context_get', ['c' => '0']); // Local context

            if ($response === '' || $response === '0') {
                return [];
            }

            $variables = [];
            $useErrors = libxml_use_internal_errors(true);
            libxml_clear_errors();
            $xml = simplexml_load_string(self::sanitizeDbgpXml($response));
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
            if ($xml && (property_exists($xml, 'property') && $xml->property !== null)) {
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
                                } elseif ($type === 'array') {
                                    // Fallback to basic info
                                    $variables[$name] = "array: [{$numChildren} items]";
                                } else {
                                    $className = (string) ($prop['classname'] ?? 'object');
                                    $variables[$name] = "object: {$className} [{$numChildren} properties]";
                                }
                            }
                        } else {
                            $variables[$name] = $type === 'array' ? 'array: []' : 'object: {}';
                        }
                    } else {
                        $value = $encoding === 'base64' ? base64_decode($raw) : $raw;
                        $variables[$name] = $this->truncateVariableDisplay("{$type}: {$value}");
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
    private function extractChildDetails(SimpleXMLElement $prop, string $type): string|null
    {
        try {
            // Check if property has child elements
            if (! property_exists($prop, 'property') || $prop->property === null) {
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
                    $displayValue = $this->truncateStringValue($value, $this->getMaxValueBytes() ?? self::DEFAULT_CHILD_VALUE_BYTES);
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
                if (count($items) < $maxItems) {
                    continue;
                }

                $totalCount = (int) ($prop['numchildren'] ?? 0);
                if ($totalCount > $maxItems) {
                    $items[] = "... ({$totalCount} total)";
                }

                break;
            }

            if ($items === []) {
                return null;
            }

            // Format based on type
            if ($type === 'array') {
                return '[' . implode(', ', $items) . ']';
            }

            return '{' . implode(', ', $items) . '}';
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get json_encode output for a variable using Xdebug eval
     */
    private function getJsonEncodeOutput(string $varName): string|null
    {
        if (! $this->xdebugSocket instanceof Socket) {
            return null;
        }

        try {
            $expression = $this->buildJsonEncodeExpression($varName);
            // Use proper DBGp protocol format: eval -- base64_encoded_data
            $transactionId = $this->getNextTransactionId();
            $fullCommand = "eval -i {$transactionId} -- " . base64_encode($expression) . "\0";
            $this->xdebugSocket->write($fullCommand);
            $response = $this->readDbgpFrame($this->xdebugSocket);

            if ($response === '' || $response === '0') {
                return null;
            }

            $useErrors = libxml_use_internal_errors(true);
            libxml_clear_errors();
            $xml = simplexml_load_string(self::sanitizeDbgpXml($response));
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
            if (! $xml || (! property_exists($xml, 'property') || $xml->property === null)) {
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
            $output = $this->truncateStringValue($output, $this->getMaxValueBytes() ?? self::DEFAULT_MAX_VALUE_BYTES);

            // Make it more readable by adding spaces after colons and commas
            $output = preg_replace('/([,:])\s*/', '$1 ', $output);

            return $output;
        } catch (Throwable) {
            return null;
        }
    }

    private function buildJsonEncodeExpression(string $varName): string
    {
        $maxDepth = $this->getMaxDepth();
        if ($maxDepth === null) {
            return "json_encode({$varName}, JSON_UNESCAPED_UNICODE)";
        }

        $maxValueBytes = $this->getMaxValueBytes();
        $maxValueBytesArg = $maxValueBytes === null ? 'null' : (string) $maxValueBytes;

        return '(static function (mixed $value, int $maxDepth, int|null $maxValueBytes): string|false {'
            . '$truncate = static function (string $text) use ($maxValueBytes): string {'
            . 'if ($maxValueBytes === null || strlen($text) <= $maxValueBytes) { return $text; }'
            . 'return substr($text, 0, $maxValueBytes) . "... (truncated, " . $maxValueBytes . " bytes)";'
            . '};'
            . '$normalize = static function (mixed $node, int $depth) use (&$normalize, $maxDepth, $truncate): mixed {'
            . 'if (is_string($node)) { return $truncate($node); }'
            . 'if ($depth >= $maxDepth) {'
            . 'if (is_array($node)) { return "[MAX_DEPTH array(" . count($node) . ")]"; }'
            . 'if (is_object($node)) { return "[MAX_DEPTH object:" . get_class($node) . "]"; }'
            . 'return $node;'
            . '}'
            . 'if (is_array($node)) { $out = []; foreach ($node as $key => $child) { $out[$key] = $normalize($child, $depth + 1); } return $out; }'
            . 'if (is_object($node)) { $out = ["__class" => get_class($node)]; foreach (get_object_vars($node) as $key => $child) { $out[$key] = $normalize($child, $depth + 1); } return $out; }'
            . 'return $node;'
            . '};'
            . 'return json_encode($normalize($value, 0), JSON_UNESCAPED_UNICODE);'
            . "})({$varName}, {$maxDepth}, {$maxValueBytesArg})";
    }

    /**
     * @param array<string, string> $previousVariables
     * @param array<string, string> $currentVariables
     *
     * @return VariableDiff
     */
    private function buildVariableDiff(array $previousVariables, array $currentVariables): array
    {
        $diff = [];
        foreach ($currentVariables as $name => $after) {
            if (! array_key_exists($name, $previousVariables)) {
                $diff[$name] = $this->createVariableDiffEntry('added', null, $after);

                continue;
            }

            $before = $previousVariables[$name];
            if ($before === $after) {
                continue;
            }

            $diff[$name] = $this->createVariableDiffEntry('changed', $before, $after);
        }

        foreach ($previousVariables as $name => $before) {
            if (array_key_exists($name, $currentVariables)) {
                continue;
            }

            $diff[$name] = $this->createVariableDiffEntry('removed', $before, null);
        }

        return $diff;
    }

    /** @return VariableDiffEntry */
    private function createVariableDiffEntry(string $change, string|null $before, string|null $after): array
    {
        $display = $after ?? $before ?? '';
        $parsed = $this->parseVariableDisplay($display);
        $entry = [
            'change' => $change,
            'type' => $parsed['type'],
        ];

        if ($before !== null) {
            $entry['before'] = $before;
        }

        if ($after !== null) {
            $entry['after'] = $after;
        }

        if ($before !== null && $after !== null) {
            $keyDiff = $this->buildShallowKeyDiff($before, $after);
            if ($keyDiff !== null) {
                $entry['keys'] = $keyDiff;
            }
        }

        return $entry;
    }

    /** @return array{type: string, value: mixed, structured: bool} */
    private function parseVariableDisplay(string $display): array
    {
        if (preg_match('/^(array|object):\s*(.*)$/s', $display, $matches) === 1) {
            try {
                $value = json_decode($matches[2], true, 512, JSON_THROW_ON_ERROR);
                if (is_array($value)) {
                    return [
                        'type' => $matches[1],
                        'value' => $value,
                        'structured' => true,
                    ];
                }
            } catch (Throwable) {
                // Fall through to scalar-style diff when compact rendering is not JSON.
            }

            return [
                'type' => $matches[1],
                'value' => $display,
                'structured' => false,
            ];
        }

        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*):/', $display, $matches) === 1) {
            return [
                'type' => $matches[1],
                'value' => $display,
                'structured' => false,
            ];
        }

        return [
            'type' => 'scalar',
            'value' => $display,
            'structured' => false,
        ];
    }

    /** @return array{added: array<string, string>, removed: array<string, string>, changed: array<string, array{before: string, after: string}>}|null */
    private function buildShallowKeyDiff(string $before, string $after): array|null
    {
        $beforeParsed = $this->parseVariableDisplay($before);
        $afterParsed = $this->parseVariableDisplay($after);
        if (! $beforeParsed['structured'] || ! $afterParsed['structured']) {
            return null;
        }

        if ($beforeParsed['type'] !== $afterParsed['type'] || ! is_array($beforeParsed['value']) || ! is_array($afterParsed['value'])) {
            return null;
        }

        $beforeMap = $this->buildShallowValueMap($this->normalizeShallowValueMap($beforeParsed['value']));
        $afterMap = $this->buildShallowValueMap($this->normalizeShallowValueMap($afterParsed['value']));
        $added = [];
        $removed = [];
        $changed = [];

        foreach ($afterMap as $key => $value) {
            if (! array_key_exists($key, $beforeMap)) {
                $added[$key] = $value;

                continue;
            }

            if ($beforeMap[$key] === $value) {
                continue;
            }

            $changed[$key] = [
                'before' => $beforeMap[$key],
                'after' => $value,
            ];
        }

        foreach ($beforeMap as $key => $value) {
            if (array_key_exists($key, $afterMap)) {
                continue;
            }

            $removed[$key] = $value;
        }

        if ($added === [] && $removed === [] && $changed === []) {
            return null;
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * @param ShallowJsonMap $value
     *
     * @return array<string, string>
     */
    private function buildShallowValueMap(array $value): array
    {
        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $this->stringifyShallowValue($item);
        }

        return $map;
    }

    /** @return ShallowJsonMap */
    private function normalizeShallowValueMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $normalized[$key] = $this->normalizeShallowChildMap($item);

                continue;
            }

            $normalized[$key] = is_scalar($item) || $item === null ? $item : $this->stringifyShallowValue($item);
        }

        return $normalized;
    }

    /** @return array<array-key, string|int|float|bool|null> */
    private function normalizeShallowChildMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = is_scalar($item) || $item === null ? $item : $this->stringifyShallowValue($item);
        }

        return $normalized;
    }

    private function stringifyShallowValue(mixed $value): string
    {
        if (is_array($value)) {
            return 'array(' . count($value) . ')';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return $this->truncateVariableDisplay((string) $value);
        }

        return 'unknown';
    }

    /**
     * Get current stack trace
     *
     * @return list<string>
     */
    private function getStackTrace(): array
    {
        try {
            $response = $this->sendCommand('stack_get');

            if ($response === '' || $response === '0') {
                return [];
            }

            $stack = [];
            foreach ($this->parseStackFrames($response) as $frame) {
                $stack[] = "{$frame['function']} at {$frame['file']}:{$frame['line']}";
            }

            return $stack;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Get trace file information (token-optimized)
     *
     * @return array{file: string, lines: int, functions: int, max_depth: int, db_queries: int}
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
            if (! $files) {
                continue;
            }

            array_push($allTraceFiles, ...$files);
        }

        if ($allTraceFiles === []) {
            return [
                'file' => '',
                'lines' => 0,
                'functions' => 0,
                'max_depth' => 0,
                'db_queries' => 0,
            ];
        }

        // Remove duplicates and sort by modification time, get the most recent
        $allTraceFiles = array_unique($allTraceFiles);
        usort($allTraceFiles, static fn ($a, $b): int => filemtime($b) - filemtime($a));

        $latestTrace = $allTraceFiles[0];

        // Use XdebugTracer for token-optimized statistics
        $tracer = new XdebugTracer();

        return $tracer->generateTraceStatistics($latestTrace);
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
        /** @var array<string, string>|null $previousVariables */
        $previousVariables = null;

        try {
            // Start execution
            $response = $this->sendCommand('run');

            while (! $this->isExecutionComplete($response) && $breakCount < $maxBreaks) {
                // Check execution time limit
                if (microtime(true) - $executionStartTime > $maxExecutionTime) {
                    $this->log('⚠️ Execution time limit reached (30s)');
                    break;
                }

                if ($this->didBreak($response)) {
                    $breakCount++;
                    $this->log("🎯 Breakpoint #{$breakCount} hit");

                    // Get current location from break response
                    $this->log('📋 Break response: ' . substr($response, 0, 200) . '...');
                    $currentLocation = $this->extractLocationFromBreakResponse($response);
                    $currentLocationData = $this->extractLocationDataFromBreakResponse($response);
                    $this->activeBreakpoint = $currentLocationData !== null
                        ? $this->resolveBreakpointForLocation($currentLocationData)
                        : null;

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
                    $debugState = $this->captureCurrentDebugState($breakCount, $this->activeBreakpoint);
                    if ($debugState !== null) {
                        if ($previousVariables !== null) {
                            $diff = $this->buildVariableDiff($previousVariables, $debugState['variables']);
                            if ($diff !== []) {
                                $debugState['diff'] = $diff;
                            }
                        }

                        $previousVariables = $debugState['variables'];
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
     *
     * @param array{id: string, label: string, file: string, line: int, condition?: string}|null $breakpoint
     *
     * @return array{step: int, stack: list<array{function: string, file: string, line: int}>, breakpoint: array{id: string, label: string}, variables: array<string, string>}|null
     */
    private function captureCurrentDebugState(int $breakNumber, array|null $breakpoint = null): array|null
    {
        try {
            $stackXml = $this->getStack();
            $this->log('📋 Stack info: ' . json_encode($stackXml, JSON_THROW_ON_ERROR));

            $variables = $this->getCurrentVariables();
            $this->log('📋 Variables: ' . json_encode($variables, JSON_THROW_ON_ERROR));

            // Parse stack XML to get current location
            $stackFrames = $this->parseStackFrames($stackXml);
            $topFrame = $stackFrames[0] ?? [
                'function' => '{main}',
                'file' => basename($this->targetScript),
                'line' => 1,
            ];
            $location = [
                'file' => $topFrame['file'],
                'line' => $topFrame['line'],
            ];

            // Keep the fallback frame in `stack` when XML parsing yields no frames,
            // so the machine-readable file/line is never lost (stack[0] is the contract).
            $stack = $stackFrames !== []
                ? array_slice($stackFrames, 0, self::STACK_CONTEXT_LIMIT)
                : [$topFrame];

            return [
                'step' => $breakNumber,
                'stack' => $stack,
                'breakpoint' => $breakpoint !== null
                    ? ['id' => $breakpoint['id'], 'label' => $breakpoint['label']]
                    : $this->breakpointReferenceForLocation($location),
                'variables' => $variables,
            ];
        } catch (Throwable $e) {
            $this->log('❌ Error in captureCurrentDebugState: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Parse stack XML response into compact frame data.
     *
     * @return list<array{function: string, file: string, line: int}>
     */
    private function parseStackFrames(string $stackXml): array
    {
        try {
            $useErrors = libxml_use_internal_errors(true);
            libxml_clear_errors();
            $xml = simplexml_load_string(self::sanitizeDbgpXml($stackXml));
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
            if (! $xml || (! property_exists($xml, 'stack') || $xml->stack === null)) {
                return [];
            }

            $frames = [];
            foreach ($xml->stack as $frame) {
                $filename = (string) ($frame['filename'] ?? '');
                $file = str_replace('file://', '', $filename);
                $function = (string) ($frame['where'] ?? '');
                $frames[] = [
                    'function' => $function !== '' ? $function : '{main}',
                    'file' => $file !== '' ? basename($file) : basename($this->targetScript),
                    'line' => (int) ($frame['lineno'] ?? 0),
                ];
            }

            return $frames;
        } catch (Throwable $e) {
            $this->log('❌ Error parsing stack frames: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Output results for multiple breakpoints
     *
     * @param list<DebugBreak> $breaks
     */
    private function outputMultipleBreakResults(array $breaks): void
    {
        $debugState = [
            '$schema' => 'https://koriym.github.io/xdebug-mcp/schemas/xstep.json',
            'breaks' => $breaks,
            'trace' => $this->getTraceInfo(),
        ];

        // Add context if provided
        if (isset($this->options['context']) && $this->options['context'] !== '') {
            $debugState['context'] = $this->options['context'];
        }

        // Output format based on jsonMode or jsonOutput option
        if ($this->jsonMode || ($this->options['jsonOutput'] ?? false)) {
            echo $this->encodeJsonOutput($debugState) . "\n";

            // Mark step-recording output as done only after JSON was successfully
            // emitted so the cleanup phase can still fall back to its own output
            // if encoding throws. See issue #67.
            $this->stepRecordingOutputDone = true;
        } else {
            // Human-readable format
            $this->log("\n" . str_repeat('=', 60));
            $this->log('🎯 MULTIPLE BREAKPOINTS DEBUG RESULT');
            $this->log(str_repeat('=', 60));

            foreach ($breaks as $break) {
                $frame = $break['stack'][0] ?? ['file' => '', 'line' => 0];
                $this->log("📍 Step {$break['step']}: {$frame['file']}:{$frame['line']}");

                $variables = $break['variables'] ?? [];
                if ($variables !== []) {
                    $this->log('📊 Variables:');
                    foreach ($variables as $name => $value) {
                        $this->log("  {$name} = {$value}");
                    }
                }

                $this->log('');
            }

            if ($debugState['trace']['file'] !== '') {
                $this->log("📈 Trace file: {$debugState['trace']['file']}");
                $this->log("📊 Trace lines: {$debugState['trace']['lines']}");
            }
        }
    }

    /**
     * Extract location information from break response
     */
    private function extractLocationFromBreakResponse(string $response): string
    {
        $location = $this->extractLocationDataFromBreakResponse($response);
        if ($location !== null) {
            return "{$location['file']}:{$location['line']}";
        }

        return 'unknown:0';
    }

    /**
     * Extract structured location information from break response.
     *
     * @return array{file: string, line: int}|null
     */
    private function extractLocationDataFromBreakResponse(string $response): array|null
    {
        try {
            $useErrors = libxml_use_internal_errors(true);
            libxml_clear_errors();
            $xml = simplexml_load_string(self::sanitizeDbgpXml($response));
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
            if ($xml) {
                // Register xdebug namespace
                $xml->registerXPathNamespace('xdebug', 'https://xdebug.org/dbgp/xdebug');
                $messages = $xml->xpath('//xdebug:message');

                if (is_array($messages) && $messages !== []) {
                    $message = $messages[0];
                    $filename = (string) $message['filename'];
                    $lineno = (string) $message['lineno'];
                    // Remove file:// prefix if present
                    $filename = str_replace('file://', '', $filename);

                    return [
                        'file' => basename($filename),
                        'line' => (int) $lineno,
                    ];
                }
            }
        } catch (Throwable $e) {
            $this->log('❌ Error parsing break response: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get current debugging location (file:line)
     */
    public function getCurrentLocation(): string
    {
        try {
            if (! $this->isConnected()) {
                return 'not_connected:0';
            }

            // Send status command to get current location
            $response = $this->sendCommand('status -i ' . $this->getNextTransactionId());

            return $this->extractLocationFromBreakResponse($response);
        } catch (Throwable $e) {
            $this->log('❌ Error getting current location: ' . $e->getMessage());

            return 'error:0';
        }
    }

    /**
     * Storage for backtrace results
     *
     * @var list<string>
     */
    private array $backtraceResult = [];

    /**
     * Get backtrace result by using existing getStackTrace method
     *
     * @return list<string>
     */
    public function getBacktraceResult(): array
    {
        try {
            $this->backtraceResult = $this->getStackTrace();

            return $this->backtraceResult;
        } catch (Throwable $e) {
            $this->log('❌ Error getting backtrace: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Set the should exit flag for clean shutdown
     */
    public function setShouldExit(bool $exit): void
    {
        $this->shouldExit = $exit;
    }
}
