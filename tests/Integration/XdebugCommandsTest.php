<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use JsonSchema\Validator;
use Koriym\XdebugMcp\XdebugFinder;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function bin2hex;
use function chmod;
use function dirname;
use function escapeshellarg;
use function exec;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function glob;
use function implode;
use function is_dir;
use function is_executable;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function realpath;
use function rmdir;
use function shell_exec;
use function sprintf;
use function str_repeat;
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
                'cd %s && ./bin/xcoverage --json -- php ./vendor/bin/phpunit --no-coverage --no-configuration --coverage-filter %s %s 2>&1',
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

    /**
     * PHPUnit's clover report nests the files of a namespaced class under a
     * <package> element; those lines count like any other.
     */
    public function testXcoverageCountsTheFilesOfANamespacedClass(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $root = dirname(__DIR__, 2);
        $fixtureDir = sys_get_temp_dir() . '/xcoverage_package_' . bin2hex(random_bytes(6));
        mkdir($fixtureDir . '/src', 0777, true);
        mkdir($fixtureDir . '/tests');
        $sourceFile = $fixtureDir . '/src/Subject.php';
        $testFile = $fixtureDir . '/tests/SubjectTest.php';

        file_put_contents($sourceFile, <<<'PHP'
<?php
namespace Acme;

final class Subject
{
    public function value(bool $flag): string
    {
        if ($flag) {
            return 'covered';
        }

        return 'uncovered';
    }
}
PHP);
        file_put_contents($testFile, <<<'PHP'
<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Subject.php';

final class SubjectTest extends TestCase
{
    public function testCoveredBranch(): void
    {
        self::assertSame('covered', (new Acme\Subject())->value(true));
    }
}
PHP);

        try {
            $stdout = [];
            $exitCode = null;
            exec(sprintf(
                'cd %s && ./bin/xcoverage --json -- php ./vendor/bin/phpunit --no-configuration --coverage-filter %s %s 2>/dev/null',
                escapeshellarg($root),
                escapeshellarg($fixtureDir . '/src'),
                escapeshellarg($testFile),
            ), $stdout, $exitCode);
            $this->assertSame(0, $exitCode, implode("\n", $stdout));

            $coverage = json_decode(implode("\n", $stdout), true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($coverage);
            $this->assertSame(1, $coverage['summary']['files']);
            $this->assertGreaterThan(0, $coverage['summary']['covered_lines']);
            $this->assertSame([12], $coverage['uncovered'][realpath($sourceFile)] ?? null);
        } finally {
            unlink($testFile);
            unlink($sourceFile);
            rmdir($fixtureDir . '/tests');
            rmdir($fixtureDir . '/src');
            rmdir($fixtureDir);
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
                'cd %s && ./bin/xcoverage --raw --json --source=%s -- php %s 2>&1',
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
            'cd %s && ./bin/xcoverage --raw --json -- php -r %s 2>&1',
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

    public function testXcoverageRawDefaultsToCompactFormat(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        // 2>/dev/null drops the program output on STDERR; STDOUT must be the compact report only
        $command = sprintf(
            'cd %s && ./bin/xcoverage --raw -- php demo/coverage.php 2>/dev/null',
            escapeshellarg(dirname(__DIR__, 2)),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertMatchesRegularExpression('/^\d+(?:\.\d+)?% \(\d+\/\d+ lines covered, \d+ files?\)\n/', $output);
        $this->assertStringContainsString('demo/coverage.php:', $output);
        $this->assertStringNotContainsString('{"$schema"', $output);
        $this->assertStringNotContainsString('Code Coverage Demo', $output);
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
        $output = $this->assertAndStripEnvNotice($output);
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
        $output = $this->assertAndStripEnvNotice($output);
        $this->assertStringStartsWith("1\n", $output);
        $this->assertStringNotContainsString('strict_types declaration must be the very first statement', $output);
    }

    /**
     * Assert the inherited-Xdebug-env notice (STDERR) appears exactly when
     * this test process inherited env that xcoverage actually overrides,
     * and return the output with the notice line removed
     */
    private function assertAndStripEnvNotice(string $output): string
    {
        $hasNotice = preg_match('/^Note: inherited .*\n/m', $output) === 1;
        $mode = getenv('XDEBUG_MODE');
        $expectNotice = ($mode !== false && $mode !== 'coverage')
            || getenv('XDEBUG_CONFIG') !== false
            || getenv('XDEBUG_TRIGGER') !== false;
        $this->assertSame($expectNotice, $hasNotice);

        return (string) preg_replace('/^Note: inherited .*\n/m', '', $output);
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

    public function testXstepAcceptsAbsolutePhpBinaryPath(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/debug_test.php';
        $command = sprintf(
            'cd %s && ./bin/xstep --break=%s -- %s %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':27'),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($fixture),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringNotContainsString('Could not determine target script', $output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://koriym.github.io/xdebug-mcp/schemas/xstep.json', $payload['$schema']);
        $this->assertSame(27, $payload['breaks'][0]['stack'][0]['line']);
    }

    /**
     * The auto_prepend helpers share the target's global scope, so their own
     * variables used to appear in every captured dump — and would overwrite a
     * target variable of the same name. The breakpoint must sit at top-level
     * scope: inside a function only locals are dumped, which hides the leak.
     */
    public function testCapturedVariablesExcludeAutoPrependInternals(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/debug_friendly.php';
        $output = shell_exec(sprintf(
            'cd %s && ./bin/xstep --break=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':26'),
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $variables = $payload['breaks'][0]['variables'];
        $this->assertArrayHasKey('$number', $variables, 'Expected the target own globals to be captured');
        $this->assertArrayNotHasKey('$vendorPath', $variables);
        $this->assertArrayNotHasKey('$excludePaths', $variables);
    }

    public function testCapturedObjectsIncludeNonPublicProperties(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/private_properties.php';
        $output = shell_exec(sprintf(
            'cd %s && ./bin/xstep --max-depth=2 --max-value-bytes=2000 --break=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':23'),
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $variables = $payload['breaks'][0]['variables'];
        $this->assertSame(
            'object: {"__class": "PrivatePropertiesHolder", "PrivatePropertiesBase::name": "base", "items": {"k": 1}, "prot": "p", "name": "child", '
            . '"self": {"__class": "PrivatePropertiesHolder", "PrivatePropertiesBase::name": "base", "items": "[MAX_DEPTH array(1)]", "prot": "p", "name": "child", "self": "[MAX_DEPTH object:PrivatePropertiesHolder]"}}',
            $variables['$this'],
        );
        $this->assertSame('object: {"__class": "PrivatePropertiesOther", "secret": 42}', $variables['$other']);
    }

    public function testRenderingStaysBoundedAndKeepsClassNames(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/recursive_values.php';
        $output = shell_exec(sprintf(
            'cd %s && ./bin/xstep --break=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':33'),
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $variables = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR)['breaks'][0]['variables'];
        $this->assertSame('array: {"x": 1, "self": "[RECURSION array]"}', $variables['$recursive']);
        $this->assertStringEndsWith(', 32, "... (100000 total)"]', $variables['$large']);
        $this->assertSame('string: ' . str_repeat('あ', 341) . '... (truncated, 1024 bytes)', $variables['$cut']);
        $this->assertSame('object: {"__class": "stdClass"}', $variables['$empty']);
        $this->assertSame('object: {"__class": "ClassProperty", "ClassProperty::__class": "shadow"}', $variables['$named']);
        $this->assertSame('array: ["INF", "-INF", "NAN", 1.5]', $variables['$floats']);
        $this->assertSame('array: {"日本": 1, "*Base*name": {"__class": "stdClass", "*Base*name": 2}}', $variables['$names']);
        $this->assertSame('object: {"__class": "Shadow", "ShadowBase::name": "base", "Shadow::name": "child"}', $variables['$shadow']);
        $this->assertSame('string: var', $variables['$日本']);
        $this->assertSame("string: \u{FFFD}\u{FFFD}abc", $variables['$invalid']);
    }

    public function testVarsSelectsMultibyteVariableName(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/recursive_values.php';
        $output = shell_exec(sprintf(
            'cd %s && ./bin/xstep --break=%s --vars=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':33'),
            escapeshellarg('$日本'),
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $variables = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR)['breaks'][0]['variables'];
        $this->assertSame(['$日本' => 'string: var'], $variables);
    }

    public function testWatchRecordsChangesNestedInsideAnArray(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/nested_watch.php';
        $output = shell_exec(sprintf(
            'cd %s && ./bin/xstep --break=%s --steps=10 --watch=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':5'),
            escapeshellarg('$a'),
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $reasons = array_map(
            static fn (array $break): string => $break['watches'][0]['reason'],
            json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR)['breaks'],
        );
        $this->assertSame(['initial', 'changed', 'changed', 'changed'], $reasons);
    }

    public function testComposerGlobalsAreLeftOutOfRecordedVariables(): void
    {
        $variables = $this->runXstepOnComposerGlobals('')[0]['variables'];

        $this->assertArrayHasKey('$a', $variables);
        $this->assertArrayNotHasKey('$__composer_autoload_files', $variables);
    }

    public function testVarsLimitsRecordedVariablesAndDiff(): void
    {
        $breaks = $this->runXstepOnComposerGlobals("--steps=3 --vars='a, \$c'");

        $this->assertSame(['$a' => 'int: 1', '$c' => 'uninitialized: '], $breaks[0]['variables']);
        $this->assertArrayNotHasKey('diff', $breaks[1], 'Expected the change to the unlisted $b to be left out');
        $this->assertSame(['$c'], array_keys($breaks[2]['diff']));
    }

    public function testVarsMatchingNothingRecordsAnEmptyObject(): void
    {
        $this->assertStringContainsString('"variables":{}', $this->xstepJsonOnComposerGlobals('--vars=nothere'));
    }

    /** @return list<array<string, mixed>> */
    private function runXstepOnComposerGlobals(string $options): array
    {
        return json_decode($this->xstepJsonOnComposerGlobals($options), true, 512, JSON_THROW_ON_ERROR)['breaks'];
    }

    private function xstepJsonOnComposerGlobals(string $options): string
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/composer_globals.php';
        $output = shell_exec(sprintf(
            'cd %s && ./bin/xstep --break=%s %s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':8'),
            $options,
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        return substr($output, $jsonStart);
    }

    /**
     * A working directory without vendor/ leaves the filter off. The prepend says
     * so on stderr, keeps its locals out of the target's scope, and stays quiet
     * when the filter was switched off on purpose.
     *
     * The target runs through bin/xtrace, which decides how to load Xdebug in its
     * own process. A child started here would get no Xdebug flag when this test
     * process already has Xdebug from `-d` (as under `composer coverage`), and the
     * prepend would return before the notice.
     */
    public function testPrependFilterReportsAMissingVendorDirWithoutLeakingVariables(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $dir = sys_get_temp_dir() . '/xdebug-mcp-novendor-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $script = $dir . '/target.php';
        file_put_contents($script, "<?php\n\$mine = 1;\necho implode(',', array_keys(get_defined_vars())), PHP_EOL;\n");
        $run = function (string $env) use ($dir): string {
            $output = shell_exec(sprintf(
                'cd %s && %s%s %s --json -- %s target.php 2>&1',
                escapeshellarg($dir),
                $env,
                escapeshellarg(PHP_BINARY),
                escapeshellarg(dirname(__DIR__, 2) . '/bin/xtrace'),
                escapeshellarg(PHP_BINARY),
            ));
            $this->assertIsString($output);

            $jsonStart = strrpos($output, '{"specification"');
            $this->assertNotFalse($jsonStart, $output);
            $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($payload);
            unlink($payload['file']);

            return $output;
        };

        try {
            $output = $run('');
            $this->assertStringContainsString('mine', $output);
            $this->assertStringNotContainsString('vendorPath', $output);
            $this->assertStringNotContainsString('excludePaths', $output);
            $this->assertStringContainsString('vendor filter inactive: no vendor/ at ' . realpath($dir), $output);

            $optedOut = $run('XDEBUG_MCP_DISABLE_VENDOR_FILTER=1 ');
            $this->assertStringContainsString('mine', $optedOut);
            $this->assertStringNotContainsString('vendor filter inactive', $optedOut);
        } finally {
            unlink($script);
            rmdir($dir);
        }
    }

    /**
     * The vendor tree to exclude is the traced project's, found from the working
     * directory. Resolving it from the tool's own location excluded the tool's
     * vendor instead, so with a global install the project's packages filled the trace.
     *
     * Xdebug's path filter matches the call site, so the project's own call into
     * the package stays visible; the call made inside the package is what drops out.
     */
    public function testXtraceExcludesTheVendorOfTheWorkingDirectory(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $project = sys_get_temp_dir() . '/xdebug-mcp-project-' . bin2hex(random_bytes(6));
        mkdir($project . '/vendor/acme/lib', 0777, true);
        file_put_contents($project . '/vendor/acme/lib/Lib.php', "<?php\nnamespace Acme;\nfinal class Lib\n{\n    public function work(): int\n    {\n        return \$this->inner();\n    }\n\n    private function inner(): int\n    {\n        return 1;\n    }\n}\n");
        file_put_contents($project . '/main.php', "<?php\nrequire __DIR__ . '/vendor/acme/lib/Lib.php';\nfunction app_entry(): int\n{\n    return (new Acme\\Lib())->work();\n}\napp_entry();\n");

        try {
            $output = shell_exec(sprintf(
                'cd %s && %s %s --json -- %s main.php 2>/dev/null',
                escapeshellarg($project),
                escapeshellarg(PHP_BINARY),
                escapeshellarg(dirname(__DIR__, 2) . '/bin/xtrace'),
                escapeshellarg(PHP_BINARY),
            ));
            $this->assertIsString($output);

            $jsonStart = strrpos($output, '{"specification"');
            $this->assertNotFalse($jsonStart, $output);
            $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
            $trace = file_get_contents($payload['file']);
            unlink($payload['file']);
            $this->assertNotFalse($trace);

            $this->assertStringContainsString('app_entry', $trace);
            $this->assertStringNotContainsString('Acme\\Lib->inner', $trace);
        } finally {
            unlink($project . '/main.php');
            unlink($project . '/vendor/acme/lib/Lib.php');
            rmdir($project . '/vendor/acme/lib');
            rmdir($project . '/vendor/acme');
            rmdir($project . '/vendor');
            rmdir($project);
        }
    }

    /**
     * Regression test: `php -d key=value script.php` must resolve the script
     * argument, not the `-d` flag itself. The old code took $command[1]
     * verbatim as the script whenever $command[0] === 'php', which broke on
     * any interpreter option preceding the script.
     */
    public function testXstepAcceptsPhpWithDOptionBeforeScript(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/debug_test.php';
        $command = sprintf(
            'cd %s && ./bin/xstep --break=%s -- php -d memory_limit=256M %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':27'),
            escapeshellarg($fixture),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringNotContainsString('Could not determine target script', $output);
        $this->assertStringNotContainsString('Target script not found', $output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://koriym.github.io/xdebug-mcp/schemas/xstep.json', $payload['$schema']);
        $this->assertSame(27, $payload['breaks'][0]['stack'][0]['line']);
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

    /**
     * A failing run removes its bootstrap and report files too. They go to
     * TMPDIR, so a private one shows exactly what the run left.
     */
    public function testXcoverageRawLeavesNoTemporaryFilesWhenTheTargetFails(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $tmpDir = sys_get_temp_dir() . '/xdebug-mcp-tmpdir-' . bin2hex(random_bytes(6));
        mkdir($tmpDir);

        try {
            $output = [];
            $exitCode = null;
            exec(sprintf(
                'cd %s && TMPDIR=%s ./bin/xcoverage --raw -- php %s 2>&1',
                escapeshellarg(dirname(__DIR__, 2)),
                escapeshellarg($tmpDir),
                escapeshellarg($tmpDir . '/missing.php'),
            ), $output, $exitCode);

            $this->assertSame(1, $exitCode, implode("\n", $output));
            $this->assertSame([], glob($tmpDir . '/*'));
        } finally {
            foreach (glob($tmpDir . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($tmpDir);
        }
    }

    /**
     * The report travels apart from the target's output. On the target's stdout it
     * joined a last line without a newline or went through the target's output
     * buffer, and the run failed with "coverage JSON was not produced"; a target
     * line starting with {"$schema" was taken for the report and dropped. Scripts
     * and inline code are bootstrapped differently, so both are run.
     */
    public function testXcoverageRawKeepsTheTargetOutputApartFromTheReport(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $dir = sys_get_temp_dir() . '/xdebug-mcp-report-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/target.php', "<?php\necho 'no trailing newline';\n");
        $stderrFile = $dir . '/stderr.txt';
        $targets = [
            'script without a trailing newline' => [escapeshellarg($dir . '/target.php'), 'no trailing newline'],
            'inline code without a trailing newline' => ['-r ' . escapeshellarg("echo 'no trailing newline';"), 'no trailing newline'],
            'output that looks like the report' => ['-r ' . escapeshellarg('echo \'{"$schema":"x"}\', PHP_EOL;'), '{"$schema":"x"}'],
            'output buffer that rewrites the output' => ['-r ' . escapeshellarg('ob_start(static fn (string $b): string => strtoupper($b)); echo "target", PHP_EOL;'), 'TARGET'],
        ];

        try {
            foreach ($targets as $label => [$target, $targetOutput]) {
                $stdoutLines = [];
                $exitCode = null;
                exec(sprintf(
                    'cd %s && ./bin/xcoverage --raw --json -- php %s 2>%s',
                    escapeshellarg(dirname(__DIR__, 2)),
                    $target,
                    escapeshellarg($stderrFile),
                ), $stdoutLines, $exitCode);
                $this->assertSame(0, $exitCode, $label);

                $report = json_decode(implode("\n", $stdoutLines), true, 512, JSON_THROW_ON_ERROR);
                $this->assertIsArray($report, $label);
                $this->assertSame('line', $report['coverage_type'], $label);
                $this->assertStringContainsString($targetOutput, (string) file_get_contents($stderrFile), $label);
            }
        } finally {
            unlink($dir . '/target.php');
            if (file_exists($stderrFile)) {
                unlink($stderrFile);
            }

            rmdir($dir);
        }
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

        $command = sprintf(
            'cd %s && ./bin/xback --php=%s -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($phpBinary),
            escapeshellarg($fixture),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertStringNotContainsString(
            'must start with a PHP binary',
            $output,
            '--php override should not fall through to the DebugServer error path',
        );

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('$schema', $payload);
    }

    public function testXbackOutputsBacktraceSchema(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/debug_test.php';
        $command = sprintf(
            'cd %s && ./bin/xback --break=%s --depth=2 -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':18'),
            escapeshellarg($fixture),
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);
        $json = substr($output, $jsonStart);

        $schema = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/docs/schemas/xback.json'), false, 512, JSON_THROW_ON_ERROR);
        $document = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        $validator = new Validator();
        $validator->validate($document, $schema);
        $this->assertTrue($validator->isValid(), var_export($validator->getErrors(), true));

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://koriym.github.io/xdebug-mcp/schemas/xback.json', $payload['$schema']);
        $this->assertSame($fixture, $payload['script']);
        $this->assertSame([
            ['level' => 0, 'function' => 'calculate_sum', 'file' => 'debug_test.php', 'line' => 18],
            ['level' => 1, 'function' => 'main', 'file' => 'debug_test.php', 'line' => 27],
        ], $payload['stack']);
    }

    /**
     * `xback -- php script.php` documents "stack at first executable line".
     * Without a breakpoint there is nothing to stop at, so the stack came back
     * empty; the capture must also skip the injected auto_prepend helper and
     * report a frame in the target script itself.
     */
    public function testXbackWithoutBreakpointCapturesFirstLineOfTargetScript(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/debug_test.php';
        $output = shell_exec(sprintf(
            'cd %s && ./bin/xback -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($output);

        $jsonStart = strrpos($output, '{"$schema"');
        $this->assertNotFalse($jsonStart, $output);

        $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotSame([], $payload['stack'], 'Default run must report a location');
        $this->assertSame('debug_test.php', $payload['stack'][0]['file']);
    }

    /**
     * The helper is skipped by full path, not by basename: a target script
     * named prepend_trace.php used to satisfy the "reached the target" check
     * while execution was still inside the injected helper.
     */
    public function testXbackSkipsPrependHelperEvenWhenTargetSharesItsName(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $dir = sys_get_temp_dir() . '/xdebug-mcp-collide-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $script = $dir . '/prepend_trace.php';
        file_put_contents($script, "<?php\n\$mine = 41 + 1;\necho \$mine, PHP_EOL;\n");

        try {
            $output = shell_exec(sprintf(
                'cd %s && ./bin/xback -- php %s 2>&1',
                escapeshellarg(dirname(__DIR__, 2)),
                escapeshellarg($script),
            ));
            $this->assertNotNull($output);

            $jsonStart = strrpos($output, '{"$schema"');
            $this->assertNotFalse($jsonStart, $output);

            $payload = json_decode(substr($output, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
            // The target has 3 lines; the helper's first break sits past its end.
            $this->assertSame(2, $payload['stack'][0]['line']);
        } finally {
            unlink($script);
            rmdir($dir);
        }
    }

    /**
     * A target whose Xdebug cannot be resolved must be reported the same way
     * by every tool: one line naming the target version, exit 1. xstep and
     * xback used to print a result document with an empty `breaks`/`stack`
     * first — which reads as a successful run that hit nothing — and then die
     * with an uncaught fatal.
     */
    public function testUnresolvableTargetIsReportedUniformly(): void
    {
        $dir = sys_get_temp_dir() . '/xdebug-mcp-unresolvable-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        // The name must satisfy PhpCommandParser::isPhpBinary(), or the tools
        // treat this path as a script argument instead of the interpreter.
        $fake = $dir . '/php4.9';
        // Answers the probe as a PHP version no Xdebug build can satisfy.
        file_put_contents($fake, "#!/bin/sh\necho \"__XDEBUG_MCP_PROBE__|0|/nonexistent|4.9\"\n");
        chmod($fake, 0o755);
        $script = $dir . '/t.php';
        file_put_contents($script, "<?php\n\$x = 1;\necho \$x, PHP_EOL;\n");

        $commands = [
            'xtrace' => sprintf('./bin/xtrace -- %s %s', escapeshellarg($fake), escapeshellarg($script)),
            'xstep' => sprintf('./bin/xstep --break=%s -- %s %s', escapeshellarg($script . ':3'), escapeshellarg($fake), escapeshellarg($script)),
            'xback' => sprintf('./bin/xback --break=%s --php=%s -- php %s', escapeshellarg($script . ':3'), escapeshellarg($fake), escapeshellarg($script)),
            'xcoverage' => sprintf('./bin/xcoverage --raw --php=%s -- php %s', escapeshellarg($fake), escapeshellarg($script)),
        ];

        try {
            foreach ($commands as $tool => $command) {
                $output = shell_exec(sprintf('cd %s && %s 2>&1; printf "EXIT:%%d" "$?"', escapeshellarg(dirname(__DIR__, 2)), $command));
                $this->assertNotNull($output);
                $this->assertStringEndsWith('EXIT:1', $output, $tool . ' must exit 1');
                $this->assertStringContainsString('not loadable in PHP 4.9', $output, $tool . ' must name the target version');
                $this->assertStringNotContainsString('"$schema"', $output, $tool . ' must not emit a result document');
                $this->assertStringNotContainsString('Uncaught', $output, $tool . ' must not surface a fatal');
            }
        } finally {
            unlink($script);
            unlink($fake);
            rmdir($dir);
        }
    }

    /**
     * Regression test: `--depth` must bound the returned frame count up to
     * the actual call-stack depth, not be silently capped by DebugServer's
     * internal STACK_CONTEXT_LIMIT (5) before xback's own slicing runs.
     * fibonacci(5)'s first hit of the base-case return (line 9, `if ($n <= 1)`
     * true) is 6 frames deep (fibonacci x5 + {main}), so --depth=3 truncates
     * to 3 and --depth=20 returns all 6.
     */
    public function testXbackDepthBoundsFramesUpToActualStackDepth(): void
    {
        if (! XdebugFinder::isXdebugAvailable()) {
            $this->markTestSkipped('Xdebug not available');
        }

        $fixture = dirname(__DIR__) . '/fixtures/debug_friendly.php';

        $shallowOutput = shell_exec(sprintf(
            'cd %s && ./bin/xback --break=%s --depth=3 -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':9'),
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($shallowOutput);
        $jsonStart = strrpos($shallowOutput, '{"$schema"');
        $this->assertNotFalse($jsonStart, $shallowOutput);
        $shallow = json_decode(substr($shallowOutput, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(3, $shallow['stack']);

        $deepOutput = shell_exec(sprintf(
            'cd %s && ./bin/xback --break=%s --depth=20 -- php %s 2>&1',
            escapeshellarg(dirname(__DIR__, 2)),
            escapeshellarg($fixture . ':9'),
            escapeshellarg($fixture),
        ));
        $this->assertNotNull($deepOutput);
        $jsonStart = strrpos($deepOutput, '{"$schema"');
        $this->assertNotFalse($jsonStart, $deepOutput);
        $deep = json_decode(substr($deepOutput, $jsonStart), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(6, $deep['stack']);
        $this->assertSame('{main}', $deep['stack'][5]['function']);
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
