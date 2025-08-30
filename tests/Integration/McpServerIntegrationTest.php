<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use Koriym\XdebugMcp\McpServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_column;

class McpServerIntegrationTest extends TestCase
{
    private McpServer $server;

    protected function setUp(): void
    {
        $this->server = new McpServer();
    }

    public function testFullInitializeWorkflow(): void
    {
        // Test initialize request
        $initRequest = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0'],
            ],
        ];

        $initResponse = $this->invokeMethod($this->server, 'handleRequest', [$initRequest]);

        $this->assertEquals('2.0', $initResponse['jsonrpc']);
        $this->assertEquals(1, $initResponse['id']);
        $this->assertArrayHasKey('result', $initResponse);
        $this->assertEquals('2025-06-18', $initResponse['result']['protocolVersion']);
        $this->assertEquals('xdebug-mcp-server', $initResponse['result']['serverInfo']['name']);

        // Test tools list request
        $toolsRequest = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ];

        $toolsResponse = $this->invokeMethod($this->server, 'handleRequest', [$toolsRequest]);

        $this->assertEquals('2.0', $toolsResponse['jsonrpc']);
        $this->assertEquals(2, $toolsResponse['id']);
        $this->assertArrayHasKey('result', $toolsResponse);
        $this->assertArrayHasKey('tools', $toolsResponse['result']);
        $this->assertCount(4, $toolsResponse['result']['tools']);

        // Verify analysis tools are present
        $toolNames = array_column($toolsResponse['result']['tools'], 'name');
        $expectedTools = [
            'x-trace',
            'x-profile',
            'x-debug',
            'x-coverage',
        ];

        foreach ($expectedTools as $toolName) {
            $this->assertContains($toolName, $toolNames, "Tool {$toolName} should be available");
        }
    }

    public function testStandaloneProfilingWorkflow(): void
    {
        $this->markTestSkipped('Standalone profiling tools have been removed due to stateless nature of MCP');
    }

    public function testCoverageAnalysisWorkflow(): void
    {
        $this->markTestSkipped('Coverage analysis tools have been removed from MCP server - use x-coverage command instead');
    }

    public function testErrorHandling(): void
    {
        // Test invalid method
        $invalidRequest = [
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'invalid/method',
        ];

        $response = $this->invokeMethod($this->server, 'handleRequest', [$invalidRequest]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(11, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertEquals('Method not found: invalid/method', $response['error']['message']);

        // Test invalid tool call
        $invalidToolRequest = [
            'jsonrpc' => '2.0',
            'id' => 12,
            'method' => 'tools/call',
            'params' => [
                'name' => 'invalid_tool',
                'arguments' => [],
            ],
        ];

        $response = $this->invokeMethod($this->server, 'handleRequest', [$invalidToolRequest]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(12, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32000, $response['error']['code']);
        $this->assertStringContainsString('Unknown tool: invalid_tool', $response['error']['message']);
    }

    public function testCompleteJsonRpcValidation(): void
    {
        $testCases = [
            ['{"valid": "json"}', true],
            ['{"incomplete": ', false],
            ['', false],
            ['not json at all', false],
            ['{"nested": {"object": true}}', true],
            ['[{"array": true}]', true],
            ['{"string": "value", "number": 123, "boolean": true}', true],
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $this->invokeMethod($this->server, 'isCompleteJsonRpc', [$input]);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
