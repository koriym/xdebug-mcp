<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use Koriym\XdebugMcp\XdebugFinder;
use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function is_executable;
use function json_decode;
use function json_encode;
use function mkdir;
use function rmdir;
use function shell_exec;
use function sprintf;
use function str_starts_with;
use function strrpos;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

use const JSON_THROW_ON_ERROR;

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
        if (! file_exists($this->testScript)) {
            return;
        }

        unlink($this->testScript);
    }

    public function testXdebugMcpCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xdebug-mcp'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xdebug-mcp'));
    }

    public function testXtraceCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xtrace'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xtrace'));
    }

    public function testXtraceHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xtrace --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xtrace', $output);
    }

    public function testXtraceExecution(): void
    {
        $command = sprintf(
            'cd %s && ./bin/xtrace -- php %s 2>&1',
            dirname(__DIR__, 2),
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringContainsString('Computing factorial', $output);
    }

    public function testXprofileCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xprofile'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xprofile'));
    }

    public function testXprofileHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xprofile --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xprofile', $output);
    }

    public function testXprofileExecution(): void
    {
        $command = sprintf(
            'cd %s && ./bin/xprofile -- php %s 2>&1',
            dirname(__DIR__, 2),
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringContainsString('Computing factorial', $output);
    }

    public function testXcoverageCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xcoverage'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xcoverage'));
    }

    public function testXcoverageFormatsPhpUnitCoverageAndIgnoresNoCoverageFlag(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $root = dirname(__DIR__, 2);
        $buildDir = $root . '/build';
        if (! is_dir($buildDir)) {
            mkdir($buildDir);
        }

        $fixtureDir = tempnam($buildDir, 'xcoverage_phpunit_');
        $this->assertIsString($fixtureDir);
        unlink($fixtureDir);

        $sourceDir = $fixtureDir . '/src';
        $testDir = $fixtureDir . '/tests';
        mkdir($sourceDir, 0777, true);
        mkdir($testDir, 0777, true);

        $sourceFile = $sourceDir . '/IgnoredSubject.php';
        $testFile = $testDir . '/IgnoredSubjectTest.php';

        file_put_contents($sourceFile, <<<'PHP'
<?php
final class IgnoredSubject
{
    public function value(bool $flag): string
    {
        if ($flag) {
            return 'covered';
        }

        return 'ignored'; // @codeCoverageIgnore
    }
}
PHP);

        file_put_contents($testFile, <<<'PHP'
<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/IgnoredSubject.php';

final class IgnoredSubjectTest extends TestCase
{
    public function testCoveredBranch(): void
    {
        self::assertSame('covered', (new IgnoredSubject())->value(true));
    }
}
PHP);

        try {
            $command = sprintf(
                'cd %s && ./bin/xcoverage -- php ./vendor/bin/phpunit --no-coverage --no-configuration --coverage-filter %s %s 2>&1',
                escapeshellarg($root),
                escapeshellarg($sourceDir),
                escapeshellarg($testFile),
            );
            $output = shell_exec($command);

            $this->assertNotNull($output);
            $jsonStart = strrpos($output, "{\n    \"\$schema\"");
            $this->assertNotFalse($jsonStart, $output);

            $coverage = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('phpunit', $coverage['mode']);
            $this->assertSame(100.0, (float) $coverage['summary']['coverage_percent']);
            $this->assertSame([], $coverage['uncovered']);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }

            if (file_exists($sourceFile)) {
                unlink($sourceFile);
            }

            if (is_dir($testDir)) {
                rmdir($testDir);
            }

            if (is_dir($sourceDir)) {
                rmdir($sourceDir);
            }

            if (is_dir($fixtureDir)) {
                rmdir($fixtureDir);
            }
        }
    }

    public function testXstepCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xstep'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xstep'));
    }

    public function testXstepHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xstep --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xstep', $output);
    }

    public function testXbackCommandExists(): void
    {
        $this->assertTrue(file_exists(__DIR__ . '/../../bin/xback'));
        $this->assertTrue(is_executable(__DIR__ . '/../../bin/xback'));
    }

    public function testXbackHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xback --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xback', $output);
    }

    public function testAllCommandsAreExecutable(): void
    {
        $commands = [
            'xdebug-mcp',
            'xtrace',
            'xprofile',
            'xcoverage',
            'xstep',
            'xback',
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
        $this->assertCount(5, $response['result']['tools'], 'Should have 5 execution tools');
    }
}
