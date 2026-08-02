<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use Koriym\XdebugMcp\McpServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_column;
use function array_merge;
use function dirname;
use function explode;
use function fclose;
use function fwrite;
use function getenv;
use function is_array;
use function json_decode;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function trim;

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

        $initResponseObj = $this->invokeMethod($this->server, 'handleRequest', [$initRequest]);
        $initResponse = $initResponseObj->toArray();

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

        $toolsResponseObj = $this->invokeMethod($this->server, 'handleRequest', [$toolsRequest]);
        $toolsResponse = $toolsResponseObj->toArray();

        $this->assertEquals('2.0', $toolsResponse['jsonrpc']);
        $this->assertEquals(2, $toolsResponse['id']);
        $this->assertArrayHasKey('result', $toolsResponse);
        $this->assertArrayHasKey('tools', $toolsResponse['result']);
        $this->assertCount(6, $toolsResponse['result']['tools']);

        // Verify analysis tools are present
        $toolNames = array_column($toolsResponse['result']['tools'], 'name');
        $expectedTools = [
            'xtrace',
            'xprofile',
            'xstep',
            'xcoverage',
            'xback',
            'xcompare',
        ];

        foreach ($expectedTools as $toolName) {
            $this->assertContains($toolName, $toolNames, "Tool {$toolName} should be available");
        }
    }

    public function testToolsCallXCompare(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => 101,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xcompare',
                'arguments' => [
                    'script_a' => 'php tests/fake/loop-counter.php',
                    'script_b' => 'php tests/fake/array-manipulation.php',
                    'breakpoint' => 'tests/fake/loop-counter.php:10',
                    'context' => 'Tools call xcompare test',
                ],
            ],
        ];

        $responseObj = $this->invokeMethod($this->server, 'handleRequest', [$request]);
        $response = $responseObj->toArray();

        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(101, $response['id']);
        $this->assertArrayHasKey('content', $response['result']);
        $message = $response['result']['content'][0]['text'];
        $this->assertStringContainsString('Breakpoint comparison completed', $message);
        $this->assertStringContainsString('tests/fake/loop-counter.php', $message);
        $this->assertStringContainsString('tests/fake/array-manipulation.php', $message);
    }

    public function testErrorHandling(): void
    {
        // Test invalid method
        $invalidRequest = [
            'jsonrpc' => '2.0',
            'id' => 11,
            'method' => 'invalid/method',
        ];

        $responseObj = $this->invokeMethod($this->server, 'handleRequest', [$invalidRequest]);
        $response = $responseObj->toArray();

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

        $responseObj = $this->invokeMethod($this->server, 'handleRequest', [$invalidToolRequest]);
        $response = $responseObj->toArray();

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(12, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32000, $response['error']['code']);
        $this->assertStringContainsString('Unknown tool: invalid_tool', $response['error']['message']);
    }

    public function testMalformedLineIsNotWedgedAndValidRequestStillAnswered(): void
    {
        // Regression test for F2: a malformed line must emit a -32700 parse
        // error AND must not wedge the stream — the following valid request
        // must still be answered. Exercises the real bin/xdebug-mcp process.
        [$stdout] = $this->runServerProcess(
            '{oops not json}' . "\n" . '{"jsonrpc":"2.0","id":42,"method":"tools/list"}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $codes = array_column(array_column($responses, 'error'), 'code');
        $ids = array_column($responses, 'id');

        $this->assertContains(-32700, $codes, 'malformed line should produce a -32700 parse error');
        $this->assertContains(42, $ids, 'valid request after a malformed line must still be answered');
    }

    public function testRequestPayloadIsNotLoggedWithoutDebugMode(): void
    {
        [, $stderr] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $this->assertStringNotContainsString('MCP Debug:', $stderr);
        $this->assertStringNotContainsString('tools/list', $stderr);
    }

    public function testRequestPayloadIsLoggedWhenDebugModeEnabled(): void
    {
        [, $stderr] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' . "\n",
            ['MCP_DEBUG' => '1'],
        );

        $this->assertStringContainsString('MCP Debug:', $stderr);
        $this->assertStringContainsString('Raw Claude CLI input', $stderr);
        $this->assertStringContainsString('tools/list', $stderr);
    }

    public function testServerDiscoverOverStdio(): void
    {
        // MCP 2026-07-28: server/discover is the dual-era probe on stdio
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"server/discover"}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(1, $responses);

        $result = $responses[0]['result'];
        $this->assertContains('2026-07-28', $result['supportedVersions']);
        $this->assertContains('2024-11-05', $result['supportedVersions']);
        $this->assertArrayHasKey('capabilities', $result);
        $this->assertSame('complete', $result['resultType']);
        $this->assertSame(
            'xdebug-mcp-server',
            $result['_meta']['io.modelcontextprotocol/serverInfo']['name'],
        );
    }

    public function testStatelessModernWorkflowOverStdio(): void
    {
        // MCP 2026-07-28: no initialize handshake — every request carries its
        // protocol version and client capabilities in _meta
        $meta = '"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}';
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{' . $meta . '}}' . "\n"
            . '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xback","arguments":{"script":"php tests/fake/loop-counter.php","breakpoint":"tests/fake/loop-counter.php:10"},' . $meta . '}}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(2, $responses);

        $this->assertCount(6, $responses[0]['result']['tools']);
        $this->assertSame('complete', $responses[0]['result']['resultType']);
        $this->assertSame(
            'xdebug-mcp-server',
            $responses[0]['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'],
        );

        $this->assertArrayHasKey('content', $responses[1]['result']);
        // Match the success marker, not just "backtrace" — the failure
        // message ("Stack trace (backtrace) failed") contains it too
        $this->assertStringContainsString('Stack trace (backtrace) retrieved', $responses[1]['result']['content'][0]['text']);
        $this->assertStringNotContainsString('failed', $responses[1]['result']['content'][0]['text']);
    }

    public function testUnsupportedProtocolVersionOverStdio(): void
    {
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2030-01-01","io.modelcontextprotocol/clientCapabilities":{}}}}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(1, $responses);

        $error = $responses[0]['error'];
        $this->assertSame(-32022, $error['code']);
        $this->assertContains('2026-07-28', $error['data']['supported']);
        $this->assertSame('2030-01-01', $error['data']['requested']);
    }

    public function testServerDiscoverWithModernMetaOverStdio(): void
    {
        // Modern (2026-07-28) form: params._meta is required on every request,
        // including server/discover — exercise both eras of the probe
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(1, $responses);

        $result = $responses[0]['result'];
        $this->assertContains('2026-07-28', $result['supportedVersions']);
        $this->assertSame('complete', $result['resultType']);
    }

    public function testNotificationWithInvalidParamsGetsNoResponse(): void
    {
        // JSON-RPC 2.0 §4.1: notifications must not be answered, even with
        // an error — the following request must still get its response
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","method":"notifications/initialized","params":"oops"}' . "\n"
            . '{"jsonrpc":"2.0","id":9,"method":"tools/list"}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(1, $responses);
        $this->assertSame(9, $responses[0]['id']);
        $this->assertCount(6, $responses[0]['result']['tools']);
    }

    public function testModernMetaMissingRequiredFieldsOverStdio(): void
    {
        // _meta declares protocolVersion but omits clientCapabilities.
        // (A request with clientCapabilities but no protocolVersion has no
        // stateless marker and passes as legacy.)
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28"}}}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(1, $responses);
        $this->assertSame(-32602, $responses[0]['error']['code']);
    }

    public function testLegacyInitializeWorkflowOverStdio(): void
    {
        // Dual-era: legacy clients (2025-11-25 and earlier) keep working.
        // The initialized notification must produce no response.
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"legacy","version":"1.0"}}}' . "\n"
            . '{"jsonrpc":"2.0","method":"notifications/initialized"}' . "\n"
            . '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(2, $responses, 'notification must not produce a response');
        $this->assertSame('2025-06-18', $responses[0]['result']['protocolVersion']);
        $this->assertCount(6, $responses[1]['result']['tools']);
    }

    public function testRequestWithNonProtocolMetaPassesAsLegacyOverStdio(): void
    {
        // _meta without io.modelcontextprotocol/* keys (e.g. progressToken)
        // is not a modern request and must not be rejected
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{"progressToken":"tok1"}}}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(1, $responses);
        $this->assertCount(6, $responses[0]['result']['tools']);
    }

    public function testNonArrayParamsOverStdio(): void
    {
        // Regression: scalar params must yield -32602, not -32603 with internals
        [$stdout] = $this->runServerProcess(
            '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":"oops"}' . "\n",
            ['MCP_DEBUG' => ''],
        );

        $responses = $this->decodeResponses($stdout);
        $this->assertCount(1, $responses);
        $this->assertSame(-32602, $responses[0]['error']['code']);
    }

    /**
     * Spawn the real bin/xdebug-mcp CLI (no mocks), write $input to STDIN,
     * close the pipe (which makes the server's fgets loop reach EOF and exit),
     * and return its [stdout, stderr].
     *
     * @param array<string, string> $env
     *
     * @return array{0: string, 1: string}
     */
    private function runServerProcess(string $input, array $env): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $binary = dirname(__DIR__, 2) . '/bin/xdebug-mcp';

        // $_ENV is often nearly empty under PHPUnit (variables_order=GPCS);
        // forward the real environment so tool calls can locate php and
        // Xdebug — but strip inherited Xdebug settings, which take precedence
        // over the tools' own on-demand Xdebug configuration and break them
        $forward = getenv();
        unset($forward['XDEBUG_MODE'], $forward['XDEBUG_CONFIG'], $forward['XDEBUG_TRIGGER']);

        $process = proc_open(['php', $binary], $descriptorSpec, $pipes, null, array_merge($forward, $env));

        $this->assertIsResource($process);

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return [$stdout, $stderr];
    }

    /** @return list<array<string, mixed>> */
    private function decodeResponses(string $stdout): array
    {
        $responses = [];
        foreach (explode("\n", trim($stdout)) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }

            $responses[] = $decoded;
        }

        return $responses;
    }

    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }
}
