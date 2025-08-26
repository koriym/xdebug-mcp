<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\Exceptions\FileNotFoundException;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use Koriym\XdebugMcp\Exceptions\InvalidToolException;
use Koriym\XdebugMcp\Exceptions\XdebugConnectionException;
use Koriym\XdebugMcp\Exceptions\XdebugNotAvailableException;
use Throwable;

use function array_flip;
use function array_slice;
use function array_values;
use function basename;
use function count;
use function date;
use function debug_backtrace;
use function dirname;
use function error_log;
use function explode;
use function extension_loaded;
use function fflush;
use function fgets;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function function_exists;
use function getenv;
use function gzfile;
use function implode;
use function in_array;
use function ini_get;
use function ini_set;
use function json_decode;
use function json_encode;
use function json_last_error;
use function max;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function ob_get_clean;
use function ob_start;
use function phpversion;
use function preg_match;
use function register_tick_function;
use function restore_error_handler;
use function round;
use function set_error_handler;
use function sprintf;
use function str_ends_with;
use function str_repeat;
use function time;
use function trim;
use function uasort;
use function uniqid;
use function unregister_tick_function;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const DEBUG_BACKTRACE_PROVIDE_OBJECT;
use const E_COMPILE_ERROR;
use const E_COMPILE_WARNING;
use const E_CORE_ERROR;
use const E_CORE_WARNING;
use const E_DEPRECATED;
use const E_ERROR;
use const E_NOTICE;
use const E_PARSE;
use const E_RECOVERABLE_ERROR;
use const E_STRICT;
use const E_USER_DEPRECATED;
use const E_USER_ERROR;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;
use const FILE_APPEND;
use const JSON_ERROR_NONE;
use const JSON_PRETTY_PRINT;
use const LOCK_EX;
use const STDIN;
use const STDOUT;

class McpServer
{
    protected array $tools = [];
    protected XdebugClient|null $xdebugClient = null;
    private bool $debugMode = false;
    private string $sessionId = Constants::DEFAULT_SESSION_ID;

    public function __construct()
    {
        $this->debugMode = (bool) (getenv('MCP_DEBUG') ?: false);
        $this->initializeTools();
        $this->cleanupPreviousSession(); // 前のセッションをクリーンアップ
        $this->loadExistingSession();
    }

    private function cleanupPreviousSession(): void
    {
        $stateFile = XdebugClient::GLOBAL_STATE_FILE;
        if (file_exists($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true);
            if (
                $state && isset($state['host'], $state['port'], $state['connected'], $state['sessionId'])
                && $state['sessionId'] === $this->sessionId && $state['connected']
            ) {
                try {
                    $tempClient = new XdebugClient($state['host'], $state['port'], $this->sessionId);
                    $tempClient->disconnect(); // 前のセッションを終了
                    $this->debugLog('Cleaned up previous session', $state);
                } catch (Throwable $e) {
                    $this->debugLog('Failed to disconnect previous session', ['error' => $e->getMessage()]);
                }

                // セッションを切断状態に更新
                $state['connected'] = false;
                $state['sessionId'] = $this->sessionId;
                file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
            }
        }
    }

    private function loadExistingSession(): void
    {
        $stateFile = XdebugClient::GLOBAL_STATE_FILE;
        if (file_exists($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true);
            if ($state && isset($state['host'], $state['port']) && $state['connected']) {
                // グローバル状態から既存セッションを復元
                $this->xdebugClient = new XdebugClient($state['host'], $state['port'], $this->sessionId);
                // 既存セッションの情報をXdebugClientに設定
                $this->debugLog('Loaded existing session from global state', $state);
            }
        }
    }

    private function debugLog(string $message, array $data = []): void
    {
        if ($this->debugMode) {
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => $message,
                'data' => $data,
            ];
            error_log('MCP Debug: ' . json_encode($logData));
        }
    }

    private function initializeTools(): void
    {
        $this->tools = [
            'xdebug_connect' => [
                'name' => 'xdebug_connect',
                'description' => 'Connect to Xdebug session',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'host' => ['type' => 'string', 'default' => XdebugClient::DEFAULT_HOST],
                        'port' => ['type' => 'integer', 'default' => XdebugClient::DEFAULT_PORT],
                    ],
                ],
            ],
            'xdebug_disconnect' => [
                'name' => 'xdebug_disconnect',
                'description' => 'Disconnect from Xdebug session',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_set_breakpoint' => [
                'name' => 'xdebug_set_breakpoint',
                'description' => 'Set a breakpoint at specified file and line',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'filename' => ['type' => 'string'],
                        'line' => ['type' => 'integer'],
                        'condition' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => ['filename', 'line'],
                ],
            ],
            'xdebug_remove_breakpoint' => [
                'name' => 'xdebug_remove_breakpoint',
                'description' => 'Remove a breakpoint by ID',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'breakpoint_id' => ['type' => 'string'],
                    ],
                    'required' => ['breakpoint_id'],
                ],
            ],
            'xdebug_step_into' => [
                'name' => 'xdebug_step_into',
                'description' => 'Step into the next function call',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_step_over' => [
                'name' => 'xdebug_step_over',
                'description' => 'Step over the current line',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_step_out' => [
                'name' => 'xdebug_step_out',
                'description' => 'Step out of the current function',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_continue' => [
                'name' => 'xdebug_continue',
                'description' => 'Continue execution until next breakpoint',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_stack' => [
                'name' => 'xdebug_get_stack',
                'description' => 'Get current stack trace',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_variables' => [
                'name' => 'xdebug_get_variables',
                'description' => 'Get variables in current context',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'context' => ['type' => 'integer', 'default' => 0],
                    ],
                ],
            ],
            'xdebug_eval' => [
                'name' => 'xdebug_eval',
                'description' => 'Evaluate PHP expression in current context',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => ['type' => 'string'],
                    ],
                    'required' => ['expression'],
                ],
            ],
            'xdebug_start_profiling' => [
                'name' => 'xdebug_start_profiling',
                'description' => 'Start profiling execution',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'output_file' => ['type' => 'string', 'default' => ''],
                    ],
                ],
            ],
            'xdebug_stop_profiling' => [
                'name' => 'xdebug_stop_profiling',
                'description' => 'Stop profiling and return results',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_profile_info' => [
                'name' => 'xdebug_get_profile_info',
                'description' => 'Get current profiling information',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_analyze_profile' => [
                'name' => 'xdebug_analyze_profile',
                'description' => 'Analyze profiling data from file',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'profile_file' => ['type' => 'string'],
                        'top_functions' => ['type' => 'integer', 'default' => 10],
                    ],
                    'required' => ['profile_file'],
                ],
            ],
            'xdebug_start_coverage' => [
                'name' => 'xdebug_start_coverage',
                'description' => 'Start code coverage tracking',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'include_patterns' => ['type' => 'array', 'default' => []],
                        'exclude_patterns' => ['type' => 'array', 'default' => []],
                        'track_unused' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
            ],
            'xdebug_stop_coverage' => [
                'name' => 'xdebug_stop_coverage',
                'description' => 'Stop code coverage tracking',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_coverage' => [
                'name' => 'xdebug_get_coverage',
                'description' => 'Get code coverage data',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'format' => ['type' => 'string', 'enum' => ['raw', 'summary'], 'default' => 'raw'],
                    ],
                ],
            ],
            'xdebug_analyze_coverage' => [
                'name' => 'xdebug_analyze_coverage',
                'description' => 'Analyze coverage data and generate report',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'coverage_data' => ['type' => 'object'],
                        'format' => ['type' => 'string', 'enum' => ['html', 'xml', 'text', 'json'], 'default' => 'text'],
                        'output_file' => ['type' => 'string', 'default' => ''],
                    ],
                ],
            ],
            'xdebug_coverage_summary' => [
                'name' => 'xdebug_coverage_summary',
                'description' => 'Get coverage summary statistics',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'coverage_data' => ['type' => 'object'],
                    ],
                ],
            ],
            'xdebug_get_memory_usage' => [
                'name' => 'xdebug_get_memory_usage',
                'description' => 'Get current memory usage information',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_peak_memory_usage' => [
                'name' => 'xdebug_get_peak_memory_usage',
                'description' => 'Get peak memory usage information',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_stack_depth' => [
                'name' => 'xdebug_get_stack_depth',
                'description' => 'Get current stack depth level',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_time_index' => [
                'name' => 'xdebug_get_time_index',
                'description' => 'Get time index since script start',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_info' => [
                'name' => 'xdebug_info',
                'description' => 'Get detailed Xdebug configuration and diagnostic information',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'format' => ['type' => 'string', 'enum' => ['array', 'html'], 'default' => 'array'],
                    ],
                ],
            ],
            'xdebug_start_error_collection' => [
                'name' => 'xdebug_start_error_collection',
                'description' => 'Start collecting PHP errors, notices, and warnings',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_stop_error_collection' => [
                'name' => 'xdebug_stop_error_collection',
                'description' => 'Stop collecting errors and return collected data',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_collected_errors' => [
                'name' => 'xdebug_get_collected_errors',
                'description' => 'Get currently collected error messages',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'clear' => ['type' => 'boolean', 'default' => false],
                    ],
                ],
            ],
            'xdebug_start_trace' => [
                'name' => 'xdebug_start_trace',
                'description' => 'Start function call tracing',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'trace_file' => ['type' => 'string', 'default' => ''],
                        'options' => ['type' => 'integer', 'default' => 0],
                    ],
                ],
            ],
            'xdebug_stop_trace' => [
                'name' => 'xdebug_stop_trace',
                'description' => 'Stop function call tracing and return trace data',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_tracefile_name' => [
                'name' => 'xdebug_get_tracefile_name',
                'description' => 'Get the filename of the current trace file',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_start_function_monitor' => [
                'name' => 'xdebug_start_function_monitor',
                'description' => 'Start monitoring specific functions',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'functions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['functions'],
                ],
            ],
            'xdebug_stop_function_monitor' => [
                'name' => 'xdebug_stop_function_monitor',
                'description' => 'Stop function monitoring and return monitored calls',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_list_breakpoints' => [
                'name' => 'xdebug_list_breakpoints',
                'description' => 'List all active breakpoints',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_set_exception_breakpoint' => [
                'name' => 'xdebug_set_exception_breakpoint',
                'description' => 'Set a breakpoint on exception',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'exception_name' => ['type' => 'string'],
                        'state' => ['type' => 'string', 'enum' => ['caught', 'uncaught', 'all'], 'default' => 'all'],
                    ],
                    'required' => ['exception_name'],
                ],
            ],
            'xdebug_set_watch_breakpoint' => [
                'name' => 'xdebug_set_watch_breakpoint',
                'description' => 'Set a watch/conditional breakpoint',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['write', 'read', 'readwrite'], 'default' => 'write'],
                    ],
                    'required' => ['expression'],
                ],
            ],
            'xdebug_get_function_stack' => [
                'name' => 'xdebug_get_function_stack',
                'description' => 'Get detailed function stack with arguments and variables',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'include_args' => ['type' => 'boolean', 'default' => true],
                        'include_object' => ['type' => 'boolean', 'default' => true],
                        'limit' => ['type' => 'integer', 'default' => 0],
                    ],
                ],
            ],
            'xdebug_print_function_stack' => [
                'name' => 'xdebug_print_function_stack',
                'description' => 'Print formatted function stack trace',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string', 'default' => 'Call Stack'],
                        'options' => ['type' => 'integer', 'default' => 0],
                    ],
                ],
            ],
            'xdebug_call_info' => [
                'name' => 'xdebug_call_info',
                'description' => 'Get information about the calling context',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_get_features' => [
                'name' => 'xdebug_get_features',
                'description' => 'Get all available Xdebug features and their values',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            'xdebug_set_feature' => [
                'name' => 'xdebug_set_feature',
                'description' => 'Set a specific Xdebug feature value',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'feature_name' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                    ],
                    'required' => ['feature_name', 'value'],
                ],
            ],
            'xdebug_get_feature' => [
                'name' => 'xdebug_get_feature',
                'description' => 'Get a specific Xdebug feature value',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'feature_name' => ['type' => 'string'],
                    ],
                    'required' => ['feature_name'],
                ],
            ],
        ];
    }

    public function __invoke(): void
    {
        try {
            $input = '';

            while (($line = fgets(STDIN)) !== false) {
                $input .= $line;

                if ($this->isCompleteJsonRpc($input)) {
                    $request = json_decode(trim($input), true);

                    if ($request === null) {
                        // Invalid JSON, send parse error
                        $errorResponse = [
                            'jsonrpc' => '2.0',
                            'id' => null,
                            'error' => [
                                'code' => -32700,
                                'message' => 'Parse error',
                            ],
                        ];
                        echo json_encode($errorResponse) . "\n";
                        fflush(STDOUT);
                    } else {
                        $this->debugLog('Received request', $request);

                        try {
                            $response = $this->handleRequest($request);

                            if ($response !== null) {
                                $this->debugLog('Sending response', $response);
                                echo json_encode($response) . "\n";
                                fflush(STDOUT);
                            }
                        } catch (Throwable $e) {
                            error_log('MCP Server Error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());

                            $errorResponse = [
                                'jsonrpc' => '2.0',
                                'id' => $request['id'] ?? null,
                                'error' => [
                                    'code' => -32603,
                                    'message' => 'Internal error: ' . $e->getMessage(),
                                ],
                            ];
                            echo json_encode($errorResponse) . "\n";
                            fflush(STDOUT);
                        }
                    }

                    $input = '';
                }
            }
        } catch (Throwable $e) {
            error_log('MCP Server Fatal Error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        }
    }

    private function isCompleteJsonRpc(string $input): bool
    {
        $trimmed = trim($input);
        if (empty($trimmed)) {
            return false;
        }

        $decoded = json_decode($trimmed);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function handleRequest(array $request): array|null
    {
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        try {
            switch ($method) {
                case 'initialize':
                    return $this->handleInitialize($id, $params);

                case 'tools/list':
                    return $this->handleToolsList($id);

                case 'tools/call':
                    return $this->handleToolCall($id, $params);

                case 'resources/list':
                    return $this->handleResourcesList($id);

                case 'prompts/list':
                    return $this->handlePromptsList($id);

                case 'notifications/initialized':
                    // Handle initialized notification (no response needed)
                    return null;

                default:
                    return [
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'error' => [
                            'code' => -32601,
                            'message' => "Method not found: {$method}",
                        ],
                    ];
            }
        } catch (Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'Server error: ' . $e->getMessage(),
                ],
            ];
        }
    }

    private function handleInitialize(mixed $id, array $params): array
    {
        // Use the protocol version requested by the client, defaulting to latest
        $clientVersion = $params['protocolVersion'] ?? '2025-06-18';

        // Ensure we support the requested version
        $supportedVersions = ['2024-11-05', '2025-03-26', '2025-06-18'];
        if (! in_array($clientVersion, $supportedVersions)) {
            $clientVersion = '2025-06-18';
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => $clientVersion,
                'capabilities' => [
                    'tools' => ['listChanged' => true],
                    'resources' => (object) [],
                    'prompts' => (object) [],
                ],
                'serverInfo' => [
                    'name' => 'xdebug-mcp-server',
                    'version' => '2.0.0',
                ],
            ],
        ];
    }

    private function handleToolsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => array_values($this->tools),
            ],
        ];
    }

    private function handleResourcesList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'resources' => [],
            ],
        ];
    }

    private function handlePromptsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'prompts' => [],
            ],
        ];
    }

    private function handleToolCall(mixed $id, array $params): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        try {
            $result = $this->executeToolCall($toolName, $arguments);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $result,
                        ],
                    ],
                ],
            ];
        } catch (Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    private function executeToolCall(string $toolName, array $arguments): string
    {
        switch ($toolName) {
            case 'xdebug_connect':
                return $this->connectToXdebug($arguments);

            case 'xdebug_disconnect':
                return $this->disconnectFromXdebug();

            case 'xdebug_set_breakpoint':
                return $this->setBreakpoint($arguments);

            case 'xdebug_step_into':
                return $this->stepInto();

            case 'xdebug_remove_breakpoint':
                return $this->removeBreakpoint($arguments);

            case 'xdebug_step_over':
                return $this->stepOver();

            case 'xdebug_step_out':
                return $this->stepOut();

            case 'xdebug_continue':
                return $this->continue();

            case 'xdebug_get_stack':
                return $this->getStack();

            case 'xdebug_get_variables':
                return $this->getVariables($arguments);

            case 'xdebug_eval':
                return $this->evaluateExpression($arguments);

            case 'xdebug_start_profiling':
                return $this->startProfiling($arguments);

            case 'xdebug_stop_profiling':
                return $this->stopProfiling();

            case 'xdebug_get_profile_info':
                return $this->getProfileInfo();

            case 'xdebug_analyze_profile':
                return $this->analyzeProfile($arguments);

            case 'xdebug_start_coverage':
                return $this->startCoverage($arguments);

            case 'xdebug_stop_coverage':
                return $this->stopCoverage();

            case 'xdebug_get_coverage':
                return $this->getCoverage($arguments);

            case 'xdebug_analyze_coverage':
                return $this->analyzeCoverage($arguments);

            case 'xdebug_coverage_summary':
                return $this->getCoverageSummary($arguments);

            case 'xdebug_get_memory_usage':
                return $this->getMemoryUsage();

            case 'xdebug_get_peak_memory_usage':
                return $this->getPeakMemoryUsage();

            case 'xdebug_get_stack_depth':
                return $this->getStackDepth();

            case 'xdebug_get_time_index':
                return $this->getTimeIndex();

            case 'xdebug_info':
                return $this->getXdebugInfo($arguments);

            case 'xdebug_start_error_collection':
                return $this->startErrorCollection();

            case 'xdebug_stop_error_collection':
                return $this->stopErrorCollection();

            case 'xdebug_get_collected_errors':
                return $this->getCollectedErrors($arguments);

            case 'xdebug_start_trace':
                return $this->startTrace($arguments);

            case 'xdebug_stop_trace':
                return $this->stopTrace();

            case 'xdebug_get_tracefile_name':
                return $this->getTracefileName();

            case 'xdebug_start_function_monitor':
                return $this->startFunctionMonitor($arguments);

            case 'xdebug_stop_function_monitor':
                return $this->stopFunctionMonitor();

            case 'xdebug_list_breakpoints':
                return $this->listBreakpoints();

            case 'xdebug_set_exception_breakpoint':
                return $this->setExceptionBreakpoint($arguments);

            case 'xdebug_set_watch_breakpoint':
                return $this->setWatchBreakpoint($arguments);

            case 'xdebug_get_function_stack':
                return $this->getFunctionStack($arguments);

            case 'xdebug_print_function_stack':
                return $this->printFunctionStack($arguments);

            case 'xdebug_call_info':
                return $this->getCallInfo();

            case 'xdebug_get_features':
                return $this->getFeatures();

            case 'xdebug_set_feature':
                return $this->setFeature($arguments);

            case 'xdebug_get_feature':
                return $this->getFeature($arguments);

            default:
                throw new InvalidToolException("Unknown tool: $toolName");
        }
    }

    protected function connectToXdebug(array $args): string
    {
        $host = $args['host'] ?? XdebugClient::DEFAULT_HOST;
        $port = $args['port'] ?? XdebugClient::DEFAULT_PORT;

        $this->xdebugClient = new XdebugClient($host, $port, $this->sessionId);
        try {
            $result = $this->xdebugClient->connect();
            $state = [
                'host' => $host,
                'port' => $port,
                'connected' => true,
                'sessionId' => $this->sessionId,
                'last_activity' => time(),
                'session_info' => $result,
            ];
            file_put_contents(XdebugClient::GLOBAL_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));

            return "Connected to new Xdebug session at {$host}:{$port}. Result: " . json_encode($result);
        } catch (Throwable $e) {
            $this->debugLog('Connection failed', ['error' => $e->getMessage()]);

            return "Failed to connect to Xdebug: {$e->getMessage()}. Port {$port} may be in use.";
        }
    }

    protected function disconnectFromXdebug(): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $this->xdebugClient->disconnect();

        // State file is cleared by client->disconnect(); do not rewrite it here.

        $this->xdebugClient = null;

        return 'Disconnected from Xdebug';
    }

    protected function setBreakpoint(array $args): string
    {
        $filename = $args['filename'];
        $line = (int) $args['line'];
        $condition = $args['condition'] ?? '';

        // Direct connection via XdebugClient
        if ($this->xdebugClient) {
            try {
                $breakpointId = $this->xdebugClient->setBreakpoint($filename, $line, $condition);

                return "Direct Connection - Breakpoint set at {$filename}:{$line} (ID: {$breakpointId})";
            } catch (Throwable $e) {
                return 'Failed to set breakpoint via both persistent server and direct connection: ' . $e->getMessage();
            }
        }

        return 'Failed to set breakpoint: No available debug client.';
    }

    protected function removeBreakpoint(array $args): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $breakpointId = $args['breakpoint_id'];
        $this->xdebugClient->removeBreakpoint($breakpointId);

        return "Breakpoint {$breakpointId} removed";
    }

    protected function stepInto(): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $result = $this->xdebugClient->stepInto();

        return 'Step into completed: ' . json_encode($result);
    }

    protected function stepOver(): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $result = $this->xdebugClient->stepOver();

        return 'Step over completed: ' . json_encode($result);
    }

    protected function stepOut(): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $result = $this->xdebugClient->stepOut();

        return 'Step out completed: ' . json_encode($result);
    }

    protected function continue(): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $result = $this->xdebugClient->continue();

        return 'Continue completed: ' . json_encode($result);
    }

    protected function getStack(): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $stack = $this->xdebugClient->getStack();

        return "Stack trace:\n" . json_encode($stack, JSON_PRETTY_PRINT);
    }

    protected function getVariables(array $args): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $context = $args['context'] ?? 0;
        $variables = $this->xdebugClient->getVariables($context);

        return "Variables (context {$context}):\n" . json_encode($variables, JSON_PRETTY_PRINT);
    }

    protected function evaluateExpression(array $args): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $expression = $args['expression'];
        $result = $this->xdebugClient->eval($expression);

        return "Evaluation result for '{$expression}':\n" . json_encode($result, JSON_PRETTY_PRINT);
    }

    protected function startProfiling(array $args): string
    {
        $outputFile = $args['output_file'] ?? '';

        if (! $this->xdebugClient) {
            return $this->startStandaloneProfiling($outputFile);
        }

        $result = $this->xdebugClient->startProfiling($outputFile);

        return 'Profiling started: ' . json_encode($result);
    }

    protected function stopProfiling(): string
    {
        if (! $this->xdebugClient) {
            return $this->stopStandaloneProfiling();
        }

        $result = $this->xdebugClient->stopProfiling();

        return 'Profiling stopped: ' . json_encode($result);
    }

    protected function getProfileInfo(): string
    {
        if (! $this->xdebugClient) {
            return $this->getStandaloneProfileInfo();
        }

        $info = $this->xdebugClient->getProfileInfo();

        return "Profile info:\n" . json_encode($info, JSON_PRETTY_PRINT);
    }

    protected function analyzeProfile(array $args): string
    {
        $profileFile = $args['profile_file'];
        $topFunctions = $args['top_functions'] ?? 10;

        if (! file_exists($profileFile)) {
            throw new FileNotFoundException("Profile file not found: {$profileFile}");
        }

        $analysis = $this->parseProfileFile($profileFile, $topFunctions);

        return "Profile analysis for {$profileFile}:\n" . json_encode($analysis, JSON_PRETTY_PRINT);
    }

    private function startStandaloneProfiling(string $outputFile): string
    {
        if (! extension_loaded('xdebug')) {
            throw new XdebugNotAvailableException('Xdebug extension not loaded');
        }

        if ($outputFile) {
            ini_set('xdebug.profiler_output_name', basename($outputFile));
            ini_set('xdebug.output_dir', dirname($outputFile));
        }

        if (function_exists('xdebug_start_trace')) {
            xdebug_start_trace();
        }

        return 'Standalone profiling started' . ($outputFile ? " (output: {$outputFile})" : '');
    }

    private function stopStandaloneProfiling(): string
    {
        if (! extension_loaded('xdebug')) {
            throw new XdebugNotAvailableException('Xdebug extension not loaded');
        }

        if (function_exists('xdebug_stop_trace')) {
            xdebug_stop_trace();
        }

        return 'Standalone profiling stopped';
    }

    private function getStandaloneProfileInfo(): string
    {
        if (! extension_loaded('xdebug')) {
            throw new XdebugNotAvailableException('Xdebug extension not loaded');
        }

        $info = [
            'xdebug_version' => phpversion('xdebug'),
            'profiler_enabled' => ini_get('xdebug.profiler_enable') || ini_get('xdebug.mode') === 'profile',
            'output_dir' => ini_get('xdebug.output_dir'),
            'output_name' => ini_get('xdebug.profiler_output_name'),
        ];

        return json_encode($info, JSON_PRETTY_PRINT);
    }

    private function parseProfileFile(string $profileFile, int $topFunctions): array
    {
        // Handle gzipped files
        if (str_ends_with($profileFile, '.gz')) {
            $content = gzfile($profileFile);
            if ($content === false) {
                throw new FileNotFoundException("Failed to read gzipped profile file: {$profileFile}");
            }

            $content = implode('', $content);
        } else {
            $content = file_get_contents($profileFile);
            if ($content === false) {
                throw new FileNotFoundException("Failed to read profile file: {$profileFile}");
            }
        }

        $lines = explode("\n", $content);
        $functions = [];
        $totalTime = 0;
        $currentFunction = null;
        $functionMap = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse function definitions: fn=(123) function_name
            if (preg_match('/^fn=\((\d+)\)\s*(.*)$/', $line, $matches)) {
                $functionId = $matches[1];
                $functionName = $matches[2] ?: "function_{$functionId}";
                $functionMap[$functionId] = $functionName;
                $currentFunction = $functionName;
                if (! isset($functions[$functionName])) {
                    $functions[$functionName] = [
                        'calls' => 0,
                        'time' => 0,
                        'memory' => 0,
                        'self_time' => 0,
                    ];
                }

                continue;
            }

            // Parse cost lines: line_number time memory
            if ($currentFunction && preg_match('/^(\d+)\s+(\d+)(?:\s+(\d+))?/', $line, $matches)) {
                $time = (int) $matches[2];
                $memory = isset($matches[3]) ? (int) $matches[3] : 0;

                $functions[$currentFunction]['self_time'] += $time;
                $functions[$currentFunction]['memory'] += $memory;
                $totalTime += $time;
                continue;
            }

            // Parse call lines: calls=X Y Z
            if (preg_match('/^calls=(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
                if ($currentFunction) {
                    $functions[$currentFunction]['calls'] += (int) $matches[1];
                }

                continue;
            }

            // Parse summary line
            if (preg_match('/^summary:\s*(\d+)\s+(\d+)/', $line, $matches)) {
                $totalTime = max($totalTime, (int) $matches[1]);
                continue;
            }
        }

        // Calculate inclusive time (self_time for now, could be enhanced)
        foreach ($functions as $name => $data) {
            $functions[$name]['time'] = $data['self_time'];
        }

        // Sort by inclusive time
        uasort($functions, static function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        $topFunctionsList = array_slice($functions, 0, $topFunctions, true);

        return [
            'total_time' => $totalTime,
            'total_functions' => count($functions),
            'top_functions' => $topFunctionsList,
            'file' => $profileFile,
            'size' => filesize($profileFile) ?: 0,
            'format' => 'cachegrind',
        ];
    }

    protected function startCoverage(array $args): string
    {
        if (! extension_loaded('xdebug')) {
            throw new XdebugNotAvailableException('Xdebug extension not loaded');
        }

        $includePatterns = $args['include_patterns'] ?? [];
        $excludePatterns = $args['exclude_patterns'] ?? [];
        $trackUnused = $args['track_unused'] ?? true;

        $flags = XDEBUG_CC_UNUSED;
        if (! $trackUnused) {
            $flags = 0;
        }

        if (function_exists('xdebug_start_code_coverage')) {
            xdebug_start_code_coverage($flags);
        }

        return 'Code coverage started' .
               ($includePatterns ? ' (includes: ' . implode(', ', $includePatterns) . ')' : '') .
               ($excludePatterns ? ' (excludes: ' . implode(', ', $excludePatterns) . ')' : '');
    }

    protected function stopCoverage(): string
    {
        if (! extension_loaded('xdebug')) {
            throw new XdebugNotAvailableException('Xdebug extension not loaded');
        }

        if (function_exists('xdebug_stop_code_coverage')) {
            xdebug_stop_code_coverage();
        }

        return 'Code coverage stopped';
    }

    protected function getCoverage(array $args): string
    {
        if (! extension_loaded('xdebug')) {
            throw new XdebugNotAvailableException('Xdebug extension not loaded');
        }

        $format = $args['format'] ?? 'raw';

        if (function_exists('xdebug_get_code_coverage')) {
            $coverage = xdebug_get_code_coverage();

            if ($format === 'summary') {
                $summary = $this->calculateCoverageSummary($coverage);

                return "Coverage summary:\n" . json_encode($summary, JSON_PRETTY_PRINT);
            }

            return "Code coverage data:\n" . json_encode($coverage, JSON_PRETTY_PRINT);
        }

        throw new XdebugNotAvailableException('xdebug_get_code_coverage function not available');
    }

    protected function analyzeCoverage(array $args): string
    {
        $coverageData = $args['coverage_data'] ?? [];
        $format = $args['format'] ?? 'text';
        $outputFile = $args['output_file'] ?? '';

        if (empty($coverageData)) {
            throw new InvalidArgumentException('No coverage data provided');
        }

        $analysis = $this->processCoverageData($coverageData);

        switch ($format) {
            case 'html':
                $report = $this->generateHtmlCoverageReport($analysis);
                break;
            case 'xml':
                $report = $this->generateXmlCoverageReport($analysis);
                break;
            case 'json':
                $report = json_encode($analysis, JSON_PRETTY_PRINT);
                break;
            default:
                $report = $this->generateTextCoverageReport($analysis);
        }

        if ($outputFile) {
            file_put_contents($outputFile, $report);

            return "Coverage report saved to {$outputFile}";
        }

        return $report;
    }

    protected function getCoverageSummary(array $args): string
    {
        $coverageData = $args['coverage_data'] ?? [];

        if (empty($coverageData)) {
            if (function_exists('xdebug_get_code_coverage')) {
                $coverageData = xdebug_get_code_coverage();
            } else {
                throw new InvalidArgumentException('No coverage data available');
            }
        }

        $summary = $this->calculateCoverageSummary($coverageData);

        return "Coverage Summary:\n" . json_encode($summary, JSON_PRETTY_PRINT);
    }

    private function calculateCoverageSummary(array $coverageData): array
    {
        $totalLines = 0;
        $coveredLines = 0;
        $fileCount = 0;

        foreach ($coverageData as $file => $lines) {
            $fileCount++;
            foreach ($lines as $lineNumber => $executed) {
                $totalLines++;
                if ($executed > 0) {
                    $coveredLines++;
                }
            }
        }

        $percentage = $totalLines > 0 ? round($coveredLines / $totalLines * 100, 2) : 0;

        return [
            'total_files' => $fileCount,
            'total_lines' => $totalLines,
            'covered_lines' => $coveredLines,
            'uncovered_lines' => $totalLines - $coveredLines,
            'coverage_percentage' => $percentage,
        ];
    }

    private function processCoverageData(array $coverageData): array
    {
        $processed = [];

        foreach ($coverageData as $file => $lines) {
            $fileInfo = [
                'file' => $file,
                'total_lines' => count($lines),
                'covered_lines' => 0,
                'uncovered_lines' => [],
                'coverage_percentage' => 0,
            ];

            foreach ($lines as $lineNumber => $executed) {
                if ($executed > 0) {
                    $fileInfo['covered_lines']++;
                } elseif ($executed === -1) {
                    $fileInfo['uncovered_lines'][] = $lineNumber;
                }
            }

            if ($fileInfo['total_lines'] > 0) {
                $fileInfo['coverage_percentage'] = round($fileInfo['covered_lines'] / $fileInfo['total_lines'] * 100, 2);
            }

            $processed[] = $fileInfo;
        }

        return $processed;
    }

    private function generateTextCoverageReport(array $analysis): string
    {
        $report = "Code Coverage Report\n";
        $report .= str_repeat('=', 50) . "\n\n";

        foreach ($analysis as $fileInfo) {
            $report .= "File: {$fileInfo['file']}\n";
            $report .= "Coverage: {$fileInfo['coverage_percentage']}%\n";
            $report .= "Lines: {$fileInfo['covered_lines']}/{$fileInfo['total_lines']}\n";

            if (! empty($fileInfo['uncovered_lines'])) {
                $report .= 'Uncovered lines: ' . implode(', ', $fileInfo['uncovered_lines']) . "\n";
            }

            $report .= "\n";
        }

        return $report;
    }

    private function generateHtmlCoverageReport(array $analysis): string
    {
        $html = '<html><head><title>Code Coverage Report</title></head><body>';
        $html .= '<h1>Code Coverage Report</h1>';
        $html .= "<table border='1'><tr><th>File</th><th>Coverage</th><th>Lines</th><th>Uncovered Lines</th></tr>";

        foreach ($analysis as $fileInfo) {
            $html .= '<tr>';
            $html .= "<td>{$fileInfo['file']}</td>";
            $html .= "<td>{$fileInfo['coverage_percentage']}%</td>";
            $html .= "<td>{$fileInfo['covered_lines']}/{$fileInfo['total_lines']}</td>";
            $html .= '<td>' . implode(', ', $fileInfo['uncovered_lines']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';

        return $html;
    }

    private function generateXmlCoverageReport(array $analysis): string
    {
        $xml = "<?xml version='1.0'?>\n<coverage>\n";

        foreach ($analysis as $fileInfo) {
            $xml .= "  <file name='{$fileInfo['file']}'>\n";
            $xml .= "    <coverage_percentage>{$fileInfo['coverage_percentage']}</coverage_percentage>\n";
            $xml .= "    <total_lines>{$fileInfo['total_lines']}</total_lines>\n";
            $xml .= "    <covered_lines>{$fileInfo['covered_lines']}</covered_lines>\n";
            $xml .= '    <uncovered_lines>' . implode(',', $fileInfo['uncovered_lines']) . "</uncovered_lines>\n";
            $xml .= "  </file>\n";
        }

        $xml .= '</coverage>';

        return $xml;
    }

    protected function getMemoryUsage(): string
    {
        $info = [
            'current_memory' => memory_get_usage(),
            'current_memory_real' => memory_get_usage(true),
            'memory_limit' => ini_get('memory_limit'),
        ];

        if (function_exists('xdebug_memory_usage')) {
            $info['xdebug_memory'] = xdebug_memory_usage();
        }

        return "Memory usage information:\n" . json_encode($info, JSON_PRETTY_PRINT);
    }

    protected function getPeakMemoryUsage(): string
    {
        $info = [
            'peak_memory' => memory_get_peak_usage(),
            'peak_memory_real' => memory_get_peak_usage(true),
        ];

        if (function_exists('xdebug_peak_memory_usage')) {
            $info['xdebug_peak_memory'] = xdebug_peak_memory_usage();
        }

        return "Peak memory usage information:\n" . json_encode($info, JSON_PRETTY_PRINT);
    }

    protected function getStackDepth(): string
    {
        $depth = 0;

        if (function_exists('xdebug_get_stack_depth')) {
            $depth = xdebug_get_stack_depth();
        } else {
            $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $depth = count($stack);
        }

        return "Current stack depth: {$depth}";
    }

    protected function getTimeIndex(): string
    {
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $currentTime = microtime(true);
        $elapsed = $currentTime - $startTime;

        $info = [
            'start_time' => $startTime,
            'current_time' => $currentTime,
            'elapsed_seconds' => $elapsed,
        ];

        if (function_exists('xdebug_time_index')) {
            $info['xdebug_time_index'] = xdebug_time_index();
        }

        return "Time information:\n" . json_encode($info, JSON_PRETTY_PRINT);
    }

    protected function getXdebugInfo(array $args): string
    {
        $format = $args['format'] ?? 'array';

        if (! extension_loaded('xdebug')) {
            return 'Xdebug extension not loaded';
        }

        if ($format === 'html' && function_exists('xdebug_info')) {
            return xdebug_info();
        }

        $info = [
            'version' => phpversion('xdebug'),
            'mode' => ini_get('xdebug.mode'),
            'client_host' => ini_get('xdebug.client_host'),
            'client_port' => ini_get('xdebug.client_port'),
            'start_with_request' => ini_get('xdebug.start_with_request'),
            'log' => ini_get('xdebug.log'),
            'output_dir' => ini_get('xdebug.output_dir'),
            'max_nesting_level' => ini_get('xdebug.max_nesting_level'),
            'collect_assignments' => ini_get('xdebug.collect_assignments'),
            'collect_return' => ini_get('xdebug.collect_return'),
            'collect_params' => ini_get('xdebug.collect_params'),
            'show_exception_trace' => ini_get('xdebug.show_exception_trace'),
            'show_error_trace' => ini_get('xdebug.show_error_trace'),
            'show_local_vars' => ini_get('xdebug.show_local_vars'),
        ];

        if (function_exists('xdebug_is_debugger_active')) {
            $info['debugger_active'] = xdebug_is_debugger_active();
        }

        return "Xdebug information:\n" . json_encode($info, JSON_PRETTY_PRINT);
    }

    private static $errorCollection = [];
    private static $errorCollectionActive = false;

    protected function startErrorCollection(): string
    {
        if (function_exists('xdebug_start_error_collection')) {
            xdebug_start_error_collection();

            return 'Xdebug error collection started';
        }

        self::$errorCollection = [];
        self::$errorCollectionActive = true;

        set_error_handler(function ($severity, $message, $file, $line) {
            if (self::$errorCollectionActive) {
                self::$errorCollection[] = [
                    'type' => $this->getErrorTypeName($severity),
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                    'timestamp' => microtime(true),
                ];
            }

            return false;
        });

        return 'Custom error collection started';
    }

    protected function stopErrorCollection(): string
    {
        if (function_exists('xdebug_stop_error_collection')) {
            xdebug_stop_error_collection();

            return 'Xdebug error collection stopped';
        }

        self::$errorCollectionActive = false;
        restore_error_handler();

        $errorCount = count(self::$errorCollection);

        return "Custom error collection stopped. Collected {$errorCount} errors.";
    }

    protected function getCollectedErrors(array $args): string
    {
        $clear = $args['clear'] ?? false;

        if (function_exists('xdebug_get_collected_errors')) {
            $errors = xdebug_get_collected_errors($clear);

            return "Collected errors:\n" . ($errors ?: 'No errors collected');
        }

        $errors = self::$errorCollection;

        if ($clear) {
            self::$errorCollection = [];
        }

        if (empty($errors)) {
            return 'No errors collected';
        }

        return "Collected errors:\n" . json_encode($errors, JSON_PRETTY_PRINT);
    }

    private function getErrorTypeName(int $type): string
    {
        return match ($type) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => "UNKNOWN_ERROR_TYPE({$type})"
        };
    }

    private static $tracingActive = false;
    private static $traceFile = '';
    private static $functionMonitor = [];
    private static $monitoredCalls = [];

    protected function startTrace(array $args): string
    {
        $traceFile = $args['trace_file'] ?? '';
        $options = $args['options'] ?? 0;

        if (function_exists('xdebug_start_trace')) {
            $filename = xdebug_start_trace($traceFile, $options);

            return 'Xdebug trace started' . ($filename ? " (file: {$filename})" : '');
        }

        self::$tracingActive = true;
        $xdebugOutputDir = ini_get('xdebug.output_dir') ?: '/tmp';
        self::$traceFile = $traceFile ?: $xdebugOutputDir . '/xdebug_custom_trace_' . uniqid() . '.xt';

        register_tick_function([$this, 'traceFunction']);
        declare(ticks=1);

        return 'Custom trace started (file: ' . self::$traceFile . ')';
    }

    protected function stopTrace(): string
    {
        if (function_exists('xdebug_stop_trace')) {
            $filename = xdebug_stop_trace();

            return 'Xdebug trace stopped' . ($filename ? " (file: {$filename})" : '');
        }

        self::$tracingActive = false;
        unregister_tick_function([$this, 'traceFunction']);

        return 'Custom trace stopped (file: ' . self::$traceFile . ')';
    }

    protected function getTracefileName(): string
    {
        if (function_exists('xdebug_get_tracefile_name')) {
            $filename = xdebug_get_tracefile_name();

            return $filename ?: 'No trace file active';
        }

        return self::$traceFile ?: 'No trace file active';
    }

    protected function startFunctionMonitor(array $args): string
    {
        $functions = $args['functions'] ?? [];

        if (empty($functions)) {
            throw new InvalidArgumentException('No functions specified to monitor');
        }

        if (function_exists('xdebug_start_function_monitor')) {
            xdebug_start_function_monitor($functions);

            return 'Xdebug function monitor started for: ' . implode(', ', $functions);
        }

        self::$functionMonitor = array_flip($functions);
        self::$monitoredCalls = [];

        return 'Custom function monitor started for: ' . implode(', ', $functions);
    }

    protected function stopFunctionMonitor(): string
    {
        if (function_exists('xdebug_stop_function_monitor')) {
            xdebug_stop_function_monitor();

            return 'Xdebug function monitor stopped';
        }

        $calls = self::$monitoredCalls;
        self::$functionMonitor = [];
        self::$monitoredCalls = [];

        $callCount = count($calls);

        return "Custom function monitor stopped. Monitored {$callCount} calls:\n" .
               json_encode($calls, JSON_PRETTY_PRINT);
    }

    public function traceFunction(): void
    {
        if (! self::$tracingActive) {
            return;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (count($backtrace) < 2) {
            return;
        }

        $current = $backtrace[1];
        $function = $current['function'] ?? 'unknown';
        $class = $current['class'] ?? '';
        $file = $current['file'] ?? 'unknown';
        $line = $current['line'] ?? 0;

        $fullFunction = $class ? "{$class}::{$function}" : $function;

        if (! empty(self::$functionMonitor) && ! isset(self::$functionMonitor[$fullFunction])) {
            return;
        }

        $entry = [
            'function' => $fullFunction,
            'file' => $file,
            'line' => $line,
            'time' => microtime(true),
            'memory' => memory_get_usage(),
        ];

        if (! empty(self::$functionMonitor)) {
            self::$monitoredCalls[] = $entry;
        }

        if (self::$traceFile) {
            file_put_contents(self::$traceFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    protected function listBreakpoints(): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $breakpoints = $this->xdebugClient->listBreakpoints();

        return "Active breakpoints:\n" . json_encode($breakpoints, JSON_PRETTY_PRINT);
    }

    protected function setExceptionBreakpoint(array $args): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $exceptionName = $args['exception_name'];
        $state = $args['state'] ?? 'all';

        $breakpointId = $this->xdebugClient->setExceptionBreakpoint($exceptionName, $state);

        return "Exception breakpoint set for '{$exceptionName}' (state: {$state}, ID: {$breakpointId})";
    }

    protected function setWatchBreakpoint(array $args): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $expression = $args['expression'];
        $type = $args['type'] ?? 'write';

        $breakpointId = $this->xdebugClient->setWatchBreakpoint($expression, $type);

        return "Watch breakpoint set for expression '{$expression}' (type: {$type}, ID: {$breakpointId})";
    }

    protected function getFunctionStack(array $args): string
    {
        $includeArgs = $args['include_args'] ?? true;
        $includeObject = $args['include_object'] ?? true;
        $limit = $args['limit'] ?? 0;

        if (function_exists('xdebug_get_function_stack')) {
            $stack = xdebug_get_function_stack();
        } else {
            $options = DEBUG_BACKTRACE_PROVIDE_OBJECT;
            if (! $includeObject) {
                $options = DEBUG_BACKTRACE_IGNORE_ARGS;
            }

            $stack = debug_backtrace($options, $limit ?: 50);
        }

        if ($limit > 0) {
            $stack = array_slice($stack, 0, $limit);
        }

        if (! $includeArgs && isset($stack[0]['args'])) {
            foreach ($stack as &$frame) {
                unset($frame['args']);
            }
        }

        return "Function stack:\n" . json_encode($stack, JSON_PRETTY_PRINT);
    }

    protected function printFunctionStack(array $args): string
    {
        $message = $args['message'] ?? 'Call Stack';
        $options = $args['options'] ?? 0;

        if (function_exists('xdebug_print_function_stack')) {
            ob_start();
            xdebug_print_function_stack($message, $options);

            return ob_get_clean();
        }

        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $output = $message . ":\n";

        foreach ($stack as $i => $frame) {
            $function = $frame['function'] ?? 'unknown';
            $class = $frame['class'] ?? '';
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;

            $fullFunction = $class ? "{$class}::{$function}" : $function;
            $output .= sprintf("#%d %s() called at [%s:%d]\n", $i, $fullFunction, $file, $line);
        }

        return $output;
    }

    protected function getCallInfo(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        $info = [
            'current_function' => $backtrace[1]['function'] ?? 'unknown',
            'current_class' => $backtrace[1]['class'] ?? null,
            'current_file' => $backtrace[1]['file'] ?? 'unknown',
            'current_line' => $backtrace[1]['line'] ?? 0,
            'caller_function' => $backtrace[2]['function'] ?? null,
            'caller_class' => $backtrace[2]['class'] ?? null,
            'caller_file' => $backtrace[2]['file'] ?? null,
            'caller_line' => $backtrace[2]['line'] ?? null,
        ];

        if (function_exists('xdebug_call_class')) {
            $info['xdebug_call_class'] = xdebug_call_class();
        }

        if (function_exists('xdebug_call_function')) {
            $info['xdebug_call_function'] = xdebug_call_function();
        }

        if (function_exists('xdebug_call_file')) {
            $info['xdebug_call_file'] = xdebug_call_file();
        }

        if (function_exists('xdebug_call_line')) {
            $info['xdebug_call_line'] = xdebug_call_line();
        }

        return "Call information:\n" . json_encode($info, JSON_PRETTY_PRINT);
    }

    protected function getFeatures(): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $features = $this->xdebugClient->getFeatures();

        return "Available Xdebug features:\n" . json_encode($features, JSON_PRETTY_PRINT);
    }

    protected function setFeature(array $args): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $featureName = $args['feature_name'];
        $value = $args['value'];

        $result = $this->xdebugClient->setFeature($featureName, $value);
        $success = $result['@attributes']['success'] ?? '0';

        if ($success === '1') {
            return "Feature '{$featureName}' set to '{$value}' successfully";
        }

        return "Failed to set feature '{$featureName}' to '{$value}'";
    }

    protected function getFeature(array $args): string
    {
        if (! $this->xdebugClient) {
            throw new XdebugConnectionException('Not connected to Xdebug');
        }

        $featureName = $args['feature_name'];
        $result = $this->xdebugClient->getFeature($featureName);

        $value = $result['#text'] ?? $result['@attributes']['supported'] ?? 'unknown';

        return "Feature '{$featureName}': {$value}";
    }
}
