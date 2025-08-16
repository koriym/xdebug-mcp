<?php

namespace XdebugMcp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use XdebugMcp\McpServer;

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
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0']
            ]
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
            'method' => 'tools/list'
        ];

        $toolsResponse = $this->invokeMethod($this->server, 'handleRequest', [$toolsRequest]);

        $this->assertEquals('2.0', $toolsResponse['jsonrpc']);
        $this->assertEquals(2, $toolsResponse['id']);
        $this->assertArrayHasKey('result', $toolsResponse);
        $this->assertArrayHasKey('tools', $toolsResponse['result']);
        $this->assertCount(42, $toolsResponse['result']['tools']);

        // Verify essential tools are present
        $toolNames = array_column($toolsResponse['result']['tools'], 'name');
        $expectedTools = [
            'xdebug_connect',
            'xdebug_disconnect',
            'xdebug_set_breakpoint',
            'xdebug_remove_breakpoint',
            'xdebug_step_into',
            'xdebug_step_over',
            'xdebug_step_out',
            'xdebug_continue',
            'xdebug_get_stack',
            'xdebug_get_variables',
            'xdebug_eval',
            'xdebug_start_profiling',
            'xdebug_stop_profiling',
            'xdebug_get_profile_info',
            'xdebug_analyze_profile',
            'xdebug_start_coverage',
            'xdebug_stop_coverage',
            'xdebug_get_coverage',
            'xdebug_coverage_summary',
            'xdebug_analyze_coverage'
        ];

        foreach ($expectedTools as $toolName) {
            $this->assertContains($toolName, $toolNames, "Tool {$toolName} should be available");
        }
    }

    public function testStandaloneProfilingWorkflow(): void
    {
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension not loaded for standalone profiling test');
        }

        // Test starting profiling without connection
        $startRequest = [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_start_profiling',
                'arguments' => ['output_file' => '/tmp/test_profile.out']
            ]
        ];

        $startResponse = $this->invokeMethod($this->server, 'handleRequest', [$startRequest]);

        $this->assertEquals('2.0', $startResponse['jsonrpc']);
        $this->assertEquals(3, $startResponse['id']);
        $this->assertArrayHasKey('result', $startResponse);
        $this->assertStringContainsString('Standalone profiling started', $startResponse['result']['content'][0]['text']);

        // Test getting profile info
        $infoRequest = [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_profile_info',
                'arguments' => []
            ]
        ];

        $infoResponse = $this->invokeMethod($this->server, 'handleRequest', [$infoRequest]);

        $this->assertEquals('2.0', $infoResponse['jsonrpc']);
        $this->assertEquals(4, $infoResponse['id']);
        $this->assertArrayHasKey('result', $infoResponse);

        // Test stopping profiling
        $stopRequest = [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_stop_profiling',
                'arguments' => []
            ]
        ];

        $stopResponse = $this->invokeMethod($this->server, 'handleRequest', [$stopRequest]);

        $this->assertEquals('2.0', $stopResponse['jsonrpc']);
        $this->assertEquals(5, $stopResponse['id']);
        $this->assertArrayHasKey('result', $stopResponse);
        $this->assertStringContainsString('Standalone profiling stopped', $stopResponse['result']['content'][0]['text']);
    }

    public function testCoverageAnalysisWorkflow(): void
    {
        // Create mock coverage data
        $mockCoverageData = [
            '/path/to/file1.php' => [
                1 => 1,  // covered
                2 => -1, // not covered
                3 => 1,  // covered
                4 => -1, // not covered
            ],
            '/path/to/file2.php' => [
                1 => 1,  // covered
                2 => 1,  // covered
                3 => 1,  // covered
            ]
        ];

        // Test coverage summary
        $summaryRequest = [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_coverage_summary',
                'arguments' => ['coverage_data' => $mockCoverageData]
            ]
        ];

        $summaryResponse = $this->invokeMethod($this->server, 'handleRequest', [$summaryRequest]);

        $this->assertEquals('2.0', $summaryResponse['jsonrpc']);
        $this->assertEquals(6, $summaryResponse['id']);
        $this->assertArrayHasKey('result', $summaryResponse);
        
        $responseText = $summaryResponse['result']['content'][0]['text'];
        $this->assertStringContainsString('Coverage Summary:', $responseText);
        $this->assertStringContainsString('"total_files": 2', $responseText);
        $this->assertStringContainsString('"total_lines": 7', $responseText);
        $this->assertStringContainsString('"covered_lines": 5', $responseText);

        // Test coverage analysis in different formats
        $formats = ['text', 'html', 'xml', 'json'];
        
        foreach ($formats as $format) {
            $analysisRequest = [
                'jsonrpc' => '2.0',
                'id' => 7 + array_search($format, $formats),
                'method' => 'tools/call',
                'params' => [
                    'name' => 'xdebug_analyze_coverage',
                    'arguments' => [
                        'coverage_data' => $mockCoverageData,
                        'format' => $format
                    ]
                ]
            ];

            $analysisResponse = $this->invokeMethod($this->server, 'handleRequest', [$analysisRequest]);

            $this->assertEquals('2.0', $analysisResponse['jsonrpc']);
            $this->assertArrayHasKey('result', $analysisResponse);
            
            $responseText = $analysisResponse['result']['content'][0]['text'];
            
            switch ($format) {
                case 'text':
                    $this->assertStringContainsString('Code Coverage Report', $responseText);
                    break;
                case 'html':
                    $this->assertStringContainsString('<html>', $responseText);
                    $this->assertStringContainsString('<title>Code Coverage Report</title>', $responseText);
                    break;
                case 'xml':
                    $this->assertStringContainsString('<?xml version=', $responseText);
                    $this->assertStringContainsString('<coverage>', $responseText);
                    break;
                case 'json':
                    $this->assertJson($responseText);
                    break;
            }
        }
    }

    public function testErrorHandling(): void
    {
        // Test invalid method
        $invalidRequest = [
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'invalid/method'
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
                'arguments' => []
            ]
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
            ['{"string": "value", "number": 123, "boolean": true}', true]
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $this->invokeMethod($this->server, 'isCompleteJsonRpc', [$input]);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}