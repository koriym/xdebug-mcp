<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use Koriym\XdebugMcp\XdebugFinder;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function bin2hex;
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
use function random_bytes;
use function realpath;
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
use function var_export;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

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

        $fixtureDir = $buildDir . '/xcoverage_phpunit_' . bin2hex(random_bytes(6));
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
            $schemaPos = strrpos($output, '"$schema"');
            $this->assertNotFalse($schemaPos, $output);
            $jsonStart = strrpos(substr($output, 0, $schemaPos), '{');
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

    public function testXcoverageRawSourceFilterExcludesSiblingFiles(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $root = dirname(__DIR__, 2);
        $buildDir = $root . '/build';
        if (! is_dir($buildDir)) {
            mkdir($buildDir);
        }

        $fixtureDir = $buildDir . '/xcoverage_source_' . bin2hex(random_bytes(6));
        $sourceDir = $fixtureDir . '/src';
        $extraDir = $fixtureDir . '/extra';
        mkdir($sourceDir, 0777, true);
        mkdir($extraDir, 0777, true);

        $sourceFile = $sourceDir . '/InSrc.php';
        $extraFile = $extraDir . '/Sibling.php';
        $entryFile = $fixtureDir . '/entry.php';

        file_put_contents($sourceFile, <<<'PHP'
<?php
function in_src_call(): string
{
    $touched = 'covered';
    $alsoTouched = 'still covered';
    if ($touched === 'never') {
        return 'unreachable';
    }

    return $touched . $alsoTouched;
}
PHP);

        file_put_contents($extraFile, <<<'PHP'
<?php
function sibling_uncalled(): string
{
    $never = 'reached';
    $also = 'never';
    return $never . $also;
}
PHP);

        file_put_contents($entryFile, sprintf(
            "<?php\nrequire %s;\nrequire %s;\nin_src_call();\n",
            var_export($sourceFile, true),
            var_export($extraFile, true),
        ));

        try {
            $command = sprintf(
                'cd %s && ./bin/xcoverage --raw --source=%s -- php %s 2>&1',
                escapeshellarg($root),
                escapeshellarg($sourceDir),
                escapeshellarg($entryFile),
            );

            $output = shell_exec($command);
            $this->assertNotNull($output);

            $jsonStart = strrpos($output, '{"$schema"');
            $this->assertNotFalse($jsonStart, $output);

            $coverage = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);

            $resolvedExtra = realpath($extraDir);
            $this->assertNotFalse($resolvedExtra);

            // The sibling file should NOT appear in uncovered when --source is used
            foreach (array_keys($coverage['uncovered'] ?? []) as $file) {
                $this->assertStringNotContainsString($resolvedExtra, $file, 'Sibling file leaked into filtered raw coverage');
            }
        } finally {
            foreach ([$entryFile, $extraFile, $sourceFile] as $f) {
                if (! file_exists($f)) {
                    continue;
                }

                unlink($f);
            }

            foreach ([$extraDir, $sourceDir, $fixtureDir] as $d) {
                if (! is_dir($d)) {
                    continue;
                }

                rmdir($d);
            }
        }
    }

    public function testXcoverageRawSupportsPhpRunOption(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $command = sprintf(
            'cd %s && ./bin/xcoverage --raw -- php -r %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg('echo strlen("abc"), PHP_EOL;'),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringContainsString("3\n", $output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $coverage = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('raw', $coverage['mode']);
        $this->assertSame('line', $coverage['coverage_type']);
        $this->assertGreaterThanOrEqual(1, $coverage['summary']['files']);
        $this->assertGreaterThanOrEqual(1, $coverage['summary']['covered_lines']);
    }

    public function testXcoverageRawPhpRunPreservesLineConstant(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $command = sprintf(
            'cd %s && ./bin/xcoverage --raw -- php -r %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg('echo __LINE__, PHP_EOL;'),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringStartsWith("1\n", $output);
    }

    public function testXcoverageRawPhpRunPreservesLeadingStrictTypesDeclare(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $command = sprintf(
            'cd %s && ./bin/xcoverage --raw -- php -r %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg('declare(strict_types=1); echo __LINE__, PHP_EOL;'),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringStartsWith("1\n", $output);
        $this->assertStringNotContainsString('strict_types declaration must be the very first statement', $output);
    }

    public function testXcoverageRawBranchPhpRunFailsBeforeExecutingInlineCode(): void
    {
        $command = sprintf(
            'cd %s && ./bin/xcoverage --raw --branch-coverage -- php -r %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg('if (true) { echo "branch", PHP_EOL; }'),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringContainsString('--branch-coverage is not supported with PHP inline code (-r)', $output);
        $this->assertStringNotContainsString("branch\n", $output);
        $this->assertStringNotContainsString('Segmentation fault', $output);
    }

    public function testXprofileSupportsPhpRunOption(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $command = sprintf(
            'cd %s && ./bin/xprofile --json -- php -r %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg('echo strlen("abc"), PHP_EOL;'),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"specification"');
        $this->assertNotFalse($jsonStart, $output);

        $profile = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('file', $profile);
        $this->assertArrayHasKey('bottlenecks', $profile);
        $this->assertSame("3\n", $profile['output']);
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
        $this->assertStringContainsString('--pretty', $output);
        $this->assertStringContainsString('--max-value-bytes=N', $output);
        $this->assertStringContainsString('--max-depth=N', $output);
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
        $this->assertStringContainsString('--cwd=', $output);
        $this->assertStringContainsString('--php=', $output);
    }

    public function testXcoverageHelp(): void
    {
        $output = shell_exec('cd ' . dirname(__DIR__, 2) . ' && ./bin/xcoverage --help 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('xcoverage', $output);
        $this->assertStringContainsString('--cwd=', $output);
        $this->assertStringContainsString('--php=', $output);
        $this->assertStringContainsString('--source=', $output);
    }

    public function testXcoverageCwdOption(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/print_cwd.php';
        $tmpDir = sys_get_temp_dir();

        $command = sprintf(
            'cd %s && ./bin/xcoverage --raw --cwd=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($tmpDir),
            escapeshellarg($fixture),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        // print_cwd.php prints the cwd; --cwd should have made it the temp dir
        $this->assertStringContainsString($tmpDir, $output);
    }

    public function testXbackCwdOption(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/print_cwd.php';
        $tmpDir = sys_get_temp_dir();

        $command = sprintf(
            'cd %s && ./bin/xback --cwd=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($tmpDir),
            escapeshellarg($fixture),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        // xback emits a single JSON document; --cwd should not break execution
        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('$schema', $payload);
    }

    public function testXbackPhpOption(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        // Resolve the current PHP binary as the override target. Using PHP_BINARY
        // guarantees the path exists and is executable on the running system.
        $phpBinary = realpath(PHP_BINARY);
        if ($phpBinary === false || ! is_executable($phpBinary)) {
            $this->markTestSkipped('Cannot resolve PHP_BINARY for --php override test');
        }

        $fixture = dirname(__DIR__) . '/fixtures/print_cwd.php';

        // Run with --php pointing at an absolute PHP binary path. Without the
        // fix this fails with "Custom command must start with 'php' or be a
        // Docker/Podman/Kubectl command" because $command[0] becomes the
        // absolute path and DebugServer's strict-equality check rejects it.
        $command = sprintf(
            'cd %s && ./bin/xback --php=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($phpBinary),
            escapeshellarg($fixture),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringNotContainsString(
            "must start with 'php'",
            $output,
            '--php override should not fall through to the DebugServer error path',
        );

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('$schema', $payload);
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
        $this->assertCount(6, $response['result']['tools'], 'Should have 6 execution tools');
    }
}
