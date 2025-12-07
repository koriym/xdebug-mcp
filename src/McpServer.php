<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\DTO\GenericResult;
use Koriym\XdebugMcp\DTO\JsonRpcResponse;
use Koriym\XdebugMcp\DTO\McpTool;
use Koriym\XdebugMcp\DTO\ToolsListResult;
use Koriym\XdebugMcp\Exceptions\FileNotFoundException;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use Koriym\XdebugMcp\Exceptions\InvalidToolException;
use Throwable;

use function array_merge;
use function array_values;
use function date;
use function dirname;
use function error_log;
use function escapeshellarg;
use function exec;
use function fflush;
use function fgets;
use function getenv;
use function implode;
use function in_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

use const STDIN;
use const STDOUT;

final class McpServer
{
    /** @var array<string, McpTool> */
    protected array $tools = [];
    private bool $debugMode = false;
    private readonly string $binDir;

    public function __construct()
    {
        $this->debugMode = (bool) (getenv('MCP_DEBUG') ?: false);
        // Use absolute path to bin directory for standalone execution
        $this->binDir = dirname(__DIR__) . '/bin';
        $this->initializeTools();
    }

    /**
     * @codeCoverageIgnore Uses error_log() side effect - difficult to test without mocking global functions
     *
     * @param array<string, mixed> $data
     */
    private function debugLog(string $message, array $data = []): void
    {
        if ($this->debugMode) {
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => $message,
                'data' => $data,
            ];
            error_log('MCP Debug: ' . json_encode($logData, JSON_THROW_ON_ERROR));
        }
    }

    private function initializeTools(): void
    {
        $this->tools = [
            'xtrace' => new McpTool(
                'xtrace',
                'Trace PHP execution flow | ex) ./xtrace "php test.php" "Debug login flow" | PHPUnit: ./xtrace "php vendor/bin/phpunit --filter testMethod TestClass.php" "Testing user auth"',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to trace (e.g., "tests/fixtures/debug_test.php")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for AI analysis (e.g., "Testing user authentication flow")',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
            'xprofile' => new McpTool(
                'xprofile',
                'Profile performance bottlenecks | ex) ./xprofile "php slow-app.php" "API performance"',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to profile (e.g., "tests/fixtures/performance_test.php")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for performance analysis',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
            'xstep' => new McpTool(
                'xstep',
                'Step debugging with breakpoints | ex) /xstep --script="php test.php" --break="test.php:15:$user==null" --steps=100 --context="debug context"',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to debug (e.g., "tests/fixtures/debug_test.php")',
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
                        'include_vendor' => [
                            'type' => 'string',
                            'description' => 'Vendor packages to include in trace (e.g., "bear/resource,ray/di", "bear/*", "*/*")',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
            'xcoverage' => new McpTool(
                'xcoverage',
                'Analyze test coverage | ex) ./xcoverage "php vendor/bin/phpunit UserTest.php"',
                [
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
            ),
            'xback' => new McpTool(
                'xback',
                'Get stack trace (backtrace) at breakpoint | ex) ./xback --break="app.php:50" "php app.php"',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to get backtrace from (e.g., "tests/fixtures/debug_test.php")',
                        ],
                        'breakpoint' => [
                            'type' => 'string',
                            'description' => 'Breakpoint location (e.g., "file.php:15")',
                            'default' => '',
                        ],
                        'depth' => [
                            'type' => 'integer',
                            'description' => 'Maximum stack depth to return',
                            'default' => 10,
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for backtrace analysis',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
        ];
    }

    /**
     * Main MCP server entry point - handles STDIN/STDOUT communication
     *
     * @codeCoverageIgnore Requires STDIN input stream, infinite loop, and process control - difficult to test in unit tests
     */
    public function __invoke(): void
    {
        try {
            $input = '';

            while (($line = fgets(STDIN)) !== false) {
                $input .= $line;

                if ($this->isCompleteJsonRpc($input)) {
                    error_log('DEBUG: Raw Claude CLI input = ' . trim($input));

                    try {
                        $request = json_decode(trim($input), true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        // @codeCoverageIgnoreStart - JSON parse error path rarely triggered in tests
                        // Invalid JSON, send parse error
                        $errorResponse = [
                            'jsonrpc' => '2.0',
                            'id' => null,
                            'error' => [
                                'code' => -32700,
                                'message' => 'Parse error',
                            ],
                        ];
                        echo json_encode($errorResponse, JSON_THROW_ON_ERROR) . "\n";
                        fflush(STDOUT);
                        $input = '';

                        continue;
                        // @codeCoverageIgnoreEnd
                    }

                    error_log('DEBUG: Processing request method = ' . ($request['method'] ?? 'unknown'));
                    $this->debugLog('Received request', $request);

                    try {
                        $response = $this->handleRequest($request);

                        if ($response !== null) {
                            $this->debugLog('Sending response', $response->toArray());
                            echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
                            fflush(STDOUT);
                        }
                    } catch (Throwable $e) {
                        error_log('DEBUG: MCP Server Error for method ' . ($request['method'] ?? 'unknown') . ': ' . $e->getMessage());
                        error_log('MCP Server Error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());

                        $errorResponse = JsonRpcResponse::error($request['id'] ?? null, -32603, 'Internal error: ' . $e->getMessage());
                        echo json_encode($errorResponse, JSON_THROW_ON_ERROR) . "\n";
                        fflush(STDOUT);
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
        if ($trimmed === '') {
            return false;
        }

        try {
            json_decode($trimmed, false, 512, JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * @param array{method?: string, params?: array<string, string|int|bool>, id?: string|int|null} $request
     */
    private function handleRequest(array $request): ?JsonRpcResponse
    {
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        try {
            return match ($method) {
                'initialize' => $this->handleInitialize($id, $params),
                'tools/list' => $this->handleToolsList($id),
                'tools/call' => $this->handleToolCall($id, $params),
                'resources/list' => $this->handleResourcesList($id),
                'prompts/list' => $this->handlePromptsList($id),
                'prompts/get' => $this->handlePromptsGet($id, $params),
                // Handle initialized notification (no response needed)
                'notifications/initialized' => null,
                default => JsonRpcResponse::error($id, -32601, "Method not found: {$method}"),
            };
        } catch (Throwable $e) {
            return JsonRpcResponse::error($id, -32000, 'Server error: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function handleInitialize(string|int|null $id, array $params): JsonRpcResponse
    {
        // Use the protocol version requested by the client, defaulting to latest
        $clientVersion = $params['protocolVersion'] ?? '2025-06-18';

        // Ensure we support the requested version
        $supportedVersions = ['2024-11-05', '2025-03-26', '2025-06-18'];
        if (! in_array($clientVersion, $supportedVersions, true)) {
            $clientVersion = '2025-06-18';
        }

        return JsonRpcResponse::success($id, new GenericResult([
            'protocolVersion' => $clientVersion,
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => [],
                'prompts' => ['listChanged' => true],
            ],
            'serverInfo' => [
                'name' => 'xdebug-mcp-server',
                'version' => '2.0.0',
            ],
        ]));
    }

    private function handleToolsList(string|int|null $id): JsonRpcResponse
    {
        return JsonRpcResponse::success($id, new ToolsListResult(array_values($this->tools)));
    }

    private function handleResourcesList(string|int|null $id): JsonRpcResponse
    {
        return JsonRpcResponse::success($id, new GenericResult([
            'resources' => [],
        ]));
    }

    private function handlePromptsList(string|int|null $id): JsonRpcResponse
    {
        return JsonRpcResponse::success($id, new GenericResult([
            'prompts' => [
                    [
                        'name' => 'xtrace',
                        'description' => 'Trace PHP execution flow | ex) /xtrace --script=test.php --context="Debug login flow" | PHPUnit: /xtrace --script="vendor/bin/phpunit --filter testMethod TestClass.php" --context="Testing user auth"',
                        'arguments' => [
                            [
                                'name' => 'script',
                                'description' => 'PHP script to trace (e.g., "tests/fixtures/debug_test.php") | PHPUnit: "vendor/bin/phpunit --filter testMethod TestClass.php"',
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
                        'name' => 'xstep',
                        'description' => 'Step debugging with breakpoints | ex) /xstep --script="php test.php" --break="test.php:15:$user==null" --steps=100 --context="debug context"',
                        'arguments' => [
                            [
                                'name' => 'script',
                                'description' => 'PHP script to debug (e.g., "tests/fixtures/debug_test.php")',
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
                        'name' => 'xprofile',
                        'description' => 'Profile performance bottlenecks | ex) /xprofile --script=slow-app.php --context="API performance"',
                        'arguments' => [
                            [
                                'name' => 'script',
                                'description' => 'PHP script to profile (e.g., "tests/fixtures/performance_test.php")',
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
                        'name' => 'xcoverage',
                        'description' => 'Analyze test coverage | ex) /xcoverage --script="vendor/bin/phpunit UserTest.php"',
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
                    [
                        'name' => 'xback',
                        'description' => 'Get stack trace (backtrace) at breakpoint | ex) /xback --script="app.php" --break="app.php:50"',
                        'arguments' => [
                            [
                                'name' => 'script',
                                'description' => 'PHP script to get backtrace from (e.g., "tests/fixtures/debug_test.php")',
                                'required' => true,
                            ],
                            [
                                'name' => 'breakpoint',
                                'description' => 'Breakpoint location (e.g., "file.php:15")',
                                'required' => false,
                            ],
                            [
                                'name' => 'depth',
                                'description' => 'Maximum stack depth to return (default: 10)',
                                'required' => false,
                            ],
                            [
                                'name' => 'context',
                                'description' => 'Context description for backtrace analysis',
                                'required' => false,
                            ],
                        ],
                    ],
            ],
        ]));
    }

    /**
     * @param array<string, string|int|bool|array<string, string>> $params
     */
    private function handlePromptsGet(string|int|null $id, array $params): JsonRpcResponse
    {
        $promptName = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        /** @var array<string, string> $args */
        $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];

        // Check if arguments contain CLI-style string that needs normalization
        if (isset($args['cli'])) {
            try {
                $normalizer = new CLIParamsNormalizer();
                $normalizedArgs = $normalizer->normalize($args['cli']);
                // Merge CLI-normalized params with any existing args (CLI takes precedence)
                $args = array_merge($args, $normalizedArgs);
                unset($args['cli']); // Remove the raw CLI string
            } catch (\InvalidArgumentException $e) {
                return JsonRpcResponse::error($id, -32602, 'CLI引数正規化エラー: ' . $e->getMessage());
            }
        }

        // Convert positional arguments to named arguments for each prompt type
        $args = $this->normalizePositionalArgs($args, $promptName);

        return match ($promptName) {
            'xtrace' => $this->executeXTrace($id, $args),
            'xstep' => $this->executeXDebug($id, $args),
            'xprofile' => $this->executeXProfile($id, $args),
            'xcoverage' => $this->executeXCoverage($id, $args),
            'xback' => $this->executeXBacktrace($id, $args),
            default => JsonRpcResponse::error($id, -32601, "Unknown prompt: {$promptName}"),
        };
    }

    /**
     * Convert positional arguments to named arguments based on prompt type
     *
     * @param array<array-key, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function normalizePositionalArgs(array $args, string $promptName): array
    {
        // Only process if we have numeric keys (positional arguments)
        if (! isset($args[0])) {
            /** @var array<string, mixed> $args */
            return $args;
        }

        switch ($promptName) {
            case 'xtrace':

            case 'xprofile':
                // $args[0] is guaranteed to exist (checked above)
                $args['script'] = $args[0];

                if (isset($args[1])) {
                    $args['context'] = $args[1];
                }

                break;
            case 'xstep':
                // $args[0] is guaranteed to exist (checked above)
                $args['script'] = $args[0];

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

            case 'xcoverage':
                // $args[0] is guaranteed to exist (checked above)
                $args['script'] = $args[0];

                if (isset($args[1])) {
                    $args['context'] = $args[1];
                }

                if (isset($args[2])) {
                    $args['format'] = $args[2];
                }

                break;

            case 'xback':
                // $args[0] is guaranteed to exist (checked above)
                $args['script'] = $args[0];

                if (isset($args[1])) {
                    $args['breakpoint'] = $args[1];
                }

                if (isset($args[2])) {
                    $args['depth'] = $args[2];
                }

                if (isset($args[3])) {
                    $args['context'] = $args[3];
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
        // Handle empty script first
        if (trim($script) === '') {
            return $script;
        }

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

        // Auto-prepend 'php' if script doesn't start with a PHP binary
        if (! preg_match('/^(\S*php)(\s+|$)/', $script)) {
            return 'php ' . $script;
        }

        return $script;
    }

    /**
     * Validate that script starts with PHP binary (any PHP executable)
     */
    private function validatePhpBinaryScript(string $script): void
    {
        if ($script === '') {
            throw new InvalidArgumentException('Script argument is required');
        }

        // Check that script starts with PHP binary (handles paths like /usr/bin/php, /path/to/php83/php)
        if (! preg_match('/^(\S*php)(\s+|$)/', $script)) {
            throw new InvalidArgumentException('Script must start with PHP binary. Examples: "php script.php", "/usr/bin/php script.php", "/path/to/php83/php script.php". Received: "' . $script . '"');
        }
    }

    /**
     * @param array<string, string|int|bool|array<string, string>> $params
     */
    private function handleToolCall(string|int|null $id, array $params): JsonRpcResponse
    {
        $toolName = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        /** @var array<string, string> $arguments */
        $arguments = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];

        try {
            $result = $this->executeToolCall($toolName, $arguments);

            return JsonRpcResponse::success($id, new GenericResult([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $result,
                    ],
                ],
            ]));
        } catch (Throwable $e) {
            return JsonRpcResponse::error($id, -32000, $e->getMessage());
        }
    }

    /**
     * @param array<string, string> $arguments
     */
    private function executeToolCall(string $toolName, array $arguments): string
    {
        switch ($toolName) {
            case 'xtrace':
                $result = $this->executeXTrace(null, $arguments);
                $data = $result->result?->jsonSerialize() ?? [];

                return $data['messages'][0]['content']['text'] ?? 'No result';

            case 'xprofile':
                $result = $this->executeXProfile(null, $arguments);
                $data = $result->result?->jsonSerialize() ?? [];

                return $data['messages'][0]['content']['text'] ?? 'No result';

            case 'xstep':
                $result = $this->executeXDebug(null, $arguments);
                $data = $result->result?->jsonSerialize() ?? [];

                return $data['messages'][0]['content']['text'] ?? 'No result';

            case 'xcoverage':
                $result = $this->executeXCoverage(null, $arguments);
                $data = $result->result?->jsonSerialize() ?? [];

                return $data['messages'][0]['content']['text'] ?? 'No result';

            case 'xback':
                $result = $this->executeXBacktrace(null, $arguments);
                $data = $result->result?->jsonSerialize() ?? [];

                return $data['messages'][0]['content']['text'] ?? 'No result';

            default:
                throw new InvalidToolException("Unknown tool: $toolName");
        }
    }

    /**
     * @param array<string, string> $args
     */
    private function executeXTrace(string|int|null $id, array $args): JsonRpcResponse
    {
        try {
            $originalScript = $args['script'] ?? '';
            $script = $this->processScriptArgument($originalScript);
            $this->validatePhpBinaryScript($script);
            $context = $args['context'] ?? '';

            // Build command - user must specify PHP binary explicitly
            $cmd = $this->binDir . '/xtrace --json -- ' . $script;

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

            return JsonRpcResponse::success($id, new GenericResult([
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => [
                            'type' => 'text',
                            'text' => 'Forward Trace execution ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$originalScript}\n**Context**: {$context}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Output**:\n```\n" . $outputText . "\n```",
                        ],
                    ],
                ],
                'debug_data' => [
                    'command' => $cmd,
                    'exit_code' => $returnCode,
                    'output' => $outputText,
                    'context' => $context,
                    'script' => $script,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]));
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart - Exception handling difficult to test without mocking shell commands
            return JsonRpcResponse::error($id, -32000, 'xtrace execution failed: ' . $e->getMessage());
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param array<string, string> $args
     */
    private function executeXDebug(string|int|null $id, array $args): JsonRpcResponse
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
            $includeVendor = $args['include_vendor'] ?? '';

            // Build command
            $cmd = $this->binDir . '/xstep --exit-on-break';

            // Add breakpoints if specified
            if ($breakpoints !== '') {
                $cmd .= ' --break=' . escapeshellarg($breakpoints);
            }

            if ($context !== '') {
                $cmd .= ' --context=' . escapeshellarg((string) $context);
            }

            // Note: --steps parameter causes issues, temporarily disabled
            // if ($steps !== '') {
            //     $cmd .= ' --steps=' . escapeshellarg($steps);
            // }

            // Add include_vendor option if specified
            if ($includeVendor !== '') {
                $cmd .= ' --include-vendor=' . escapeshellarg((string) $includeVendor);
            }

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

            if ($returnCode === 255 && str_contains($outputText, 'RuntimeException') && preg_match('/RuntimeException: (.+?) in/', $outputText, $matches)) {
                throw new InvalidArgumentException('Debug error: ' . $matches[1]);
            }

            return JsonRpcResponse::success($id, new GenericResult([
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => [
                            'type' => 'text',
                            'text' => 'Forward Trace debugging ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$script}\n**Context**: {$context}\n**Breakpoints**: {$breakpoints}\n**Steps**: {$steps}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Debug Output**:\n```\n" . $outputText . "\n```",
                        ],
                    ],
                ],
                'debug_data' => [
                    'command' => $cmd,
                    'exit_code' => $returnCode,
                    'output' => $outputText,
                    'context' => $context,
                    'script' => $script,
                    'breakpoints' => $breakpoints,
                    'steps' => $steps,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]));
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart - Exception handling difficult to test without mocking shell commands
            return JsonRpcResponse::error($id, -32000, 'xstep execution failed: ' . $e->getMessage());
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param array<string, string> $args
     */
    private function executeXProfile(string|int|null $id, array $args): JsonRpcResponse
    {
        try {
            $script = $args['script'] ?? '';
            $script = $this->processScriptArgument($script);
            $this->validatePhpBinaryScript($script);
            $context = $args['context'] ?? '';

            // Build command - user must specify PHP binary explicitly
            $cmd = $this->binDir . '/xprofile --json -- ' . $script;

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

            return JsonRpcResponse::success($id, new GenericResult([
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => [
                            'type' => 'text',
                            'text' => 'Performance profiling ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$script}\n**Context**: {$context}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Profile Analysis**:\n```\n" . $outputText . "\n```",
                        ],
                    ],
                ],
                'debug_data' => [
                    'command' => $cmd,
                    'exit_code' => $returnCode,
                    'output' => $outputText,
                    'context' => $context,
                    'script' => $script,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]));
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart - Exception handling path requires shell command failures which are difficult to reproduce consistently in tests
            return JsonRpcResponse::error($id, -32000, 'xprofile execution failed: ' . $e->getMessage());
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param array<string, string> $args
     */
    private function executeXCoverage(string|int|null $id, array $args): JsonRpcResponse
    {
        try {
            $script = $args['script'] ?? '';
            $script = $this->processScriptArgument($script);
            $this->validatePhpBinaryScript($script);
            $context = $args['context'] ?? '';
            $format = $args['format'] ?? 'json';

            // Build command - user must specify PHP binary explicitly
            $cmd = $this->binDir . '/xcoverage -- ' . $script;

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

            return JsonRpcResponse::success($id, new GenericResult([
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => [
                            'type' => 'text',
                            'text' => 'Code coverage analysis ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$script}\n**Context**: {$context}\n**Format**: {$format}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Coverage Report**:\n```\n" . $outputText . "\n```",
                        ],
                    ],
                ],
                'debug_data' => [
                    'command' => $cmd,
                    'exit_code' => $returnCode,
                    'output' => $outputText,
                    'context' => $context,
                    'script' => $script,
                    'format' => $format,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]));
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart - Exception handling path requires coverage collection failures which are environment-dependent and difficult to test reliably
            return JsonRpcResponse::error($id, -32000, 'xcoverage execution failed: ' . $e->getMessage());
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param array<string, string|int> $args
     */
    private function executeXBacktrace(string|int|null $id, array $args): JsonRpcResponse
    {
        try {
            $originalScript = isset($args['script']) ? (string) $args['script'] : '';
            $script = $this->processScriptArgument($originalScript);
            $this->validatePhpBinaryScript($script);
            $context = isset($args['context']) ? (string) $args['context'] : '';
            $breakpoint = isset($args['breakpoint']) ? (string) $args['breakpoint'] : '';
            $depth = $args['depth'] ?? 10;

            // Build command
            $cmd = $this->binDir . '/xback';

            // Add breakpoint if specified
            if ($breakpoint !== '') {
                $cmd .= ' --break=' . escapeshellarg($breakpoint);
            }

            if ($context !== '') {
                $cmd .= ' --context=' . escapeshellarg($context);
            }

            if ($depth !== '' && (int) $depth > 0 && (int) $depth <= 1000) {
                $cmd .= ' --depth=' . escapeshellarg((string) (int) $depth);
            }

            // Build command - user must specify PHP binary explicitly
            $cmd .= ' -- ' . $script;

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

            return JsonRpcResponse::success($id, new GenericResult([
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => [
                            'type' => 'text',
                            'text' => 'Stack trace (backtrace) ' . ($returnCode === 0 ? 'retrieved' : 'failed') . ":\n\n**Script**: {$originalScript}\n**Context**: {$context}\n**Breakpoint**: {$breakpoint}\n**Depth**: {$depth}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Stack Trace**:\n```\n" . $outputText . "\n```",
                        ],
                    ],
                ],
                'debug_data' => [
                    'command' => $cmd,
                    'exit_code' => $returnCode,
                    'output' => $outputText,
                    'context' => $context,
                    'script' => $script,
                    'breakpoint' => $breakpoint,
                    'depth' => $depth,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]));
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart - Exception handling path requires backtrace failures which are environment-dependent
            return JsonRpcResponse::error($id, -32000, 'xback execution failed: ' . $e->getMessage());
            // @codeCoverageIgnoreEnd
        }
    }
}
