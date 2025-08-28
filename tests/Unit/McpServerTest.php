<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\McpServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_column;
use function extension_loaded;

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
        $this->assertCount(28, $response['result']['tools']);

        $toolNames = array_column($response['result']['tools'], 'name');
        // Test that analysis tools are present
        $this->assertContains('xdebug_start_profiling', $toolNames);
        $this->assertContains('xdebug_start_coverage', $toolNames);
        $this->assertContains('xdebug_start_trace', $toolNames);

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

    public function testCoverageSummaryWithoutXdebug(): void
    {
        if (extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug is loaded, cannot test without Xdebug scenario');
        }

        $mockData = [
            '/path/file1.php' => [1 => 1, 2 => -1, 3 => 1],
            '/path/file2.php' => [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1],
        ];

        $result = $this->invokePrivateMethod($this->server, 'getCoverageSummary', [['coverage_data' => $mockData]]);

        $this->assertStringContainsString('Coverage Summary:', $result);
        $this->assertStringContainsString('"total_files": 2', $result);
        $this->assertStringContainsString('"total_lines": 9', $result);
        $this->assertStringContainsString('"covered_lines": 8', $result);
    }

    public function testAnalyzeCoverageWithTextFormat(): void
    {
        $mockData = [
            '/path/file1.php' => [1 => 1, 2 => -1, 3 => 1],
        ];

        $result = $this->invokePrivateMethod($this->server, 'analyzeCoverage', [
            [
                'coverage_data' => $mockData,
                'format' => 'text',
            ],
        ]);

        $this->assertStringContainsString('Code Coverage Report', $result);
        $this->assertStringContainsString('/path/file1.php', $result);
        $this->assertStringContainsString('Coverage: 66.67%', $result);
    }

    public function testAnalyzeCoverageWithHtmlFormat(): void
    {
        $mockData = [
            '/path/file1.php' => [1 => 1, 2 => -1, 3 => 1],
        ];

        $result = $this->invokePrivateMethod($this->server, 'analyzeCoverage', [
            [
                'coverage_data' => $mockData,
                'format' => 'html',
            ],
        ]);

        $this->assertStringContainsString('<html>', $result);
        $this->assertStringContainsString('<title>Code Coverage Report</title>', $result);
        $this->assertStringContainsString('/path/file1.php', $result);
    }

    private function invokePrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
