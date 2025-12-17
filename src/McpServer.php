<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use JsonException;
use Koriym\XdebugMcp\DTO\GenericResult;
use Koriym\XdebugMcp\DTO\JsonRpcResponse;
use Koriym\XdebugMcp\DTO\McpTool;
use Koriym\XdebugMcp\DTO\ToolsListResult;
use Koriym\XdebugMcp\Exceptions\FileNotFoundException;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use Koriym\XdebugMcp\Exceptions\InvalidToolException;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function date;
use function dirname;
use function error_log;
use function escapeshellarg;
use function exec;
use function explode;
use function fflush;
use function fgets;
use function file;
use function file_exists;
use function file_get_contents;
use function getcwd;
use function getenv;
use function implode;
use function in_array;
use function is_array;
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
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

use const JSON_THROW_ON_ERROR;
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
     * @param array<string, string|int|null> $data
     *
     * @codeCoverageIgnore Uses error_log() side effect - difficult to test without mocking global functions
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
                'Capture call stack (backtrace) at specific line - Lightweight, non-interactive stack trace collection | Use when: Need backtrace/stack trace at specific location | ex) ./xback --break="app.php:50" "php app.php"',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to get backtrace from (e.g., "tests/fixtures/debug_test.php")',
                        ],
                        'breakpoint' => [
                            'type' => 'string',
                            'description' => 'Line location to capture backtrace (e.g., "file.php:15")',
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
                    } catch (JsonException) {
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

                    // Validate request is an array
                    if (! is_array($request)) {
                        $errorResponse = JsonRpcResponse::error(null, -32600, 'Invalid Request: expected object');
                        echo json_encode($errorResponse, JSON_THROW_ON_ERROR) . "\n";
                        fflush(STDOUT);
                        $input = '';

                        continue;
                    }

                    /** @var array{method?: string, params?: array<string, string|int|bool|array<string, string|int|bool>>, id?: string|int|null} $request */
                    $requestMethod = $request['method'] ?? 'unknown';
                    $requestId = $request['id'] ?? null;

                    error_log('DEBUG: Processing request method = ' . $requestMethod);

                    try {
                        $response = $this->handleRequest($request);

                        if ($response instanceof JsonRpcResponse) {
                            $this->debugLog('Sending response', ['id' => $response->id]);
                            echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
                            fflush(STDOUT);
                        }
                    } catch (Throwable $e) {
                        error_log('DEBUG: MCP Server Error for method ' . $requestMethod . ': ' . $e->getMessage());
                        error_log('MCP Server Error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());

                        $errorResponse = JsonRpcResponse::error($requestId, -32603, 'Internal error: ' . $e->getMessage());
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
        } catch (JsonException) {
            return false;
        }
    }

    /** @param array{method?: string, params?: array<string, string|int|bool|array<string, string|int|bool>>, id?: string|int|null} $request */
    private function handleRequest(array $request): JsonRpcResponse|null
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

    /** @param array<string, string|int|bool|array<string, string|int|bool>> $params */
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
                'resources' => ['listChanged' => false],
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
                    'description' => 'Capture call stack (backtrace) at specific line - Lightweight, non-interactive stack trace collection | Use when: Need backtrace/stack trace at specific location | ex) /xback --script="app.php" --break="app.php:50"',
                    'arguments' => [
                        [
                            'name' => 'script',
                            'description' => 'PHP script to get backtrace from (e.g., "tests/fixtures/debug_test.php")',
                            'required' => true,
                        ],
                        [
                            'name' => 'breakpoint',
                            'description' => 'Line location to capture backtrace (e.g., "file.php:15")',
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

    /** @param array<string, string|int|bool|array<string, string|int|bool>> $params */
    private function handlePromptsGet(string|int|null $id, array $params): JsonRpcResponse
    {
        $promptName = isset($params['name']) && is_string($params['name']) ? $params['name'] : '';
        /** @var array<string, string> $args */
        $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];

        // Check if arguments contain CLI-style string that needs normalization
        if (isset($args['cli'])) {
            try {
                $normalizer = new CLIParamsNormalizer();
                $cliParams = $normalizer->normalize($args['cli']);
                unset($args['cli']); // Remove the raw CLI string
                // Merge string params, then map positional args to named args
                $args = array_merge($args, $cliParams->toStringArray());
                $args = $this->mapPositionalArgs($args, $cliParams->positionalArgs, $promptName);
            } catch (\InvalidArgumentException $e) {
                return JsonRpcResponse::error($id, -32602, 'CLI引数正規化エラー: ' . $e->getMessage());
            }
        }

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
     * Map positional arguments to named arguments based on prompt type
     *
     * @param array<string, string> $args           Named arguments
     * @param list<string>          $positionalArgs Positional arguments from CLI
     *
     * @return array<string, string>
     */
    private function mapPositionalArgs(array $args, array $positionalArgs, string $promptName): array
    {
        if ($positionalArgs === []) {
            return $args;
        }

        $mapping = match ($promptName) {
            'xtrace', 'xprofile' => ['script', 'context'],
            'xstep' => ['script', 'breakpoints', 'steps', 'context'],
            'xcoverage' => ['script', 'context', 'format'],
            'xback' => ['script', 'breakpoint', 'depth', 'context'],
            default => [],
        };

        foreach ($positionalArgs as $index => $value) {
            if (isset($mapping[$index])) {
                $args[$mapping[$index]] = $value;
            }
        }

        return $args;
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
        } elseif (strlen($script) >= 2 && str_starts_with($script, '"') && str_ends_with($script, '"')) {
            // Strip complete outer double quotes if present (Claude CLI client adds extra quotes)
            $script = substr($script, 1, -1);
        } elseif (str_ends_with($script, '"') && ! str_starts_with($script, '"')) {
            // Handle trailing quote without leading quote (Claude CLI parsing issue)
            $script = substr($script, 0, -1);
        }

        // If the command already starts with a PHP binary (php, php8.2, /usr/bin/php) keep as-is
        if (preg_match('/^(\S*\/)?php([0-9.]*)?(\s|$)/', $script)) {
            return $script;
        }

        // Also keep plain PHP script paths (e.g., "demo.php", "./bin/cli.php", "app.php --flag")
        if (preg_match('/^\S+\.php(\s|$)/', $script)) {
            return $script;
        }

        // Otherwise, prepend php for non-PHP commands (e.g., "script.py" -> "php script.py")
        return 'php ' . $script;
    }

    /**
     * Validate that script starts with PHP binary (any PHP executable)
     */
    private function validatePhpBinaryScript(string $script): void
    {
        if ($script === '') {
            throw new InvalidArgumentException('Script argument is required');
        }

        // Check that script starts with PHP binary (handles php, php8.1, /usr/bin/php, /path/to/php83/php, etc.)
        if (! preg_match('/^(\S*\/)?php([0-9.]*)?(\\s+|$)/i', $script)) {
            throw new InvalidArgumentException('Script must start with PHP binary. Examples: "php script.php", "php8.1 script.php", "/usr/bin/php script.php". Received: "' . $script . '"');
        }
    }

    /**
     * Validate breakpoint specifications
     * Format: "file.php:line" or "file.php:line:condition"
     * Multiple breakpoints: "file1.php:10,file2.php:20"
     *
     * @throws InvalidArgumentException If breakpoint format is invalid or file doesn't exist.
     */
    private function validateBreakpoints(string $breakpoints): void
    {
        // Filter out empty entries from trailing commas (e.g., "a.php:1,")
        $breakpointList = array_filter(array_map('trim', explode(',', $breakpoints)));

        foreach ($breakpointList as $breakpoint) {
            // Parse breakpoint format: file:line or file:line:condition
            // Use limit 3 to preserve colons in conditions (e.g., "file.php:10:$a==$b:1")
            $parts = explode(':', $breakpoint, 3);
            if (count($parts) < 2) {
                throw new InvalidArgumentException(
                    'Invalid breakpoint format: "' . $breakpoint . '". ' .
                    'Expected format: "file.php:line" or "file.php:line:condition"',
                );
            }

            $file = $parts[0];
            $line = $parts[1];

            // Validate line number is numeric
            if (! is_numeric($line)) {
                throw new InvalidArgumentException(
                    'Invalid line number in breakpoint "' . $breakpoint . '": "' . $line . '" is not a number',
                );
            }

            $lineNumber = (int) $line;
            if ($lineNumber < 1) {
                throw new InvalidArgumentException(
                    'Invalid line number in breakpoint "' . $breakpoint . '": line number must be >= 1',
                );
            }

            // Convert to absolute path if relative
            $absolutePath = $file;
            if (! str_starts_with($file, '/')) {
                $absolutePath = getcwd() . '/' . $file;
            }

            // Check if file exists
            if (! file_exists($absolutePath)) {
                throw new InvalidArgumentException(
                    'Breakpoint file not found: "' . $file . '"' .
                    ($absolutePath !== $file ? ' (resolved to: "' . $absolutePath . '")' : ''),
                );
            }

            // Validate line number is within file bounds
            $fileContents = file($absolutePath);
            if ($fileContents === false) {
                throw new InvalidArgumentException(
                    'Cannot read breakpoint file: "' . $file . '"',
                );
            }

            $totalLines = count($fileContents);
            if ($lineNumber > $totalLines) {
                throw new InvalidArgumentException(
                    'Invalid line number in breakpoint "' . $breakpoint . '": ' .
                    'line ' . $lineNumber . ' exceeds file length (' . $totalLines . ' lines)',
                );
            }
        }
    }

    /** @param array<string, string|int|bool|array<string, string|int|bool>> $params */
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

    /** @param array<string, string> $arguments */
    private function executeToolCall(string $toolName, array $arguments): string
    {
        switch ($toolName) {
            case 'xtrace':
                $result = $this->executeXTrace(null, $arguments);

                return $this->extractResultText($result);

            case 'xprofile':
                $result = $this->executeXProfile(null, $arguments);

                return $this->extractResultText($result);

            case 'xstep':
                $result = $this->executeXDebug(null, $arguments);

                return $this->extractResultText($result);

            case 'xcoverage':
                $result = $this->executeXCoverage(null, $arguments);

                return $this->extractResultText($result);

            case 'xback':
                $result = $this->executeXBacktrace(null, $arguments);

                return $this->extractResultText($result);

            default:
                throw new InvalidToolException("Unknown tool: $toolName");
        }
    }

    /**
     * Extract text content from JsonRpcResponse result
     */
    private function extractResultText(JsonRpcResponse $response): string
    {
        $data = $response->result?->jsonSerialize();
        if (! is_array($data)) {
            return 'No result';
        }

        if (! isset($data['messages']) || ! is_array($data['messages'])) {
            return 'No result';
        }

        $firstMessage = $data['messages'][0] ?? null;
        if (! is_array($firstMessage)) {
            return 'No result';
        }

        $content = $firstMessage['content'] ?? null;
        if (! is_array($content)) {
            return 'No result';
        }

        return isset($content['text']) && is_string($content['text']) ? $content['text'] : 'No result';
    }

    /** @param array<string, string> $args */
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

    /** @param array<string, string> $args */
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
            // Note: Do NOT apply processScriptArgument() to breakpoints
            // It would incorrectly prepend 'php ' to "file.php:15" making it "php file.php:15"

            // Claude CLI bug workaround: if breakpoints contains a script-like value, treat as empty
            if (str_contains($breakpoints, '.php') && ! str_contains($breakpoints, ':')) {
                $breakpoints = '';
            }

            $steps = $args['steps'] ?? '100';
            $includeVendor = $args['include_vendor'] ?? '';

            // Validate breakpoints if specified
            if ($breakpoints !== '') {
                $this->validateBreakpoints($breakpoints);
            }

            // Build command
            $cmd = $this->binDir . '/xstep --json --exit-on-break';

            // Add breakpoints if specified
            if ($breakpoints !== '') {
                $cmd .= ' --break=' . escapeshellarg($breakpoints);
            }

            if ($context !== '') {
                $cmd .= ' --context=' . escapeshellarg((string) $context);
            }

            // Add steps parameter if specified
            if ($steps !== '') {
                $cmd .= ' --steps=' . escapeshellarg($steps);
            }

            // Add include_vendor option if specified
            if ($includeVendor !== '') {
                $cmd .= ' --include-vendor=' . escapeshellarg((string) $includeVendor);
            }

            // Build command - user must specify PHP binary explicitly
            $cmd .= ' -- ' . $script;

            // Execute command and redirect output to temp file (shutdown function output requires file redirect)
            $tmpFile = tempnam(sys_get_temp_dir(), 'xstep_');
            $returnCode = 0;

            // Handle tempnam() failure
            if ($tmpFile === false) {
                // Fallback to direct exec without temp file
                $output = [];
                exec($cmd . ' 2>&1', $output, $returnCode);
                $outputText = implode("\n", $output);
            } else {
                $output = [];
                exec($cmd . ' > ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $returnCode);

                // Read output from temp file
                $outputText = '';
                if (file_exists($tmpFile)) {
                    $outputText = file_get_contents($tmpFile) ?: '';
                    unlink($tmpFile);
                }
            }

            // Handle common error cases with user-friendly messages
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

    /** @param array<string, string> $args */
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

    /** @param array<string, string> $args */
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

    /** @param array<string, string> $args */
    private function executeXBacktrace(string|int|null $id, array $args): JsonRpcResponse
    {
        try {
            $originalScript = $args['script'] ?? '';
            $script = $this->processScriptArgument($originalScript);
            $this->validatePhpBinaryScript($script);
            $context = $args['context'] ?? '';
            $breakpoint = $args['breakpoint'] ?? '';
            $depth = $args['depth'] ?? '10';

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
