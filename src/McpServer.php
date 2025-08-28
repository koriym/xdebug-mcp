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
use function array_merge;
use function array_slice;
use function array_values;
use function basename;
use function count;
use function date;
use function debug_backtrace;
use function dirname;
use function error_log;
use function escapeshellarg;
use function exec;
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
use function is_array;
use function is_numeric;
use function is_string;
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
use function str_contains;
use function str_ends_with;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function substr;
use function sys_get_temp_dir;
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
    public function __construct()
    {
        $this->debugMode = (bool) (getenv('MCP_DEBUG') ?: false);
        $this->initializeTools();
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
            'x-trace' => [
                'name' => 'x-trace',
                'description' => 'Trace PHP execution flow | ex) x-trace --script=test.php --context="Debug login flow"',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to trace (e.g., "test/debug_test.php")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for AI analysis (e.g., "Testing user authentication flow")',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ],
            'x-profile' => [
                'name' => 'x-profile',
                'description' => 'Profile performance bottlenecks | ex) x-profile --script=slow-app.php --context="API performance"',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to profile (e.g., "test/performance_test.php")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for performance analysis',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ],
            'x-debug' => [
                'name' => 'x-debug',
                'description' => 'Step debugging with breakpoints | ex) x-debug --script=test.php --breakpoints="test.php:15:$user==null" --steps=100',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to debug (e.g., "test/debug_test.php")',
                        ],
                        'breakpoints' => [
                            'type' => 'string',
                            'description' => 'Comma-separated breakpoint locations (e.g., "file.php:15,file.php:25")',
                            'default' => '',
                        ],
                        'steps' => [
                            'type' => 'string',
                            'description' => 'Maximum debugging steps to execute',
                            'default' => '100',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for debugging session',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ],
            'x-coverage' => [
                'name' => 'x-coverage',
                'description' => 'Analyze test coverage | ex) x-coverage --script="vendor/bin/phpunit UserTest.php"',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to analyze coverage (e.g., "vendor/bin/phpunit UserTest.php")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for coverage analysis',
                            'default' => '',
                        ],
                        'format' => [
                            'type' => 'string',
                            'description' => 'Output format: html, xml, json, text',
                            'default' => 'html',
                        ],
                    ],
                    'required' => ['script'],
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
                    error_log('DEBUG: Raw Claude CLI input = ' . trim($input));
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
                        error_log('DEBUG: Processing request method = ' . ($request['method'] ?? 'unknown'));
                        $this->debugLog('Received request', $request);

                        try {
                            $response = $this->handleRequest($request);

                            if ($response !== null) {
                                $this->debugLog('Sending response', $response);
                                echo json_encode($response) . "\n";
                                fflush(STDOUT);
                            }
                        } catch (Throwable $e) {
                            error_log('DEBUG: MCP Server Error for method ' . ($request['method'] ?? 'unknown') . ': ' . $e->getMessage());
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

                case 'prompts/get':
                    return $this->handlePromptsGet($id, $params);

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
                    'prompts' => ['listChanged' => true],
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
                'prompts' => [
                    [
                        'name' => 'x-trace',
                        'description' => 'Trace PHP execution flow | ex) /x-trace --script=test.php --context="Debug login flow"',
                        'arguments' => [
                            [
                                'name' => 'script',
                                'description' => 'PHP script to trace (e.g., "test/debug_test.php")',
                                'required' => true,
                            ],
                            [
                                'name' => 'context',
                                'description' => 'Context description for AI analysis (e.g., "Testing user authentication flow")',
                                'required' => false,
                            ],
                            [
                                'name' => 'last',
                                'description' => 'Use settings from last execution (true/false)',
                                'required' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'x-debug',
                        'description' => 'Step debugging with breakpoints | ex) /x-debug "php test.php" "test.php:15" 5 "debug context"',
                        'arguments' => [
                            [
                                'name' => 'script',
                                'description' => 'PHP script to debug (e.g., "test/debug_test.php")',
                                'required' => true,
                            ],
                            [
                                'name' => 'breakpoints',
                                'description' => 'Comma-separated breakpoint locations (e.g., "file.php:15,file.php:25")',
                                'required' => false,
                            ],
                            [
                                'name' => 'steps',
                                'description' => 'Maximum debugging steps to execute',
                                'required' => false,
                            ],
                            [
                                'name' => 'context',
                                'description' => 'Context description for debugging session',
                                'required' => false,
                            ],
                            [
                                'name' => 'last',
                                'description' => 'Use settings from last execution (true/false)',
                                'required' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'x-profile',
                        'description' => 'Profile performance bottlenecks | ex) /x-profile --script=slow-app.php --context="API performance"',
                        'arguments' => [
                            [
                                'name' => 'script',
                                'description' => 'PHP script to profile (e.g., "test/performance_test.php")',
                                'required' => true,
                            ],
                            [
                                'name' => 'context',
                                'description' => 'Context description for performance analysis',
                                'required' => false,
                            ],
                            [
                                'name' => 'last',
                                'description' => 'Use settings from last execution (true/false)',
                                'required' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'x-coverage',
                        'description' => 'Analyze test coverage | ex) /x-coverage --script="vendor/bin/phpunit UserTest.php"',
                        'arguments' => [
                            [
                                'name' => 'script',
                                'description' => 'PHP script to analyze coverage (e.g., "vendor/bin/phpunit")',
                                'required' => true,
                            ],
                            [
                                'name' => 'context',
                                'description' => 'Context description for coverage analysis',
                                'required' => false,
                            ],
                            [
                                'name' => 'format',
                                'description' => 'Output format: json, html, xml, text (default: json)',
                                'required' => false,
                            ],
                            [
                                'name' => 'last',
                                'description' => 'Use settings from last execution (true/false)',
                                'required' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function handlePromptsGet(mixed $id, array $params): array
    {
        $promptName = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        // Check if arguments contain CLI-style string that needs normalization
        if (isset($args['cli']) && is_string($args['cli'])) {
            try {
                $normalizer = new CLIParamsNormalizer();
                $normalizedArgs = $normalizer->normalize($args['cli']);
                // Merge CLI-normalized params with any existing args (CLI takes precedence)
                $args = array_merge($args, $normalizedArgs);
                unset($args['cli']); // Remove the raw CLI string
            } catch (\InvalidArgumentException $e) {
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32602,
                        'message' => 'CLI引数正規化エラー: ' . $e->getMessage(),
                    ],
                ];
            }
        }

        // Convert positional arguments to named arguments for each prompt type
        $args = $this->normalizePositionalArgs($args, $promptName);

        switch ($promptName) {
            case 'x-trace':
                return $this->executeXTrace($id, $args);

            case 'x-debug':
                return $this->executeXDebug($id, $args);

            case 'x-profile':
                return $this->executeXProfile($id, $args);

            case 'x-coverage':
                return $this->executeXCoverage($id, $args);

            default:
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => "Unknown prompt: {$promptName}",
                    ],
                ];
        }
    }

    /**
     * Convert positional arguments to named arguments based on prompt type
     */
    private function normalizePositionalArgs(array $args, string $promptName): array
    {
        // Only process if we have numeric keys (positional arguments)
        if (! isset($args[0])) {
            return $args;
        }

        switch ($promptName) {
            case 'x-trace':
                if (isset($args[0])) {
                    $args['script'] = $args[0];
                }

                if (isset($args[1])) {
                    $args['context'] = $args[1];
                }

                break;

            case 'x-debug':
                if (isset($args[0])) {
                    $args['script'] = $args[0];
                }

                if (isset($args[1])) {
                    $args['breakpoints'] = $args[1];
                }

                if (isset($args[2])) {
                    $args['steps'] = $args[2];
                }

                if (isset($args[3])) {
                    $args['context'] = $args[3];
                }

                break;

            case 'x-profile':
                if (isset($args[0])) {
                    $args['script'] = $args[0];
                }

                if (isset($args[1])) {
                    $args['context'] = $args[1];
                }

                break;

            case 'x-coverage':
                if (isset($args[0])) {
                    $args['script'] = $args[0];
                }

                if (isset($args[1])) {
                    $args['context'] = $args[1];
                }

                if (isset($args[2])) {
                    $args['format'] = $args[2];
                }

                break;
        }

        // Remove numeric keys to avoid confusion
        $filteredArgs = [];
        foreach ($args as $key => $value) {
            if (! is_numeric($key)) {
                $filteredArgs[$key] = $value;
            }
        }

        return $filteredArgs;
    }

    /**
     * Process script argument by removing Claude CLI quotes
     */
    private function processScriptArgument(string $script): string
    {
        // Fix incomplete quotes from Claude CLI (handles truncated input)
        if (str_starts_with($script, '"') && ! str_ends_with($script, '"')) {
            // Remove leading quote from incomplete input
            $script = substr($script, 1);
        }
        // Strip complete outer double quotes if present (Claude CLI client adds extra quotes)
        elseif (strlen($script) >= 2 && str_starts_with($script, '"') && str_ends_with($script, '"')) {
            $script = substr($script, 1, -1);
        }
        // Handle trailing quote without leading quote (Claude CLI parsing issue)
        elseif (str_ends_with($script, '"') && ! str_starts_with($script, '"')) {
            $script = substr($script, 0, -1);
        }

        return $script;
    }

    /**
     * Validate that script starts with PHP binary (any PHP executable)
     */
    private function validatePhpBinaryScript(string $script): void
    {
        if (empty($script)) {
            throw new InvalidArgumentException('Script argument is required');
        }

        // Check that script starts with PHP binary (handles paths like /usr/bin/php, /path/to/php83/php)
        if (! preg_match('/^(\S*php)(\s+|$)/', $script)) {
            throw new InvalidArgumentException('Script must start with PHP binary. Examples: "php script.php", "/usr/bin/php script.php", "/path/to/php83/php script.php". Received: "' . $script . '"');
        }
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

            case 'x-trace':
                $result = $this->executeXTrace(null, $arguments);

                return $result['result']['messages'][0]['content']['text'] ?? 'No result';

            case 'x-profile':
                $result = $this->executeXProfile(null, $arguments);

                return $result['result']['messages'][0]['content']['text'] ?? 'No result';

            case 'x-debug':
                $result = $this->executeXDebug(null, $arguments);

                return $result['result']['messages'][0]['content']['text'] ?? 'No result';

            case 'x-coverage':
                $result = $this->executeXCoverage(null, $arguments);

                return $result['result']['messages'][0]['content']['text'] ?? 'No result';

            default:
                throw new InvalidToolException("Unknown tool: $toolName");
        }
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

    private function executeXTrace(mixed $id, array $args): array
    {
        try {
            $script = $args['script'] ?? '';
            $script = $this->processScriptArgument($script);
            $this->validatePhpBinaryScript($script);
            $context = $args['context'] ?? '';


            // Build command - user must specify PHP binary explicitly
            $cmd = './bin/xdebug-trace --json -- ' . $script;

            // Execute command
            $output = [];
            $returnCode = 0;
            exec($cmd . ' 2>&1', $output, $returnCode);

            // Handle common error cases
            $outputText = implode("\n", $output);
            if ($returnCode !== 0 && str_contains($outputText, 'No such file')) {
                throw new FileNotFoundException('Script file not found: ' . $script);
            }

            if ($returnCode !== 0 && str_contains($outputText, 'Permission denied')) {
                throw new InvalidArgumentException('Permission denied accessing: ' . $script);
            }

            $result = [
                'command' => $cmd,
                'exit_code' => $returnCode,
                'output' => $outputText,
                'context' => $context,
                'script' => $script,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'messages' => [
                        [
                            'role' => 'assistant',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Forward Trace execution ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$script}\n**Context**: {$context}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Output**:\n```\n" . $outputText . "\n```",
                            ],
                        ],
                    ],
                    'debug_data' => $result,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'x-trace execution failed: ' . $e->getMessage(),
                ],
            ];
        }
    }

    private function executeXDebug(mixed $id, array $args): array
    {
        try {
            $script = $args['script'] ?? '';
            $script = $this->processScriptArgument($script);

            // Claude CLI workaround: If script is just "php" and breakpoints contains a .php file, reconstruct
            if (trim($script) === 'php') {
                $breakpoints = $args['breakpoints'] ?? '';
                $breakpoints = $this->processScriptArgument($breakpoints);

                // If breakpoints contains what looks like a PHP file, use it to reconstruct the script
                if (str_contains($breakpoints, '.php') && ! str_contains($breakpoints, ':')) {
                    $script = 'php ' . $breakpoints;
                    $args['breakpoints'] = ''; // Clear breakpoints since we used it for script reconstruction
                }
            }

            $this->validatePhpBinaryScript($script);
            $context = $args['context'] ?? '';
            $breakpoints = $args['breakpoints'] ?? '';
            $breakpoints = $this->processScriptArgument($breakpoints); // Process quotes in breakpoints too

            // Claude CLI bug workaround: if breakpoints contains a script-like value, treat as empty
            if (str_contains($breakpoints, '.php') && ! str_contains($breakpoints, ':')) {
                $breakpoints = '';
            }

            $steps = $args['steps'] ?? '100';


            // Build command
            $cmd = './bin/xdebug-debug --exit-on-break';

            // Add breakpoints if specified
            if (! empty($breakpoints)) {
                $cmd .= ' --break=' . escapeshellarg($breakpoints);
            }

            if (! empty($context)) {
                $cmd .= ' --context=' . escapeshellarg($context);
            }

            // Note: --steps parameter causes issues, temporarily disabled
            // if (! empty($steps)) {
            //     $cmd .= ' --steps=' . escapeshellarg($steps);
            // }

            // Build command - user must specify PHP binary explicitly
            $cmd .= ' -- ' . $script;

            // Execute command
            $output = [];
            $returnCode = 0;
            exec($cmd . ' 2>&1', $output, $returnCode);

            // Handle common error cases with user-friendly messages
            $outputText = implode("\n", $output);
            if ($returnCode === 255 && str_contains($outputText, 'Breakpoint file not found')) {
                throw new InvalidArgumentException('Invalid breakpoint format. Use: file.php:line or file.php:line:condition');
            }

            if ($returnCode === 255 && str_contains($outputText, 'RuntimeException')) {
                if (preg_match('/RuntimeException: (.+?) in/', $outputText, $matches)) {
                    throw new InvalidArgumentException('Debug error: ' . $matches[1]);
                }
            }

            $result = [
                'command' => $cmd,
                'exit_code' => $returnCode,
                'output' => $outputText,
                'context' => $context,
                'script' => $script,
                'breakpoints' => $breakpoints,
                'steps' => $steps,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'messages' => [
                        [
                            'role' => 'assistant',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Forward Trace debugging ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$script}\n**Context**: {$context}\n**Breakpoints**: {$breakpoints}\n**Steps**: {$steps}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Debug Output**:\n```\n" . $outputText . "\n```",
                            ],
                        ],
                    ],
                    'debug_data' => $result,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'x-debug execution failed: ' . $e->getMessage(),
                ],
            ];
        }
    }

    private function executeXProfile(mixed $id, array $args): array
    {
        try {
            $script = $args['script'] ?? '';
            $script = $this->processScriptArgument($script);
            $this->validatePhpBinaryScript($script);
            $context = $args['context'] ?? '';


            // Build command - user must specify PHP binary explicitly
            $cmd = './bin/xdebug-profile --json -- ' . $script;

            // Execute command
            $output = [];
            $returnCode = 0;
            exec($cmd . ' 2>&1', $output, $returnCode);

            // Handle common error cases
            $outputText = implode("\n", $output);
            if ($returnCode !== 0 && str_contains($outputText, 'No such file')) {
                throw new FileNotFoundException('Script file not found: ' . $script);
            }

            if ($returnCode !== 0 && str_contains($outputText, 'Permission denied')) {
                throw new InvalidArgumentException('Permission denied accessing: ' . $script);
            }

            $result = [
                'command' => $cmd,
                'exit_code' => $returnCode,
                'output' => $outputText,
                'context' => $context,
                'script' => $script,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'messages' => [
                        [
                            'role' => 'assistant',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Performance profiling ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$script}\n**Context**: {$context}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Profile Analysis**:\n```\n" . $outputText . "\n```",
                            ],
                        ],
                    ],
                    'debug_data' => $result,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'x-profile execution failed: ' . $e->getMessage(),
                ],
            ];
        }
    }

    private function executeXCoverage(mixed $id, array $args): array
    {
        try {
            $script = $args['script'] ?? '';
            $script = $this->processScriptArgument($script);
            $this->validatePhpBinaryScript($script);
            $context = $args['context'] ?? '';
            $format = $args['format'] ?? 'json';


            // Build command - user must specify PHP binary explicitly
            $cmd = './bin/xdebug-coverage -- ' . $script;

            // Execute command
            $output = [];
            $returnCode = 0;
            exec($cmd . ' 2>&1', $output, $returnCode);

            // Handle common error cases
            $outputText = implode("\n", $output);
            if ($returnCode !== 0 && str_contains($outputText, 'No such file')) {
                throw new FileNotFoundException('Script file not found: ' . $script);
            }

            if ($returnCode !== 0 && str_contains($outputText, 'Permission denied')) {
                throw new InvalidArgumentException('Permission denied accessing: ' . $script);
            }

            $result = [
                'command' => $cmd,
                'exit_code' => $returnCode,
                'output' => $outputText,
                'context' => $context,
                'script' => $script,
                'format' => $format,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'messages' => [
                        [
                            'role' => 'assistant',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Code coverage analysis ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$script}\n**Context**: {$context}\n**Format**: {$format}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Coverage Report**:\n```\n" . $outputText . "\n```",
                            ],
                        ],
                    ],
                    'debug_data' => $result,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'x-coverage execution failed: ' . $e->getMessage(),
                ],
            ];
        }
    }
}
