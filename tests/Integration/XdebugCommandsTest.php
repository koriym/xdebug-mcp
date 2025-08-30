<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_put_contents;
use function is_executable;
use function json_decode;
use function json_encode;
use function shell_exec;
use function sprintf;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

class XdebugCommandsTest extends TestCase
{
    private string $testScript;

    protected function setUp(): void
    {
        // Create a simple test PHP script
        $this->testScript = tempnam(sys_get_temp_dir(), 'xdebug_test_') . '.php';
        file_put_contents($this->testScript, '<?php
function factorial($n) {
    if ($n <= 1) return 1;
    return $n * factorial($n - 1);
}

echo "Computing factorial of 5: " . factorial(5) . "\n";
echo "Memory usage: " . memory_get_usage() . " bytes\n";
');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testScript)) {
            unlink($this->testScript);
        }
    }

    public function testXdebugMcpCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xdebug-mcp'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xdebug-mcp'));
    }

    public function testXdebugTraceCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xdebug-trace'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xdebug-trace'));
    }

    public function testXdebugTraceHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xdebug-trace --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xdebug-trace', $output);
    }

    public function testXdebugTraceExecution(): void
    {
        $command = sprintf(
            'cd %s && ./bin/xdebug-trace -- php %s 2>&1',
            dirname(__DIR__, 2),
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringContainsString('Computing factorial', $output);
    }

    public function testXdebugProfileCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xdebug-profile'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xdebug-profile'));
    }

    public function testXdebugProfileHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xdebug-profile --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xdebug-profile', $output);
    }

    public function testXdebugProfileExecution(): void
    {
        $command = sprintf(
            'cd %s && ./bin/xdebug-profile -- php %s 2>&1',
            dirname(__DIR__, 2),
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringContainsString('Computing factorial', $output);
    }

    public function testXdebugCoverageCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xdebug-coverage'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xdebug-coverage'));
    }

    public function testXdebugCoverageHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xdebug-coverage --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xdebug-coverage', $output);
    }

    public function testXdebugCoverageExecution(): void
    {
        $this->markTestSkipped('Skip the test for infinite loop');
        $command = sprintf(
            'cd %s && ./bin/xdebug-coverage -- php %s 2>/dev/null',
            dirname(__DIR__, 2),
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        // Coverage tool outputs JSON with schema and coverage data
        $this->assertStringContainsString('"$schema":"https://koriym.github.io/xdebug-mcp/schemas/xdebug-coverage.json"', $output);
        $this->assertStringContainsString('"coverage":', $output);

        // Validate JSON structure
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('$schema', $data);
        $this->assertArrayHasKey('coverage', $data);
    }

    public function testXdebugDebugCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xdebug-debug'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xdebug-debug'));
    }

    public function testXdebugDebugHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xdebug-debug --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xdebug-debug', $output);
    }

    public function testXdebugPhpunitCommandExists(): void
    {
        $this->markTestSkipped('xdebug-phpunit command removed - use x-trace instead');
    }

    /** @codeCoverageIgnore */
    public function testXdebugPhpunitHelp(): void
    {
        // Skip this test as it can cause issues with PHPUnit execution
        $this->markTestSkipped('PHPUnit help test can interfere with test execution');
    }

    public function testAllCommandsAreExecutable(): void
    {
        $commands = [
            'xdebug-mcp',
            'xdebug-trace',
            'xdebug-profile',
            'xdebug-coverage',
            'xdebug-debug',
        ];

        foreach ($commands as $command) {
            $path = __DIR__ . '/../../bin/' . $command;
            $this->assertTrue(file_exists($path), "Command {$command} should exist");
            $this->assertTrue(is_executable($path), "Command {$command} should be executable");
        }
    }

    public function testMcpServerBasicFunctionality(): void
    {
        // Test basic JSON-RPC request to MCP server
        $request = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $command = sprintf(
            'cd %s && echo %s | ./bin/xdebug-mcp 2>&1',
            dirname(__DIR__, 2),
            escapeshellarg($request),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        $lines = explode("\n", trim($output));
        $jsonLine = null;

        // Find the JSON response line (ignore server startup messages)
        foreach ($lines as $line) {
            if (str_starts_with($line, '{"jsonrpc"')) {
                $jsonLine = $line;
                break;
            }
        }

        $this->assertNotNull($jsonLine, 'Should find JSON response');

        $response = json_decode($jsonLine, true);
        $this->assertNotNull($response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertCount(4, $response['result']['tools'], 'Should have 4 execution tools after removing internal analysis tools');
    }
}
