<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\McpServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_column;
use function extension_loaded;
use function json_decode;
use function time;

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
        $this->assertCount(43, $response['result']['tools']);

        $toolNames = array_column($response['result']['tools'], 'name');
        $this->assertContains('xdebug_connect', $toolNames);
        $this->assertContains('xdebug_disconnect', $toolNames);
        $this->assertContains('xdebug_set_breakpoint', $toolNames);
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

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('content', $response['result']);
        $content = json_decode($response['result']['content'][0]['text'], true);
        $this->assertEquals('error', $content['status']);
        $this->assertStringContainsString('No active session found', $content['message']);
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

    public function testSessionManagement(): void
    {
        $reflection = new ReflectionClass($this->server);
        $property = $reflection->getProperty('xdebugSessions');
        $property->setAccessible(true);

        // Verify sessions are empty initially
        $sessions = $property->getValue($this->server);
        $this->assertEmpty($sessions);

        // Manually add a mock session
        $mockSessionId = 'test_session_123';
        $mockSession = [
            'client' => null,
            'host' => '127.0.0.1',
            'port' => 9004,
            'connected' => true,
            'created_at' => time(),
            'last_activity' => time(),
            'session_info' => 'test session',
        ];

        $sessions = [$mockSessionId => $mockSession];
        $property->setValue($this->server, $sessions);

        // Verify session was added
        $updatedSessions = $property->getValue($this->server);
        $this->assertCount(1, $updatedSessions);
        $this->assertArrayHasKey($mockSessionId, $updatedSessions);
        $this->assertEquals('127.0.0.1', $updatedSessions[$mockSessionId]['host']);
        $this->assertEquals(9004, $updatedSessions[$mockSessionId]['port']);
        $this->assertTrue($updatedSessions[$mockSessionId]['connected']);
    }

    public function testListSessionsTool(): void
    {
        $reflection = new ReflectionClass($this->server);
        $property = $reflection->getProperty('xdebugSessions');
        $property->setAccessible(true);

        // Add test sessions
        $testSessions = [
            'session1' => [
                'client' => null,
                'host' => '127.0.0.1',
                'port' => 9004,
                'connected' => true,
                'created_at' => time() - 100,
                'last_activity' => time() - 50,
                'session_info' => 'test session 1',
            ],
            'session2' => [
                'client' => null,
                'host' => '192.168.1.100',
                'port' => 9005,
                'connected' => false,
                'created_at' => time() - 200,
                'last_activity' => time() - 150,
                'session_info' => 'test session 2',
            ],
        ];
        $property->setValue($this->server, $testSessions);

        $request = [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_list_sessions',
                'arguments' => [],
            ],
        ];

        $response = $this->invokePrivateMethod($this->server, 'handleRequest', [$request]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('content', $response['result']);

        $content = json_decode($response['result']['content'][0]['text'], true);
        $this->assertArrayHasKey('status', $content);
        $this->assertEquals('success', $content['status']);
        $this->assertEquals(2, $content['active_sessions']);
        $this->assertArrayHasKey('sessions', $content);
        $this->assertArrayHasKey('session1', $content['sessions']);
        $this->assertArrayHasKey('session2', $content['sessions']);
        $this->assertEquals('127.0.0.1', $content['sessions']['session1']['host']);
        $this->assertEquals(9004, $content['sessions']['session1']['port']);
        $this->assertTrue($content['sessions']['session1']['connected']);
        $this->assertFalse($content['sessions']['session2']['connected']);
    }

    public function testGetXdebugClientMethod(): void
    {
        $reflection = new ReflectionClass($this->server);
        $method = $reflection->getMethod('getXdebugClient');
        $method->setAccessible(true);

        $property = $reflection->getProperty('xdebugSessions');
        $property->setAccessible(true);

        // Use null since we can't easily mock XdebugClient in unit tests
        $testSessions = [
            'test_session' => [
                'client' => null, // Simple null for testing session structure
                'host' => '127.0.0.1',
                'port' => 9004,
                'connected' => true,
                'created_at' => time(),
                'last_activity' => time(),
                'session_info' => 'test',
            ],
        ];
        $property->setValue($this->server, $testSessions);

        // Get client for specific session ID (will return null due to null client)
        $result = $method->invoke($this->server, 'test_session');
        $this->assertNull($result); // Since client is null in test data

        // Verify null is returned for non-existent session ID
        $result = $method->invoke($this->server, 'non_existent_session');
        $this->assertNull($result);
    }

    public function testSessionCleanup(): void
    {
        $reflection = new ReflectionClass($this->server);
        $method = $reflection->getMethod('cleanupInactiveSessions');
        $method->setAccessible(true);

        $property = $reflection->getProperty('xdebugSessions');
        $property->setAccessible(true);

        // Create old and recent sessions
        $oldTime = time() - 3700; // More than 1 hour ago
        $recentTime = time() - 100; // Recent

        $testSessions = [
            'old_session' => [
                'client' => null,
                'host' => '127.0.0.1',
                'port' => 9004,
                'connected' => true,
                'created_at' => $oldTime,
                'last_activity' => $oldTime,
                'session_info' => 'old session',
            ],
            'recent_session' => [
                'client' => null,
                'host' => '127.0.0.1',
                'port' => 9004,
                'connected' => true,
                'created_at' => $recentTime,
                'last_activity' => $recentTime,
                'session_info' => 'recent session',
            ],
        ];
        $property->setValue($this->server, $testSessions);

        // Execute cleanup
        $method->invoke($this->server);

        // Verify old session was removed and recent session remains
        $sessions = $property->getValue($this->server);
        $this->assertCount(1, $sessions);
        $this->assertArrayHasKey('recent_session', $sessions);
        $this->assertArrayNotHasKey('old_session', $sessions);
    }

    private function invokePrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
