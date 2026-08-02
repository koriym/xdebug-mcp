<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use JsonException;
use Koriym\XdebugMcp\DTO\GenericResult;
use Koriym\XdebugMcp\DTO\JsonRpcError;
use Koriym\XdebugMcp\DTO\JsonRpcResponse;
use Koriym\XdebugMcp\DTO\McpTool;
use Koriym\XdebugMcp\DTO\ToolsListResult;
use Koriym\XdebugMcp\Exceptions\FileNotFoundException;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use Koriym\XdebugMcp\Exceptions\InvalidToolException;
use Koriym\XdebugMcp\Utilities\PhpCommandParser;
use Throwable;

use function array_filter;
use function array_key_exists;
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

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const STDIN;
use const STDOUT;

final class McpServer
{
    /** Supported MCP protocol versions, oldest first (dual-era: legacy + stateless) */
    private const SUPPORTED_VERSIONS = ['2024-11-05', '2025-03-26', '2025-06-18', '2025-11-25', '2026-07-28'];

    /** Legacy (initialize-handshake) revisions, oldest first */
    private const LEGACY_VERSIONS = ['2024-11-05', '2025-03-26', '2025-06-18', '2025-11-25'];

    /** Latest legacy revision — the fallback for initialize requests; the stateless 2026-07-28 has no handshake and must not be named in an initialize result */
    private const LATEST_LEGACY_VERSION = '2025-11-25';
    private const INSTRUCTIONS = 'PHP debugging and analysis tools using Xdebug. Use when asked to trace, debug, profile, analyze coverage, or compare executions of PHP code. Tools: xtrace (execution flow), xstep (breakpoint debugging), xprofile (performance), xcoverage (test coverage), xback (stack traces), xcompare (breakpoint variable comparison across two runs).';

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
        if (! $this->debugMode) {
            return;
        }

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'data' => $data,
        ];
        // Substitute (don't throw on) non-UTF-8 bytes in the logged payload —
        // an unset MCP_DEBUG already skips this, but with it set a non-UTF-8
        // request line must not be able to wedge the loop from here either.
        error_log('MCP Debug: ' . json_encode($logData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function initializeTools(): void
    {
        $this->tools = [
            'xtrace' => new McpTool(
                'xtrace',
                'Trace PHP execution flow. Returns JSON with $schema URL for semantic details and AI analysis strategies. Key fields: {lines, functions, max_depth, db_queries}. Vendor excluded by default.',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to trace (e.g., "vendor/bin/phpunit --filter testMethod")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for AI analysis (e.g., "Debug login failure")',
                            'default' => '',
                        ],
                        'include_vendor' => [
                            'type' => 'string',
                            'description' => 'Include vendor packages in trace (e.g., "bear/*,ray/di" or "*/*" for all)',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
            'xprofile' => new McpTool(
                'xprofile',
                'Profile performance bottlenecks. Returns JSON with a schema URL; time_ms/memory_mb are measured from the cachegrind summary and bottlenecks is a structured array — read the schema for field shapes. Key fields: {time_ms, memory_mb, bottlenecks}. Vendor excluded by default.',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to profile (e.g., "vendor/bin/phpunit --filter testMethod")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for performance analysis',
                            'default' => '',
                        ],
                        'include_vendor' => [
                            'type' => 'string',
                            'description' => 'Include vendor packages in profile (e.g., "bear/*,ray/di" or "*/*" for all)',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
            'xstep' => new McpTool(
                'xstep',
                'Step debugging with breakpoints. Returns slim JSON with a $schema URL; read that schema for field semantics and variable-state reconstruction (the shape is deduplicated, not self-evident). Key fields: {breakpoint, breaks: [{step, stack, variables, diff}]}. Breakpoint: file.php:line or file.php:line:condition. Vendor excluded by default.',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to debug (e.g., "vendor/bin/phpunit --filter testMethod")',
                        ],
                        'breakpoints' => [
                            'type' => 'string',
                            'description' => 'Breakpoints: "file.php:line" or "file.php:line:$var==null" (conditional). Multiple: "a.php:10,b.php:20"',
                            'default' => '',
                        ],
                        'steps' => [
                            'type' => 'string',
                            'description' => 'Max steps to record after breakpoint (default: 100)',
                            'default' => '100',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for debugging session',
                            'default' => '',
                        ],
                        'include_vendor' => [
                            'type' => 'string',
                            'description' => 'Include vendor packages in trace (e.g., "bear/*,ray/di" or "*/*" for all)',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
            'xcoverage' => new McpTool(
                'xcoverage',
                'Analyze test coverage. Returns JSON with $schema URL for semantic details. Key fields: {summary: {coverage_percent}, uncovered: {file: [lines]}}. Shows only uncovered lines. Vendor excluded by default.',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to analyze (e.g., "vendor/bin/phpunit" or "vendor/bin/phpunit --filter testMethod")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for coverage analysis',
                            'default' => '',
                        ],
                        'include_vendor' => [
                            'type' => 'string',
                            'description' => 'Include vendor packages in coverage (e.g., "bear/*,ray/di" or "*/*" for all)',
                            'default' => '',
                        ],
                        'cwd' => [
                            'type' => 'string',
                            'description' => 'Run from this directory (target project root)',
                            'default' => '',
                        ],
                        'php' => [
                            'type' => 'string',
                            'description' => 'Path to the PHP binary to use instead of the default',
                            'default' => '',
                        ],
                        'source' => [
                            'type' => 'string',
                            'description' => 'Restrict raw-mode coverage to these source paths (comma-separated). Mutually exclusive with include_vendor.',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
            'xback' => new McpTool(
                'xback',
                'Capture call stack (backtrace) at specific line. Returns JSON with $schema URL for semantic details. Key fields: {backtrace: [{file, line, function, args}]}.',
                [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'PHP script to get backtrace from (e.g., "vendor/bin/phpunit --filter testMethod")',
                        ],
                        'breakpoint' => [
                            'type' => 'string',
                            'description' => 'Line to capture backtrace (e.g., "file.php:50")',
                            'default' => '',
                        ],
                        'depth' => [
                            'type' => 'integer',
                            'description' => 'Max stack depth (default: 10)',
                            'default' => 10,
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for backtrace analysis',
                            'default' => '',
                        ],
                        'cwd' => [
                            'type' => 'string',
                            'description' => 'Run from this directory (target project root)',
                            'default' => '',
                        ],
                        'php' => [
                            'type' => 'string',
                            'description' => 'Path to the PHP binary to use instead of the default',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ),
            'xcompare' => new McpTool(
                'xcompare',
                'Compare variable states at the same breakpoint across two PHP executions. Returns JSON with $schema URL for semantic details. Key fields: {breakpoint, run_a, run_b, diff, analysis_hints}.',
                [
                    'type' => 'object',
                    'properties' => [
                        'script_a' => [
                            'type' => 'string',
                            'description' => 'First PHP script to run (e.g., "php calc.php 10").',
                        ],
                        'script_b' => [
                            'type' => 'string',
                            'description' => 'Second PHP script to run (e.g., "php calc.php 0").',
                        ],
                        'breakpoint' => [
                            'type' => 'string',
                            'description' => 'Breakpoint location shared by both runs (e.g., "src/Calculator.php:25")',
                        ],
                        'context' => [
                            'type' => 'string',
                            'description' => 'Context description for comparison analysis',
                            'default' => '',
                        ],
                        'steps' => [
                            'type' => 'string',
                            'description' => 'Max steps to record after breakpoint (default: 1)',
                            'default' => '1',
                        ],
                        'include_vendor' => [
                            'type' => 'string',
                            'description' => 'Include vendor packages in trace (e.g., "bear/*,ray/di" or "*/*" for all)',
                            'default' => '',
                        ],
                    ],
                    'required' => ['script_a', 'script_b', 'breakpoint'],
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
            while (($line = fgets(STDIN)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                $response = $this->handleLine($line);

                if (! $response instanceof JsonRpcResponse) {
                    continue;
                }

                $this->debugLog('Sending response', ['id' => $response->id]);
                echo $this->encodeResponse($response) . "\n";
                fflush(STDOUT);
            }
        } catch (Throwable $e) {
            error_log('MCP Server Fatal Error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Encode a response for the wire without letting an encoding failure
     * terminate the STDIN loop.
     *
     * Tool results embed the raw output of arbitrary scripts (exec()), which
     * may contain non-UTF-8 bytes; those are substituted rather than thrown on,
     * so the response is still delivered. On any other, unexpected encoding
     * error we emit a protocol-valid error for the same id instead of dropping
     * the response — a dropped response would leave the client waiting forever.
     */
    private function encodeResponse(JsonRpcResponse $response): string
    {
        try {
            // JSON_INVALID_UTF8_SUBSTITUTE keeps non-UTF-8 tool output from
            // throwing; the flag set otherwise matches the previous wire output.
            return json_encode($response, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException $e) {
            error_log('MCP Server Error: failed to encode response: ' . $e->getMessage());

            return json_encode(
                JsonRpcResponse::error($response->id, -32603, 'Failed to encode response'),
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            );
        }
    }

    /**
     * Parse and dispatch a single JSON-RPC request line.
     *
     * Stdio JSON-RPC framing is one message per line, so each line is decoded
     * independently: a parse failure on one line can never affect any other
     * line (there is no cross-line buffer to wedge). Returns null for
     * notifications that require no response.
     */
    private function handleLine(string $line): JsonRpcResponse|null
    {
        $trimmed = trim($line);

        $this->debugLog('Raw Claude CLI input', ['input' => $trimmed]);

        try {
            $request = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return JsonRpcResponse::error(null, -32700, 'Parse error');
        }

        if (! is_array($request)) {
            return JsonRpcResponse::error(null, -32600, 'Invalid Request: expected object');
        }

        /** @var array{method?: string, params?: string|int|bool|array<string, string|int|bool|array<string, string|int|bool>>|null, id?: string|int|null} $request */
        $requestMethod = $request['method'] ?? 'unknown';
        $requestId = $request['id'] ?? null;

        $this->debugLog('Processing request', ['method' => $requestMethod]);

        try {
            return $this->handleRequest($request);
        } catch (Throwable $e) {
            $this->debugLog('MCP Server Error for method ' . $requestMethod, ['message' => $e->getMessage()]);
            error_log('MCP Server Error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());

            return JsonRpcResponse::error($requestId, -32603, 'Internal error: ' . $e->getMessage());
        }
    }

    /** @param array{method?: string, params?: string|int|bool|array<string, string|int|bool|array<string, string|int|bool>>|null, id?: string|int|null} $request */
    private function handleRequest(array $request): JsonRpcResponse|null
    {
        $method = $request['method'] ?? '';
        $id = $request['id'] ?? null;

        // JSON-RPC 2.0 §4.1: a notification (no id member) must never be
        // answered — not even with an error
        if (! array_key_exists('id', $request)) {
            return null;
        }

        $params = $request['params'] ?? [];

        // params is decoded JSON — it may be a scalar even though handlers
        // expect an object; reject before it reaches typed signatures
        if (! is_array($params)) {
            return JsonRpcResponse::error($id, -32602, 'Invalid params: expected object');
        }

        // MCP 2026-07-28 (stateless): requests carrying the
        // io.modelcontextprotocol/protocolVersion _meta key must declare a
        // supported protocol version (SEP-2575). Requests without it are
        // legacy (initialize-era) and pass through.
        $metaError = $this->validateProtocolMeta($id, $params);
        if ($metaError instanceof JsonRpcResponse) {
            return $metaError;
        }

        try {
            return match ($method) {
                'initialize' => $this->handleInitialize($id, $params),
                'server/discover' => $this->handleServerDiscover($id),
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
     * Validate MCP 2026-07-28 per-request protocol fields.
     *
     * The stateless marker is the io.modelcontextprotocol/protocolVersion
     * _meta key: when present, clientCapabilities is also required (-32602 if
     * missing) and the version must be one this server implements (-32022
     * UnsupportedProtocolVersion otherwise). Other keys under the reserved
     * io.modelcontextprotocol/ prefix (logLevel, subscriptionId, ...) may
     * legitimately appear on their own, so they are not treated as markers.
     * Returns null when the request is valid or legacy (no protocolVersion).
     *
     * @param array<string, string|int|bool|array<string, string|int|bool>> $params
     */
    private function validateProtocolMeta(string|int|null $id, array $params): JsonRpcResponse|null
    {
        $meta = $params['_meta'] ?? null;
        if (! is_array($meta)) {
            return null;
        }

        if (! array_key_exists('io.modelcontextprotocol/protocolVersion', $meta)) {
            return null;
        }

        $version = $meta['io.modelcontextprotocol/protocolVersion'];
        if (! is_string($version)) {
            return JsonRpcResponse::error($id, -32602, 'Invalid params: io.modelcontextprotocol/protocolVersion must be a string');
        }

        if (! isset($meta['io.modelcontextprotocol/clientCapabilities'])) {
            return JsonRpcResponse::error($id, -32602, 'Invalid params: missing required _meta field (io.modelcontextprotocol/clientCapabilities)');
        }

        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            // UnsupportedProtocolVersionError: data shape per 2026-07-28 schema
            return JsonRpcResponse::error($id, -32022, "Unsupported protocol version: {$version}", ['supported' => self::SUPPORTED_VERSIONS, 'requested' => $version]);
        }

        return null;
    }

    /** @param array<string, string|int|bool|array<string, string|int|bool>> $params */
    private function handleInitialize(string|int|null $id, array $params): JsonRpcResponse
    {
        // Legacy handshake: negotiate within the legacy (initialize-era)
        // revisions only — the stateless 2026-07-28 has no handshake and
        // must not be named in an initialize result
        $clientVersion = $params['protocolVersion'] ?? self::LATEST_LEGACY_VERSION;
        if (! is_string($clientVersion) || ! in_array($clientVersion, self::LEGACY_VERSIONS, true)) {
            $clientVersion = self::LATEST_LEGACY_VERSION;
        }

        return JsonRpcResponse::success($id, new GenericResult([
            'protocolVersion' => $clientVersion,
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['listChanged' => false],
                'prompts' => ['listChanged' => true],
            ],
            'serverInfo' => [
                'name' => Constants::MCP_SERVER_NAME,
                'version' => Constants::MCP_SERVER_VERSION,
            ],
            'instructions' => self::INSTRUCTIONS,
        ]));
    }

    /**
     * MCP 2026-07-28 server/discover (SEP-2575): advertise supported protocol
     * versions, capabilities, and identity. Also used by dual-era clients as
     * the stdio backward-compatibility probe.
     */
    private function handleServerDiscover(string|int|null $id): JsonRpcResponse
    {
        return JsonRpcResponse::success($id, new GenericResult([
            'supportedVersions' => self::SUPPORTED_VERSIONS,
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['listChanged' => false],
                'prompts' => ['listChanged' => true],
            ],
            'instructions' => self::INSTRUCTIONS,
            'ttlMs' => Constants::MCP_LIST_CACHE_TTL_MS,
            'cacheScope' => Constants::MCP_LIST_CACHE_SCOPE,
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
            'ttlMs' => Constants::MCP_LIST_CACHE_TTL_MS,
            'cacheScope' => Constants::MCP_LIST_CACHE_SCOPE,
        ]));
    }

    private function handlePromptsList(string|int|null $id): JsonRpcResponse
    {
        return JsonRpcResponse::success($id, new GenericResult([
            'prompts' => [
                [
                    'name' => 'xtrace',
                    'description' => 'Trace PHP execution flow. Returns JSON with $schema URL for semantic details and AI analysis strategies. Key fields: {lines, functions, max_depth, db_queries}. Vendor excluded by default.',
                    'arguments' => [
                        [
                            'name' => 'script',
                            'description' => 'PHP script to trace (e.g., "vendor/bin/phpunit --filter testMethod")',
                            'required' => true,
                        ],
                        [
                            'name' => 'context',
                            'description' => 'Context description for AI analysis (e.g., "Debug login failure")',
                            'required' => false,
                        ],
                        [
                            'name' => 'include_vendor',
                            'description' => 'Include vendor packages in trace (e.g., "bear/*,ray/di" or "*/*" for all)',
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
                    'description' => 'Step debugging with breakpoints. Returns slim JSON with a $schema URL; read that schema for field semantics and variable-state reconstruction (the shape is deduplicated, not self-evident). Key fields: {breakpoint, breaks: [{step, stack, variables, diff}]}. Breakpoint: file.php:line or file.php:line:condition. Vendor excluded by default.',
                    'arguments' => [
                        [
                            'name' => 'script',
                            'description' => 'PHP script to debug (e.g., "vendor/bin/phpunit --filter testMethod")',
                            'required' => true,
                        ],
                        [
                            'name' => 'breakpoints',
                            'description' => 'Breakpoints: "file.php:line" or "file.php:line:$var==null" (conditional). Multiple: "a.php:10,b.php:20"',
                            'required' => false,
                        ],
                        [
                            'name' => 'steps',
                            'description' => 'Max steps to record after breakpoint (default: 100)',
                            'required' => false,
                        ],
                        [
                            'name' => 'context',
                            'description' => 'Context description for debugging session',
                            'required' => false,
                        ],
                        [
                            'name' => 'include_vendor',
                            'description' => 'Include vendor packages in trace (e.g., "bear/*,ray/di" or "*/*" for all)',
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
                    'description' => 'Profile performance bottlenecks. Returns JSON with a schema URL; time_ms/memory_mb are measured from the cachegrind summary and bottlenecks is a structured array — read the schema for field shapes. Key fields: {time_ms, memory_mb, bottlenecks}. Vendor excluded by default.',
                    'arguments' => [
                        [
                            'name' => 'script',
                            'description' => 'PHP script to profile (e.g., "vendor/bin/phpunit --filter testMethod")',
                            'required' => true,
                        ],
                        [
                            'name' => 'context',
                            'description' => 'Context description for performance analysis',
                            'required' => false,
                        ],
                        [
                            'name' => 'include_vendor',
                            'description' => 'Include vendor packages in profile (e.g., "bear/*,ray/di" or "*/*" for all)',
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
                    'description' => 'Analyze test coverage. Returns JSON with $schema URL for semantic details. Key fields: {summary: {coverage_percent}, uncovered: {file: [lines]}}. Shows only uncovered lines. Vendor excluded by default.',
                    'arguments' => [
                        [
                            'name' => 'script',
                            'description' => 'PHP script to analyze (e.g., "vendor/bin/phpunit" or "vendor/bin/phpunit --filter testMethod")',
                            'required' => true,
                        ],
                        [
                            'name' => 'context',
                            'description' => 'Context description for coverage analysis',
                            'required' => false,
                        ],
                        [
                            'name' => 'include_vendor',
                            'description' => 'Include vendor packages in coverage (e.g., "bear/*,ray/di" or "*/*" for all)',
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
                    'description' => 'Capture call stack (backtrace) at specific line. Returns JSON with $schema URL for semantic details. Key fields: {backtrace: [{file, line, function, args}]}.',
                    'arguments' => [
                        [
                            'name' => 'script',
                            'description' => 'PHP script to get backtrace from (e.g., "vendor/bin/phpunit --filter testMethod")',
                            'required' => true,
                        ],
                        [
                            'name' => 'breakpoint',
                            'description' => 'Line to capture backtrace (e.g., "file.php:50")',
                            'required' => false,
                        ],
                        [
                            'name' => 'depth',
                            'description' => 'Max stack depth (default: 10)',
                            'required' => false,
                        ],
                        [
                            'name' => 'context',
                            'description' => 'Context description for backtrace analysis',
                            'required' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'xcompare',
                    'description' => 'Compare variable states at the same breakpoint across two PHP executions. Returns JSON with $schema URL for semantic details. Key fields: {breakpoint, run_a, run_b, diff, analysis_hints}.',
                    'arguments' => [
                        [
                            'name' => 'script_a',
                            'description' => 'First PHP script to run (e.g., "php calc.php 10").',
                            'required' => true,
                        ],
                        [
                            'name' => 'script_b',
                            'description' => 'Second PHP script to run (e.g., "php calc.php 0").',
                            'required' => true,
                        ],
                        [
                            'name' => 'breakpoint',
                            'description' => 'Breakpoint location shared by both runs (e.g., "src/Calculator.php:25")',
                            'required' => true,
                        ],
                        [
                            'name' => 'context',
                            'description' => 'Context description for comparison analysis',
                            'required' => false,
                        ],
                        [
                            'name' => 'steps',
                            'description' => 'Max steps to record after breakpoint (default: 1)',
                            'required' => false,
                        ],
                        [
                            'name' => 'include_vendor',
                            'description' => 'Include vendor packages in trace (e.g., "bear/*,ray/di" or "*/*" for all)',
                            'required' => false,
                        ],
                    ],
                ],
            ],
            'ttlMs' => Constants::MCP_LIST_CACHE_TTL_MS,
            'cacheScope' => Constants::MCP_LIST_CACHE_SCOPE,
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
            'xcompare' => $this->executeXCompare($id, $args),
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
            'xtrace', 'xprofile' => ['script', 'context', 'include_vendor'],
            'xstep' => ['script', 'breakpoints', 'steps', 'context', 'include_vendor'],
            'xcoverage' => ['script', 'context', 'include_vendor', 'cwd', 'php', 'source'],
            'xback' => ['script', 'breakpoint', 'depth', 'context', 'cwd', 'php'],
            'xcompare' => ['script_a', 'script_b', 'breakpoint', 'context', 'steps', 'include_vendor'],
            default => [],
        };

        foreach ($positionalArgs as $index => $value) {
            if (! isset($mapping[$index])) {
                continue;
            }

            $args[$mapping[$index]] = $value;
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

        // If the command already starts with a PHP binary (php, php8.2, /usr/bin/php, C:\php\php.exe) keep as-is
        if (preg_match('/^(\S*[\/\\\\])?php([0-9.]*)?(\.exe)?(\s|$)/i', $script)) {
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

        // Check that script starts with PHP binary (handles php, php8.2, /usr/bin/php, C:\php\php.exe, etc.)
        if (! preg_match('/^(\S*[\/\\\\])?php([0-9.]*)?(\.exe)?(\\s+|$)/i', $script)) {
            throw new InvalidArgumentException('Script must start with PHP binary. Examples: "php script.php", "php8.2 script.php", "/usr/bin/php script.php", "C:\php\php.exe script.php". Received: "' . $script . '"');
        }
    }

    private function isPhpInlineCodeScript(string $script): bool
    {
        return PhpCommandParser::isPhpInlineCodeScript($script);
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
                $cwd = getcwd();
                if ($cwd === false) {
                    throw new InvalidArgumentException(
                        'Cannot determine current working directory for relative breakpoint path: "' . $file . '"',
                    );
                }

                $absolutePath = $cwd . '/' . $file;
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

            case 'xcompare':
                $result = $this->executeXCompare(null, $arguments);

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
        // Surface execution errors (e.g. argument validation) instead of a bare "No result"
        if ($response->error instanceof JsonRpcError) {
            return 'Error: ' . $response->error->message;
        }

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
            $includeVendor = $args['include_vendor'] ?? '';

            // Build command - user must specify PHP binary explicitly
            $cmd = $this->binDir . '/xtrace --json';

            // Add include_vendor option if specified
            if ($includeVendor !== '') {
                $cmd .= ' --include-vendor=' . escapeshellarg($includeVendor);
            }

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
            $includeVendor = $args['include_vendor'] ?? '';

            // Build command - user must specify PHP binary explicitly
            $cmd = $this->binDir . '/xprofile --json';

            // Add include_vendor option if specified
            if ($includeVendor !== '') {
                $cmd .= ' --include-vendor=' . escapeshellarg($includeVendor);
            }

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
            $includeVendor = $args['include_vendor'] ?? '';
            $cwd = $args['cwd'] ?? '';
            $phpBinary = $args['php'] ?? '';
            $source = $args['source'] ?? '';

            if ($includeVendor !== '' && $source !== '') {
                throw new InvalidArgumentException('Parameters "include_vendor" and "source" are mutually exclusive');
            }

            // Build command - user must specify PHP binary explicitly
            $cmd = $this->binDir . '/xcoverage';

            if ($this->isPhpInlineCodeScript($script)) {
                $cmd .= ' --raw';
            }

            // Add include_vendor option if specified
            if ($includeVendor !== '') {
                $cmd .= ' --include-vendor=' . escapeshellarg($includeVendor);
            }

            if ($cwd !== '') {
                $cmd .= ' --cwd=' . escapeshellarg($cwd);
            }

            if ($phpBinary !== '') {
                $cmd .= ' --php=' . escapeshellarg($phpBinary);
            }

            if ($source !== '') {
                $cmd .= ' --source=' . escapeshellarg($source);
            }

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
                            'text' => 'Code coverage analysis ' . ($returnCode === 0 ? 'completed' : 'failed') . ":\n\n**Script**: {$script}\n**Context**: {$context}\n**Format**: json\n**Command**: `{$cmd}`\n**Exit Code**: {$returnCode}\n\n**Coverage Report**:\n```\n" . $outputText . "\n```",
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
            $cwd = $args['cwd'] ?? '';
            $phpBinary = $args['php'] ?? '';

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

            if ($cwd !== '') {
                $cmd .= ' --cwd=' . escapeshellarg($cwd);
            }

            if ($phpBinary !== '') {
                $cmd .= ' --php=' . escapeshellarg($phpBinary);
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

    /** @param array<string, string> $args */
    private function executeXCompare(string|int|null $id, array $args): JsonRpcResponse
    {
        try {
            $breakpoint = $args['breakpoint'] ?? '';
            if ($breakpoint === '') {
                throw new InvalidArgumentException('Breakpoint argument is required');
            }

            if (str_contains($breakpoint, ',')) {
                throw new InvalidArgumentException('xcompare accepts a single breakpoint location shared by both runs (e.g., "src/Calculator.php:25")');
            }

            $this->validateBreakpoints($breakpoint);

            $runA = $args['script_a'] ?? '';
            $runB = $args['script_b'] ?? '';

            if ($runA === '' || $runB === '') {
                throw new InvalidArgumentException('Both script_a and script_b arguments are required');
            }

            $runA = $this->processScriptArgument($runA);
            $runB = $this->processScriptArgument($runB);

            $this->validatePhpBinaryScript($runA);
            $this->validatePhpBinaryScript($runB);

            $context = $args['context'] ?? '';
            $steps = $args['steps'] ?? '1';
            $includeVendor = $args['include_vendor'] ?? '';

            $options = [
                'break' => $breakpoint,
                'run_a' => $runA,
                'run_b' => $runB,
            ];

            if ($context !== '') {
                $options['context'] = $context;
            }

            if ($steps !== '') {
                $options['steps'] = (int) $steps;
            }

            if ($includeVendor !== '') {
                $options['include_vendor'] = $includeVendor;
            }

            $runner = new CompareRunner($options);
            $result = $runner->run();

            $outputText = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return JsonRpcResponse::success($id, new GenericResult([
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => [
                            'type' => 'text',
                            'text' => 'Breakpoint comparison completed:' . "\n\n" .
                                '**Breakpoint**: ' . $breakpoint . "\n" .
                                '**Run A**: ' . $runA . "\n" .
                                '**Run B**: ' . $runB . "\n" .
                                '**Context**: ' . $context . "\n\n" .
                                '**Result**:' . "\n```json\n" . $outputText . "\n```",
                        ],
                    ],
                ],
                'debug_data' => [
                    'breakpoint' => $breakpoint,
                    'run_a' => $runA,
                    'run_b' => $runB,
                    'context' => $context,
                    'steps' => $steps,
                    'include_vendor' => $includeVendor,
                    'output' => $outputText,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]));
        } catch (Throwable $e) {
            // @codeCoverageIgnoreStart - Exception handling path requires CompareRunner/xstep failures which are environment-dependent
            return JsonRpcResponse::error($id, -32000, 'xcompare execution failed: ' . $e->getMessage());
            // @codeCoverageIgnoreEnd
        }
    }
}
