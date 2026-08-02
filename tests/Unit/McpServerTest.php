<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\DTO\GenericResult;
use Koriym\XdebugMcp\DTO\JsonRpcResponse;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use Koriym\XdebugMcp\McpServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

use function array_column;
use function json_decode;
use function putenv;

use const JSON_THROW_ON_ERROR;

class McpServerTest extends TestCase
{
    private McpServer $server;

    protected function setUp(): void
    {
        $this->server = new McpServer();
    }

    public function testInitializeRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('jsonrpc', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('protocolVersion', $response['result']);
        $this->assertArrayHasKey('serverInfo', $response['result']);
        $this->assertEquals('xdebug-mcp-server', $response['result']['serverInfo']['name']);
    }

    public function testToolsListRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertCount(6, $response['result']['tools']);

        $toolNames = array_column($response['result']['tools'], 'name');
        // Test that execution tools are present
        $this->assertContains('xtrace', $toolNames);
        $this->assertContains('xprofile', $toolNames);
        $this->assertContains('xstep', $toolNames);
        $this->assertContains('xcoverage', $toolNames);
        $this->assertContains('xback', $toolNames);
        $this->assertContains('xcompare', $toolNames);

        // Test that interactive debugging tools are removed
        $this->assertNotContains('xdebug_connect', $toolNames);
        $this->assertNotContains('xdebug_disconnect', $toolNames);
        $this->assertNotContains('xdebug_set_breakpoint', $toolNames);
    }

    public function testUnknownMethodRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'unknown/method',
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertEquals('Method not found: unknown/method', $response['error']['message']);
    }

    public function testHandleLineMalformedJsonReturnsParseError(): void
    {
        $responseObj = $this->invokePrivateMethod($this->server, 'handleLine', ['{oops}']);
        $response = $responseObj->toArray();

        $this->assertNull($response['id']);
        $this->assertEquals(-32700, $response['error']['code']);
        $this->assertEquals('Parse error', $response['error']['message']);
    }

    public function testHandleLineValidRequestAfterMalformedLineIsNotWedged(): void
    {
        // Regression test for F2: a malformed line must not contaminate the
        // next line. Each line is parsed independently by handleLine().
        $firstResponse = $this->invokePrivateMethod($this->server, 'handleLine', ['{oops}']);
        $this->assertEquals(-32700, $firstResponse->toArray()['error']['code']);

        $secondResponse = $this->invokePrivateMethod(
            $this->server,
            'handleLine',
            ['{"jsonrpc":"2.0","id":99,"method":"tools/list"}'],
        );
        $second = $secondResponse->toArray();

        $this->assertEquals(99, $second['id']);
        $this->assertArrayHasKey('result', $second);
        $this->assertArrayHasKey('tools', $second['result']);
    }

    public function testEncodeResponseWithNonUtf8ToolOutputDoesNotThrow(): void
    {
        // Regression: tool results embed raw exec() output that may contain
        // non-UTF-8 bytes. encodeResponse() must not throw (which would
        // propagate to __invoke()'s outer catch and wedge the STDIN loop) — the
        // bytes are substituted and a valid JSON response is still produced.
        $response = JsonRpcResponse::success(7, new GenericResult([
            'content' => [['type' => 'text', 'text' => "trace output \xff\xfe not utf-8"]],
        ]));

        $encoded = $this->invokePrivateMethod($this->server, 'encodeResponse', [$response]);

        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(7, $decoded['id']);
        $this->assertArrayHasKey('result', $decoded);
    }

    public function testHandleLineNonObjectJsonReturnsInvalidRequest(): void
    {
        $responseObj = $this->invokePrivateMethod($this->server, 'handleLine', ['42']);
        $response = $responseObj->toArray();

        $this->assertEquals(-32600, $response['error']['code']);
    }

    public function testHandleLineNotificationsInitializedReturnsNull(): void
    {
        $result = $this->invokePrivateMethod(
            $this->server,
            'handleLine',
            ['{"jsonrpc":"2.0","method":"notifications/initialized"}'],
        );

        $this->assertNull($result);
    }

    public function testToolCallWithoutConnection(): void
    {
        // Test that removed interactive tools return proper error
        $request = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_disconnect',
                'arguments' => [],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32000, $response['error']['code']);
        $this->assertStringContainsString('Unknown tool: xdebug_disconnect', $response['error']['message']);
    }

    public function testUnknownToolCall(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'unknown_tool',
                'arguments' => [],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32000, $response['error']['code']);
        $this->assertStringContainsString('Unknown tool: unknown_tool', $response['error']['message']);
    }

    public function testDebugModeLogging(): void
    {
        // Test debug mode enabled
        putenv('MCP_DEBUG=1');
        $debugServer = new McpServer();

        // Debug logging should be enabled
        $reflection = new ReflectionClass($debugServer);
        $debugMode = $reflection->getProperty('debugMode');
        $this->assertTrue($debugMode->getValue($debugServer));

        // Test debug mode disabled
        putenv('MCP_DEBUG=0');
        $normalServer = new McpServer();
        $this->assertFalse($debugMode->getValue($normalServer));

        // Restore environment
        putenv('MCP_DEBUG=');
    }

    public function testResourcesListRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'resources/list',
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('resources', $response['result']);
        $this->assertEmpty($response['result']['resources']);
    }

    public function testPromptsListRequest(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'prompts/list',
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('prompts', $response['result']);
        $this->assertCount(6, $response['result']['prompts']);

        $promptNames = array_column($response['result']['prompts'], 'name');
        $this->assertContains('xtrace', $promptNames);
        $this->assertContains('xstep', $promptNames);
        $this->assertContains('xprofile', $promptNames);
        $this->assertContains('xcoverage', $promptNames);
        $this->assertContains('xback', $promptNames);
        $this->assertContains('xcompare', $promptNames);
    }

    public function testNotificationsInitialized(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ];

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        // Should return null for notifications
        $this->assertNull($response);
    }

    public function testPromptsGetUnknown(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'unknown-prompt',
                'arguments' => [],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertStringContainsString('Unknown prompt: unknown-prompt', $response['error']['message']);
    }

    public function testProcessScriptArgument(): void
    {
        // Test quote processing - processScriptArgument removes quotes and adds php prefix when needed
        $this->assertEquals('php test.php', $this->invokePrivateMethod($this->server, 'processScriptArgument', ['"php test.php"']));
        $this->assertEquals('test.php', $this->invokePrivateMethod($this->server, 'processScriptArgument', ['"test.php'])); // "test.php" -> "test.php" (matches *php pattern)
        $this->assertEquals('test.php', $this->invokePrivateMethod($this->server, 'processScriptArgument', ['test.php"'])); // "test.php" -> "test.php" (matches *php pattern)
        $this->assertEquals('test.php', $this->invokePrivateMethod($this->server, 'processScriptArgument', ['test.php'])); // "test.php" matches *php pattern - no prefix
        $this->assertEquals('php already.php', $this->invokePrivateMethod($this->server, 'processScriptArgument', ['php already.php'])); // already has php prefix
        $this->assertEquals('php script.py', $this->invokePrivateMethod($this->server, 'processScriptArgument', ['script.py'])); // doesn't end with php - gets prefix
    }

    public function testMapPositionalArgs(): void
    {
        // Test xtrace mapping - positional args are mapped to named args
        $namedArgs = [];
        $positionalArgs = ['script.php', 'test context'];
        $mapped = $this->invokePrivateMethod($this->server, 'mapPositionalArgs', [$namedArgs, $positionalArgs, 'xtrace']);
        $this->assertEquals('script.php', $mapped['script']);
        $this->assertEquals('test context', $mapped['context']);

        // Test xstep mapping
        $namedArgs = [];
        $positionalArgs = ['script.php', 'file.php:10', '50', 'debug context'];
        $mapped = $this->invokePrivateMethod($this->server, 'mapPositionalArgs', [$namedArgs, $positionalArgs, 'xstep']);
        $this->assertEquals('script.php', $mapped['script']);
        $this->assertEquals('file.php:10', $mapped['breakpoints']);
        $this->assertEquals('50', $mapped['steps']);
        $this->assertEquals('debug context', $mapped['context']);
    }

    public function testInitializeWithUnsupportedVersion(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '1999-01-01'], // Unsupported version
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        // Should default to latest supported version
        $this->assertEquals('2026-07-28', $response['result']['protocolVersion']);
    }

    public function testServerDiscover(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 20,
            'method' => 'server/discover',
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('supportedVersions', $response['result']);
        $this->assertContains('2026-07-28', $response['result']['supportedVersions']);
        $this->assertContains('2024-11-05', $response['result']['supportedVersions']);
        $this->assertArrayHasKey('capabilities', $response['result']);
        $this->assertArrayHasKey('tools', $response['result']['capabilities']);
        $this->assertArrayHasKey('instructions', $response['result']);
        $this->assertSame('complete', $response['result']['resultType']);
        $this->assertSame(
            'xdebug-mcp-server',
            $response['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'],
        );
    }

    public function testStatelessRequestWithoutInitialize(): void
    {
        // MCP 2026-07-28: no handshake; every request carries its protocol
        // version and capabilities in _meta
        $request = [
            'jsonrpc' => '2.0',
            'id' => 21,
            'method' => 'tools/list',
            'params' => [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => [],
                    'io.modelcontextprotocol/clientInfo' => ['name' => 'modern-client', 'version' => '1.0.0'],
                ],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertSame('complete', $response['result']['resultType']);
        $this->assertSame(
            'xdebug-mcp-server',
            $response['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'],
        );
    }

    public function testUnsupportedProtocolVersionMetaReturnsError(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 22,
            'method' => 'tools/list',
            'params' => [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '1999-01-01',
                    'io.modelcontextprotocol/clientCapabilities' => [],
                ],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32022, $response['error']['code']);
        $this->assertStringContainsString('Unsupported protocol version', $response['error']['message']);
        $this->assertContains('2026-07-28', $response['error']['data']['supportedVersions']);
    }

    public function testModernMetaMissingRequiredFieldsReturnsInvalidParams(): void
    {
        // _meta has io.modelcontextprotocol/* keys but lacks clientCapabilities
        $request = [
            'jsonrpc' => '2.0',
            'id' => 23,
            'method' => 'tools/list',
            'params' => [
                '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28'],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32602, $response['error']['code']);
    }

    public function testLegacyRequestWithoutMetaIsNotRejected(): void
    {
        // Dual-era: requests without modern _meta (legacy clients) pass through
        $request = [
            'jsonrpc' => '2.0',
            'id' => 24,
            'method' => 'tools/list',
            'params' => ['_meta' => ['progressToken' => 'abc']],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
    }

    public function testListResultsContainCacheableFields(): void
    {
        foreach (['tools/list', 'prompts/list', 'resources/list'] as $index => $method) {
            $request = [
                'jsonrpc' => '2.0',
                'id' => 30 + $index,
                'method' => $method,
            ];

            $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
            $response = $responseObj->toArray();

            $this->assertArrayHasKey('ttlMs', $response['result'], $method);
            $this->assertArrayHasKey('cacheScope', $response['result'], $method);
            $this->assertSame('complete', $response['result']['resultType'], $method);
        }
    }

    public function testValidatePhpBinaryScript(): void
    {
        // Test valid PHP script - should not throw exception
        try {
            $this->invokePrivateMethod($this->server, 'validatePhpBinaryScript', ['php test.php']);
            $this->assertTrue(true); // If we get here, no exception was thrown
        } catch (Throwable $e) {
            $this->fail('Valid PHP script should not throw exception: ' . $e->getMessage());
        }
    }

    public function testValidatePhpBinaryScriptEmpty(): void
    {
        // Test empty script - should throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Script argument is required');
        $this->invokePrivateMethod($this->server, 'validatePhpBinaryScript', ['']);
    }

    public function testValidatePhpBinaryScriptInvalid(): void
    {
        // Test invalid script - should throw exception (use script that definitely doesn't match pattern)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Script must start with PHP binary');
        $this->invokePrivateMethod($this->server, 'validatePhpBinaryScript', ['python script.py']);
    }

    public function testIsPhpInlineCodeScriptDetectsRunForms(): void
    {
        $this->assertTrue($this->invokePrivateMethod($this->server, 'isPhpInlineCodeScript', ['php -r "echo 1;"']));
        $this->assertTrue($this->invokePrivateMethod($this->server, 'isPhpInlineCodeScript', ['php -recho 1;']));
        $this->assertTrue($this->invokePrivateMethod($this->server, 'isPhpInlineCodeScript', ['php --run=echo 1;']));
        $this->assertFalse($this->invokePrivateMethod($this->server, 'isPhpInlineCodeScript', ['php script.php']));
    }

    public function testIsPhpInlineCodeScriptIgnoresRunPastScriptBoundary(): void
    {
        // -r belongs to the script's own arguments, not the interpreter.
        $this->assertFalse($this->invokePrivateMethod($this->server, 'isPhpInlineCodeScript', ['php app.php -r dry-run']));
        $this->assertFalse($this->invokePrivateMethod($this->server, 'isPhpInlineCodeScript', ['php -- -r code']));
        // Interpreter options before -r must not hide it (value of -d is skipped).
        $this->assertTrue($this->invokePrivateMethod($this->server, 'isPhpInlineCodeScript', ['php -d memory_limit=512M -r "echo 1;"']));
        $this->assertTrue($this->invokePrivateMethod($this->server, 'isPhpInlineCodeScript', ['php --define memory_limit=512M -r "echo 1;"']));
    }

    public function testExecuteToolCall(): void
    {
        // executeToolCall surfaces execution errors (e.g. argument validation) as text
        $result = $this->invokePrivateMethod($this->server, 'executeToolCall', ['xtrace', ['script' => '']]);

        $this->assertIsString($result);
        $this->assertStringContainsString('Error:', $result);
        $this->assertStringContainsString('Script argument is required', $result);
    }

    public function testHandleToolCallError(): void
    {
        // Test handleToolCall with invalid tool name to get error response
        $request = [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => [
                'name' => 'invalid-tool', // Invalid tool name will trigger error
                'arguments' => [],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32000, $response['error']['code']);
        $this->assertStringContainsString('Unknown tool: invalid-tool', $response['error']['message']);
    }

    public function testExecuteXCompareRejectsMultipleBreakpoints(): void
    {
        // xcompare shares one breakpoint across both runs; comma-separated lists must be rejected
        $result = $this->invokePrivateMethod($this->server, 'executeXCompare', [
            null,
            [
                'script_a' => 'php tests/fake/loop-counter.php',
                'script_b' => 'php tests/fake/array-manipulation.php',
                'breakpoint' => 'tests/fake/loop-counter.php:10,tests/fake/array-manipulation.php:20',
            ],
        ]);
        $response = $result->toArray();

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('single breakpoint', $response['error']['message']);
    }

    public function testToolsCallXDebug(): void
    {
        // Test tools/call request with xstep to hit executeToolCall case
        $request = [
            'jsonrpc' => '2.0',
            'id' => 100,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xstep',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Tools call xstep test',
                ],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(100, $response['id']);
        $this->assertArrayHasKey('content', $response['result']);
        $this->assertStringContainsString('Forward Trace debugging completed', $response['result']['content'][0]['text']);
        $this->assertStringContainsString('tests/fake/loop-counter.php', $response['result']['content'][0]['text']);
    }

    public function testPromptsGetXTrace(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 200,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'xtrace',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Test xtrace prompt',
                ],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(200, $response['id']);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertArrayHasKey('debug_data', $response['result']);
    }

    public function testPromptsGetXDebug(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 201,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'xstep',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Test xstep prompt',
                    'breakpoints' => 'tests/fake/loop-counter.php:10',
                ],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(201, $response['id']);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertArrayHasKey('debug_data', $response['result']);

        // Verify the response contains Forward Trace debugging output
        $message = $response['result']['messages'][0]['content']['text'];
        $this->assertStringContainsString('Forward Trace debugging completed', $message);
        $this->assertStringContainsString('Context**: Test xstep prompt', $message);
        $this->assertStringContainsString('tests/fake/loop-counter.php', $message);

        // Verify debug_data structure
        $debugData = $response['result']['debug_data'];
        $this->assertArrayHasKey('command', $debugData);
        $this->assertArrayHasKey('exit_code', $debugData);
        $this->assertArrayHasKey('context', $debugData);
        $this->assertStringContainsString('xstep', $debugData['command']);
        $this->assertEquals('Test xstep prompt', $debugData['context']);
        $this->assertEquals(0, $debugData['exit_code']);
    }

    public function testPromptsGetXProfile(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 202,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'xprofile',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Test xprofile prompt',
                ],
            ],
        ];

        $responseObj = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(202, $response['id']);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertArrayHasKey('debug_data', $response['result']);
    }

    private function invokePrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }
}
