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
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function ltrim;
use function md5;
use function microtime;
use function parse_str;
use function preg_match;
use function preg_replace;
use function property_exists;
use function rawurlencode;
use function register_shutdown_function;
use function round;
use function shell_exec;
use function simplexml_load_string;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function time;
use function trim;
use function usort;

use const DIRECTORY_SEPARATOR;
use const FILE_IGNORE_NEW_LINES;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
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
 * @see https://xdebug.org/docs/step_debug
 * @see https://xdebug.org/docs/dbgp
 */
final class DebugServer
{
    private const DEFAULT_CONNECTION_TIMEOUT = 30.0;  // Initial connection only
    private const DEFAULT_EXECUTION_TIMEOUT = 3600.0;  // 1 hour for long debugging sessions
    private const DEFAULT_STEP_TIMEOUT = 0.0;  // No timeout for interactive debugging
    private const MAX_STEPS = 100;  // Default maximum steps for step recording

    /** @var DeferredFuture<bool>|null */
    private DeferredFuture|null $listenerReady = null;

    /** @var DeferredFuture<bool>|null */
    private DeferredFuture|null $xdebugConnected = null;
    private Socket|null $xdebugSocket = null;
    private DbgpClient|null $dbgpClient = null;
    private ServerSocket|null $server = null;
    private Process|null $process = null;
    private string|null $traceFile = null;
    private SocketHttpServer|null $httpServer = null;
    private bool $httpMode = false;
    private bool $shouldExit = false;
    private readonly DebugResultFormatter $resultFormatter;
    private readonly ClaudeTraceAnalyzer $claudeTraceAnalyzer;
    private readonly int $sessionStartTime;

    /** @var list<array{step: int, location: array{file: string, line: int}, variables: array<string, string>, recording_type: string}> */
    private array $breaks = [];
    private bool $isDockerCommand = false;
    private bool $stepRecordingOutputDone = false;

    /** @param array{command?: list<string>, context?: string, breakpoint?: string, steps?: int, connectionTimeout?: float, executionTimeout?: float, traceOnly?: bool, maxSteps?: int, jsonOutput?: bool, breakpoints?: list<array{file: string, line: int|string, condition?: string}>, readTimeout?: float, watches?: list<string>} $options */
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
        $this->resultFormatter = new DebugResultFormatter();
        $this->claudeTraceAnalyzer = new ClaudeTraceAnalyzer();
        // Capture session start so trace lookups can filter out stale files from prior runs.
        $this->sessionStartTime = time();

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
            $dbgpClient = new DbgpClient(
                $socket,
                (float) ($this->options['readTimeout'] ?? self::DEFAULT_STEP_TIMEOUT),
                fn (string $message) => $this->log($message),
            );
            $this->dbgpClient = $dbgpClient;

            // Close server socket after accepting connection
            $this->server?->close();
            $this->server = null;

            // Read init packet
            $this->log('📨 Reading initial Xdebug packet...');
            try {
                $initData = $dbgpClient->readFrame();
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
                    $prependFilter = __DIR__ . '/../prepend_filter.php';

                    // Get appropriate Xdebug flag (empty if already loaded)
                    $xdebugFlag = XdebugFinder::getXdebugFlag();
                    $xdebugPart = $xdebugFlag !== '' ? $xdebugFlag . ' ' : '';

                    $cmd = sprintf(
                        'XDEBUG_SESSION=xdebug-mcp php %s'
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
                $prependFilter = __DIR__ . '/../prepend_filter.php';

                // Get appropriate Xdebug flag (empty if already loaded)
                $xdebugFlag = XdebugFinder::getXdebugFlag();
                $xdebugPart = $xdebugFlag !== '' ? $xdebugFlag . ' ' : '';

                $cmd = sprintf(
                    'XDEBUG_SESSION=xdebug-mcp php %s'
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
     * @return list<array{step: int, location: array{file: string, line: int}, variables: array<string, string>, recording_type: string}>
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

            $stackInfo = $this->getStackTrace();
            if ($stackInfo === []) {
                $this->log('⚠️ No stack info available, execution may have completed');
                break;
            }

            // Get current location from stack info or XML response
            $location = ['file' => 'unknown', 'line' => 0];
            $stackResponse = '';
            try {
                $stackResponse = $this->sendCommand('stack_get');
                if ($stackResponse !== '' && $stackResponse !== '0') {
                    $xml = simplexml_load_string($stackResponse);
                    if ($xml && isset($xml->stack[0])) {
                        $topFrame = $xml->stack[0];
                        $filename = (string) $topFrame['filename'];
                        $lineno = (string) $topFrame['lineno'];
                        if ($filename && $lineno) {
                            $location = [
                                'file' => basename($filename),
                                'line' => (int) $lineno,
                            ];
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->log('⚠️ Error getting location: ' . $e->getMessage());
            }

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
                        $watchData[] = [
                            'expression' => $expr,
                            'value' => $value,
                            'previous' => null,
                            'reason' => 'initial',
                        ];
                    }
                } else {
                    // Subsequent steps: compare with previous values
                    foreach ($currentWatchValues as $expr => $value) {
                        $previous = $previousWatchValues[$expr] ?? '<unavailable>';

                        // Transition from available to unavailable → out_of_scope
                        if ($value === '<unavailable>' && $previous !== '<unavailable>') {
                            $watchChanged = true;
                            $watchData[] = [
                                'expression' => $expr,
                                'value' => $value,
                                'previous' => $previous,
                                'reason' => 'out_of_scope',
                            ];
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
                        $watchData[] = [
                            'expression' => $expr,
                            'value' => $value,
                            'previous' => $previous !== '<unavailable>' ? $previous : null,
                            'reason' => 'changed',
                        ];
                    }
                }

                $previousWatchValues = $currentWatchValues;
            }

            // Implement differential recording (like video compression)
            if ($stepCount === 1) {
                // First frame: record all variables (full state)
                $variablesToRecord = $currentVariables;
                $recordingType = 'full';
                $previousVariables = $currentVariables;
            } else {
                // Subsequent frames: record only differences (diff state)
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

            // Record the step
            $step = [
                'step' => $stepCount,
                'location' => $location,
                'variables' => $variablesToRecord,
                'recording_type' => $recordingType,
            ];

            if ($hasWatches && $watchData !== []) {
                $step['watches'] = $watchData;
            }

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
    private function sendCommand(string $command, array $params = [], string|null $data = null): string
    {
        if (! $this->dbgpClient instanceof DbgpClient) {
            throw new RuntimeException('No active Xdebug connection');
        }

        return $this->dbgpClient->sendCommand($command, $params, $data);
    }

    /**
     * Check if connected to Xdebug
     */
    public function isConnected(): bool
    {
        return $this->dbgpClient instanceof DbgpClient && $this->dbgpClient->isConnected();
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

        foreach ($this->options['breakpoints'] as $breakpoint) {
            $file = $breakpoint['file'];
            $line = (int) $breakpoint['line'];
            $condition = $breakpoint['condition'] ?? null;

            // Set the breakpoint with condition
            $breakpointId = $this->setBreakpoint($file, $line, $condition);

            if ($breakpointId !== 'error') {
                continue;
            }

            $this->log("❌ Failed to set breakpoint: {$file}:{$line}");
        }
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
        return $this->sendCommand('eval', [], base64_encode($expression));
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
                return "'" . $value . "'";
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
        $resp = $this->sendCommand('eval', [], $code);
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
        $this->sendCommand('eval', [], $restart);

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
     * Parse XML response safely without error suppression
     */
    private function parseXmlResponse(string $xmlString): SimpleXMLElement|null
    {
        return DbgpClient::parseXmlResponse($xmlString, fn (string $message) => $this->log($message));
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

        $this->dbgpClient = null;

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

        $this->dbgpClient = null;

        // Output Step Recording results in JSON mode (always output even if no breaks hit)
        if ($this->jsonMode) {
            $this->outputStepRecordingResults();
        }

        $this->log('🧹 Cleanup completed');
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

        $scriptName = basename($this->targetScript, '.php');
        $patterns = [
            '/tmp/trace-*-' . $scriptName . '.xt',
            '/tmp/trace-*-' . $scriptName . '.xt.gz',
            '/var/tmp/trace-*-' . $scriptName . '.xt',
            '/var/tmp/trace-*-' . $scriptName . '.xt.gz',
        ];
        $allTraceFiles = $this->filterTraceFilesToSession($this->findTraceFiles($patterns));

        $payload = $this->resultFormatter->buildBreakpointPayload(
            $this->breaks,
            $this->buildTraceInfoFromFiles($allTraceFiles),
            (string) ($this->options['context'] ?? ''),
        );

        if ($this->jsonMode && getenv('XDEBUG_DEBUG')) {
            $payload['debug'] = [
                'trace_files_found' => count($allTraceFiles),
                'search_patterns' => $patterns,
                'latest_file' => $allTraceFiles[0] ?? null,
            ];
        }

        $this->resultFormatter->emit($payload, true, fn (string $message) => $this->log($message));
    }

    /**
     * Handle Claude analysis command
     */
    private function handleClaudeCommand(string $args): void
    {
        try {
            $this->claudeTraceAnalyzer->analyze(
                $this->getCurrentDebugContext(),
                $args,
                fn (string $message) => $this->log($message),
            );
        } catch (Throwable $e) {
            $this->log('❌ Claude analysis error: ' . $e->getMessage());
        }
    }

    /**
     * Get current debug context for external analysis adapters.
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

        try {
            $variables = $this->getCurrentVariables();
            if ($variables !== []) {
                $context['current_variables'] = $variables;
            }
        } catch (Throwable) {
        }

        try {
            $stack = $this->getStackTrace();
            if ($stack !== []) {
                $context['current_stack'] = $stack;
            }
        } catch (Throwable) {
        }

        return $context;
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
            $xml = simplexml_load_string($response);
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
        if (! $this->isConnected()) {
            return null;
        }

        try {
            $expression = "json_encode({$varName}, JSON_UNESCAPED_UNICODE)";
            $response = $this->sendCommand('eval', [], base64_encode($expression));

            if ($response === '' || $response === '0') {
                return null;
            }

            $xml = $this->parseXmlResponse($response);
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
            if (strlen($output) > 200) {
                $output = substr($output, 0, 200) . '... (truncated)';
            }

            // Make it more readable by adding spaces after colons and commas
            $output = preg_replace('/([,:])\s*/', '$1 ', $output);

            return $output;
        } catch (Throwable) {
            return null;
        }
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
            $xml = simplexml_load_string($response);
            if ($xml && (property_exists($xml, 'stack') && $xml->stack !== null)) {
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
     * Get trace file information (token-optimized)
     *
     * @return array{file: string, lines: int, functions: int, max_depth: int, db_queries: int}
     */
    private function getTraceInfo(): array
    {
        return $this->buildTraceInfoFromFiles($this->findTraceFiles([
            '/tmp/trace.*.xt',
            '/tmp/trace-*-' . basename($this->targetScript, '.php') . '.xt',
            '/tmp/trace-*.xt',
        ]));
    }

    /**
     * @param list<string> $patterns
     *
     * @return list<string>
     */
    private function findTraceFiles(array $patterns): array
    {
        $allTraceFiles = [];

        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if ($files === false || $files === []) {
                continue;
            }

            array_push($allTraceFiles, ...$files);
        }

        return $allTraceFiles;
    }

    /**
     * Keep only trace files written during the current debug session.
     *
     * Trace files are generated into a shared directory (e.g. /tmp), so a naive
     * "latest match" lookup could pick up another concurrent session's trace.
     *
     * @param list<string> $traceFiles
     *
     * @return list<string>
     */
    private function filterTraceFilesToSession(array $traceFiles): array
    {
        $filtered = [];
        foreach ($traceFiles as $file) {
            $mtime = @filemtime($file);
            if ($mtime === false || $mtime < $this->sessionStartTime) {
                continue;
            }

            $filtered[] = $file;
        }

        return $filtered;
    }

    /**
     * @param list<string> $traceFiles
     *
     * @return array{file: string, lines: int, functions: int, max_depth: int, db_queries: int, error?: string}
     */
    private function buildTraceInfoFromFiles(array $traceFiles): array
    {
        if ($traceFiles === []) {
            return $this->emptyTraceInfo();
        }

        try {
            $traceFiles = array_unique($traceFiles);
            usort($traceFiles, static fn ($a, $b): int => filemtime($b) - filemtime($a));

            $tracer = new XdebugTracer();

            return $tracer->generateTraceStatistics($traceFiles[0]);
        } catch (Throwable $e) {
            return $this->emptyTraceInfo($e->getMessage());
        }
    }

    /** @return array{file: string, lines: int, functions: int, max_depth: int, db_queries: int, error?: string} */
    private function emptyTraceInfo(string|null $error = null): array
    {
        $traceInfo = [
            'file' => '',
            'lines' => 0,
            'functions' => 0,
            'max_depth' => 0,
            'db_queries' => 0,
        ];

        if ($error !== null && $error !== '') {
            $traceInfo['error'] = $error;
        }

        return $traceInfo;
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
     *
     * @return array{step: int, location: array{file: string, line: int}, variables: array<string, string>}|null
     */
    private function captureCurrentDebugState(int $breakNumber): array|null
    {
        try {
            $stackXml = $this->getStack();
            $this->log('📋 Stack info: ' . json_encode($stackXml, JSON_THROW_ON_ERROR));

            $variables = $this->getCurrentVariables();
            $this->log('📋 Variables: ' . json_encode($variables, JSON_THROW_ON_ERROR));

            // Parse stack XML to get current location
            $location = $this->parseStackLocation($stackXml);

            return [
                'step' => $breakNumber,
                'location' => $location,
                'variables' => $variables,
            ];
        } catch (Throwable $e) {
            $this->log('❌ Error in captureCurrentDebugState: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Parse stack XML response to extract current location
     *
     * @return array{file: string, line: int}
     */
    private function parseStackLocation(string $stackXml): array
    {
        try {
            $xml = simplexml_load_string($stackXml);
            if ($xml && (property_exists($xml, 'stack') && $xml->stack !== null) && count($xml->stack) > 0) {
                $currentFrame = $xml->stack[0];
                if ($currentFrame !== null) {
                    $filename = (string) ($currentFrame['filename'] ?? '');
                    $line = (int) ($currentFrame['lineno'] ?? 0);

                    // Clean up file:// protocol from filename
                    $file = str_replace('file://', '', $filename);

                    return [
                        'file' => basename($file),
                        'line' => $line,
                    ];
                }
            }
        } catch (Throwable $e) {
            $this->log('❌ Error parsing stack location: ' . $e->getMessage());
        }

        // Fallback
        return [
            'file' => basename($this->targetScript),
            'line' => 1,
        ];
    }

    /**
     * Output results for multiple breakpoints
     *
     * @param list<array{step: int, location: array{file: string, line: int}, variables: array<string, string>}> $breaks
     */
    private function outputMultipleBreakResults(array $breaks): void
    {
        $payload = $this->resultFormatter->buildBreakpointPayload(
            $breaks,
            $this->getTraceInfo(),
            (string) ($this->options['context'] ?? ''),
        );

        $this->resultFormatter->emit(
            $payload,
            $this->jsonMode || ($this->options['jsonOutput'] ?? false),
            fn (string $message) => $this->log($message),
        );
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

                if (is_array($messages) && $messages !== []) {
                    $message = $messages[0];
                    $filename = (string) $message['filename'];
                    $lineno = (string) $message['lineno'];
                    // Remove file:// prefix if present
                    $filename = str_replace('file://', '', $filename);

                    return basename($filename) . ':' . $lineno;
                }
            }
        } catch (Throwable $e) {
            $this->log('❌ Error parsing break response: ' . $e->getMessage());
        }

        return 'unknown:0';
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
            $response = $this->sendCommand('status');

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
