<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\Exceptions\FileNotFoundException;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use Koriym\XdebugMcp\Exceptions\InvalidToolException;
use Throwable;

use function array_merge;
use function array_values;
use function date;
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
use function json_last_error;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

use const JSON_ERROR_NONE;
use const STDIN;
use const STDOUT;

final class McpServer
{
    protected array $tools = [];
    private bool $debugMode = false;

    public function __construct()
    {
        $this->debugMode = (bool) (getenv('MCP_DEBUG') ?: false);
        $this->initializeTools();
    }

    /** @codeCoverageIgnore Uses error_log() side effect - difficult to test without mocking global functions */
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
            'x-trace' => [
                'name' => 'x-trace',
                'description' => 'Trace PHP execution flow | ex) ./x-trace "php test.php" "Debug login flow" | PHPUnit: ./x-trace "php vendor/bin/phpunit --filter testMethod TestClass.php" "Testing user auth"',
                'inputSchema' => [
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
            ],
            'x-profile' => [
                'name' => 'x-profile',
                'description' => 'Profile performance bottlenecks | ex) ./x-profile "php slow-app.php" "API performance"',
                'inputSchema' => [
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
            ],
            'x-debug' => [
                'name' => 'x-debug',
                'description' => 'Step debugging with breakpoints | ex) /x-debug --script="php test.php" --break="test.php:15:$user==null" --steps=100 --context="debug context"',
                'inputSchema' => [
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
                    ],
                    'required' => ['script'],
                ],
            ],
            'x-coverage' => [
                'name' => 'x-coverage',
                'description' => 'Analyze test coverage | ex) ./x-coverage "php vendor/bin/phpunit UserTest.php"',
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
                    $request = json_decode(trim($input), true);

                    if ($request === null) {
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
                        echo json_encode($errorResponse) . "\n";
                        fflush(STDOUT);
                        // @codeCoverageIgnoreEnd
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
                        'description' => 'Trace PHP execution flow | ex) /x-trace --script=test.php --context="Debug login flow" | PHPUnit: /x-trace --script="vendor/bin/phpunit --filter testMethod TestClass.php" --context="Testing user auth"',
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
                        'name' => 'x-debug',
                        'description' => 'Step debugging with breakpoints | ex) /x-debug --script="php test.php" --break="test.php:15:$user==null" --steps=100 --context="debug context"',
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
                        'name' => 'x-profile',
                        'description' => 'Profile performance bottlenecks | ex) /x-profile --script=slow-app.php --context="API performance"',
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
        // Handle empty script first
        if (empty(trim($script))) {
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
            $script = 'php ' . $script;
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

    private function executeXTrace(mixed $id, array $args): array
    {
        try {
            $originalScript = $args['script'] ?? '';
            $script = $this->processScriptArgument($originalScript);
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
                                'text' => 'Forward Trace execution ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$originalScript}\n**Context**: {$context}\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Output**:\n```\n" . $outputText . "\n```",
                            ],
                        ],
                    ],
                    'debug_data' => $result,
                ],
            ];
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart - Exception handling difficult to test without mocking shell commands
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'x-trace execution failed: ' . $e->getMessage(),
                ],
            ];
            // @codeCoverageIgnoreEnd
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
            // @codeCoverageIgnoreStart - Exception handling difficult to test without mocking shell commands
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'x-debug execution failed: ' . $e->getMessage(),
                ],
            ];
            // @codeCoverageIgnoreEnd
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
            // @codeCoverageIgnoreStart - Exception handling path requires shell command failures which are difficult to reproduce consistently in tests
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'x-profile execution failed: ' . $e->getMessage(),
                ],
            ];
            // @codeCoverageIgnoreEnd
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
            // @codeCoverageIgnoreStart - Exception handling path requires coverage collection failures which are environment-dependent and difficult to test reliably
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32000,
                    'message' => 'x-coverage execution failed: ' . $e->getMessage(),
                ],
            ];
            // @codeCoverageIgnoreEnd
        }
    }
}
