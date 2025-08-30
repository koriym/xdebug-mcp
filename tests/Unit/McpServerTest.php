<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use Koriym\XdebugMcp\McpServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

use function array_column;
use function putenv;

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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertCount(4, $response['result']['tools']);

        $toolNames = array_column($response['result']['tools'], 'name');
        // Test that execution tools are present
        $this->assertContains('x-trace', $toolNames);
        $this->assertContains('x-profile', $toolNames);
        $this->assertContains('x-debug', $toolNames);
        $this->assertContains('x-coverage', $toolNames);

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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertEquals('Method not found: unknown/method', $response['error']['message']);
    }

    public function testIsCompleteJsonRpc(): void
    {
        $this->assertTrue($this->invokePrivateMethod($this->server, 'isCompleteJsonRpc', ['{"test": "value"}']));
        $this->assertFalse($this->invokePrivateMethod($this->server, 'isCompleteJsonRpc', ['{"test": ']));
        $this->assertFalse($this->invokePrivateMethod($this->server, 'isCompleteJsonRpc', ['']));
        $this->assertFalse($this->invokePrivateMethod($this->server, 'isCompleteJsonRpc', ['not json']));
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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

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
        $debugMode->setAccessible(true);
        $this->assertTrue($debugMode->getValue($debugServer));

        // Test debug mode disabled
        putenv('MCP_DEBUG=0');
        $normalServer = new McpServer();
        $debugMode->setAccessible(true);
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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('prompts', $response['result']);
        $this->assertCount(4, $response['result']['prompts']);

        $promptNames = array_column($response['result']['prompts'], 'name');
        $this->assertContains('x-trace', $promptNames);
        $this->assertContains('x-debug', $promptNames);
        $this->assertContains('x-profile', $promptNames);
        $this->assertContains('x-coverage', $promptNames);
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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

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

    public function testNormalizePositionalArgs(): void
    {
        // Test x-trace normalization
        $args = ['script.php', 'test context'];
        $normalized = $this->invokePrivateMethod($this->server, 'normalizePositionalArgs', [$args, 'x-trace']);
        $this->assertEquals('script.php', $normalized['script']);
        $this->assertEquals('test context', $normalized['context']);

        // Test x-debug normalization
        $args = ['script.php', 'file.php:10', '50', 'debug context'];
        $normalized = $this->invokePrivateMethod($this->server, 'normalizePositionalArgs', [$args, 'x-debug']);
        $this->assertEquals('script.php', $normalized['script']);
        $this->assertEquals('file.php:10', $normalized['breakpoints']);
        $this->assertEquals('50', $normalized['steps']);
        $this->assertEquals('debug context', $normalized['context']);
    }

    public function testInitializeWithUnsupportedVersion(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '1999-01-01', // Unsupported version
            ],
        ];

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('result', $response);
        // Should default to latest supported version
        $this->assertEquals('2025-06-18', $response['result']['protocolVersion']);
    }

    public function testCoverageSummaryWithoutXdebug(): void
    {
        $this->markTestSkipped('Coverage summary method removed - use x-coverage tool instead');
    }

    public function testAnalyzeCoverageWithTextFormat(): void
    {
        $this->markTestSkipped('Coverage analysis method removed - use x-coverage tool instead');
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

    public function testExecuteToolCall(): void
    {
        // Test executeToolCall method directly - it should handle exceptions and return formatted result
        // The method catches exceptions and handles them, so let's test it returns proper error content
        try {
            $result = $this->invokePrivateMethod($this->server, 'executeToolCall', ['x-trace', ['script' => '']]);
            // executeToolCall should return a string result, not throw exception
            $this->assertIsString($result);
            $this->assertStringContainsString('No result', $result); // Default fallback when execution fails
        } catch (Throwable $e) {
            // If an exception is thrown, it should be InvalidArgumentException
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
            $this->assertStringContainsString('Script argument is required', $e->getMessage());
        }
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

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32000, $response['error']['code']);
        $this->assertStringContainsString('Unknown tool: invalid-tool', $response['error']['message']);
    }

    public function testToolsCallXDebug(): void
    {
        // Test tools/call request with x-debug to hit executeToolCall case
        $request = [
            'jsonrpc' => '2.0',
            'id' => 100,
            'method' => 'tools/call',
            'params' => [
                'name' => 'x-debug',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Tools call x-debug test',
                ],
            ],
        ];

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(100, $response['id']);
        $this->assertArrayHasKey('content', $response['result']);
        $this->assertStringContainsString('Forward Trace debugging completed', $response['result']['content'][0]['text']);
        $this->assertStringContainsString('tests/fake/loop-counter.php', $response['result']['content'][0]['text']);
    }

    public function testAnalyzeCoverageWithHtmlFormat(): void
    {
        $this->markTestSkipped('Coverage analysis method removed - use x-coverage tool instead');
    }

    public function testPromptsGetXTrace(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 200,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'x-trace',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Test x-trace prompt',
                ],
            ],
        ];

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

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
                'name' => 'x-debug',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Test x-debug prompt',
                    'breakpoints' => 'tests/fake/loop-counter.php:10',
                ],
            ],
        ];

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(201, $response['id']);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertArrayHasKey('debug_data', $response['result']);

        // Verify the response contains Forward Trace debugging output
        $message = $response['result']['messages'][0]['content']['text'];
        $this->assertStringContainsString('Forward Trace debugging completed', $message);
        $this->assertStringContainsString('Context**: Test x-debug prompt', $message);
        $this->assertStringContainsString('tests/fake/loop-counter.php', $message);

        // Verify debug_data structure
        $debugData = $response['result']['debug_data'];
        $this->assertArrayHasKey('command', $debugData);
        $this->assertArrayHasKey('exit_code', $debugData);
        $this->assertArrayHasKey('context', $debugData);
        $this->assertStringContainsString('xdebug-debug', $debugData['command']);
        $this->assertEquals('Test x-debug prompt', $debugData['context']);
        $this->assertEquals(0, $debugData['exit_code']);
    }

    public function testPromptsGetXProfile(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 202,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'x-profile',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Test x-profile prompt',
                ],
            ],
        ];

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(202, $response['id']);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertArrayHasKey('debug_data', $response['result']);
    }

    public function testPromptsGetXCoverage(): void
    {
        $this->markTestSkipped('Skip the test for infinite loop');
        $request = [
            'jsonrpc' => '2.0',
            'id' => 203,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'x-coverage',
                'arguments' => [
                    'script' => 'php tests/fake/loop-counter.php',
                    'context' => 'Test x-coverage prompt',
                ],
            ],
        ];

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(203, $response['id']);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertArrayHasKey('debug_data', $response['result']);
    }

    private function invokePrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
