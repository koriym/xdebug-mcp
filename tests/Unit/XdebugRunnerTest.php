<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\XdebugRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_exists;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function strpos;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

#[CoversClass(XdebugRunner::class)]
final class XdebugRunnerTest extends TestCase
{
    #[Test]
    public function parseArgumentsRequiresSeparator(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing -- separator');

        new XdebugRunner(['script', 'php', 'test.php']);
    }

    #[Test]
    public function parseArgumentsRequiresCommandAfterSeparator(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Command is required after --');

        new XdebugRunner(['script', '--']);
    }

    #[Test]
    public function parsesLocalPhpCommand(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php', 'test.php']);

        $this->assertFalse($runner->isDockerCommand($runner->getCommandParts()));
        $this->assertSame(['php', 'test.php'], $runner->getCommandParts());
    }

    #[Test]
    public function detectsDockerCommand(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            'docker',
            'compose',
            'run',
            '--rm',
            'php',
            'php',
            '/app/test.php',
        ]);

        $this->assertTrue($runner->isDockerCommand($runner->getCommandParts()));
    }

    #[Test]
    public function detectsPodmanCommand(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            'podman',
            'run',
            '--rm',
            'php:8.4',
            'php',
            '/app/test.php',
        ]);

        $this->assertTrue($runner->isDockerCommand($runner->getCommandParts()));
    }

    #[Test]
    public function detectsKubectlCommand(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            'kubectl',
            'exec',
            '-it',
            'pod-name',
            '--',
            'php',
            '/app/test.php',
        ]);

        $this->assertTrue($runner->isDockerCommand($runner->getCommandParts()));
    }

    #[Test]
    public function findPhpCommandIndexInDockerCompose(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            'docker',
            'compose',
            'run',
            '--rm',
            'php',
            'php',
            '/app/test.php',
        ]);

        $parts = $runner->getCommandParts();
        // Parts after '--': docker(0), compose(1), run(2), --rm(3), php(4), php(5), /app/test.php(6)
        // We want the LAST 'php' which is at index 5
        $this->assertSame(5, $runner->findPhpCommandIndex($parts));
    }

    #[Test]
    public function findPhpCommandIndexWithVersionedPhp(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            'docker',
            'run',
            '--rm',
            'myimage',
            'php8.4',
            '/app/test.php',
        ]);

        $parts = $runner->getCommandParts();
        // Parts: docker(0), run(1), --rm(2), myimage(3), php8.4(4), /app/test.php(5)
        $this->assertSame(4, $runner->findPhpCommandIndex($parts));
    }

    #[Test]
    public function findPhpCommandIndexReturnsLastMatch(): void
    {
        // Important: when 'php' appears multiple times (service name + command),
        // we want the LAST occurrence
        $runner = new XdebugRunner([
            'script',
            '--',
            'docker',
            'compose',
            'exec',
            'php',
            'php',
            '/app/test.php',
        ]);

        $parts = $runner->getCommandParts();
        // Parts: docker(0), compose(1), exec(2), php(3), php(4), /app/test.php(5)
        // Index 3 is 'php' service, index 4 is 'php' command - we want 4
        $this->assertSame(4, $runner->findPhpCommandIndex($parts));
    }

    #[Test]
    public function findPhpCommandIndexReturnsFalseWhenNotFound(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            'docker',
            'run',
            '--rm',
            'myimage',
            'python',
            '/app/test.py',
        ]);

        $parts = $runner->getCommandParts();
        $this->assertFalse($runner->findPhpCommandIndex($parts));
    }

    #[Test]
    public function setAndGetMode(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php', 'test.php']);

        $runner->setMode('profile');
        $this->assertSame('profile', $runner->getMode());

        $runner->setMode('coverage');
        $this->assertSame('coverage', $runner->getMode());
    }

    #[Test]
    public function setAndGetContext(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php', 'test.php']);

        $this->assertNull($runner->getContext());

        $runner->setContext('Testing login functionality');
        $this->assertSame('Testing login functionality', $runner->getContext());
    }

    #[Test]
    public function setAndGetIncludeVendor(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php', 'test.php']);

        $this->assertNull($runner->getIncludeVendor());

        $runner->setIncludeVendor('symfony/*,doctrine/*');
        $this->assertSame('symfony/*,doctrine/*', $runner->getIncludeVendor());
    }

    #[Test]
    public function buildsLocalCommandWithXdebugArgs(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setMode('trace');
        $runner->setOutputDir('/tmp');

        $command = $runner->buildCommand();

        $this->assertStringContainsString('php', $command);
        $this->assertStringContainsString('-dxdebug.mode=trace', $command);
        $this->assertStringContainsString('-dxdebug.start_with_request=yes', $command);
        $this->assertStringContainsString('-dxdebug.output_dir=/tmp', $command);
        $this->assertStringContainsString('-dxdebug.use_compression=0', $command);
        $this->assertStringContainsString('-dxdebug.trace_format=1', $command);
    }

    #[Test]
    public function buildsProfileCommandWithCorrectOptions(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setMode('profile');

        $command = $runner->buildCommand();

        $this->assertStringContainsString('-dxdebug.mode=profile', $command);
        $this->assertStringContainsString('-dxdebug.profiler_output_name=cachegrind.out.%p', $command);
    }

    #[Test]
    public function buildsDockerCommandWithXdebugArgsInjected(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            'docker',
            'compose',
            'run',
            '--rm',
            'php',
            'php',
            '/app/test.php',
        ]);
        $runner->setMode('trace');

        $command = $runner->buildCommand();

        // Xdebug args should be injected after 'php' command
        $this->assertStringContainsString('docker compose run --rm php php', $command);
        $this->assertStringContainsString('-dxdebug.mode=trace', $command);

        // Verify order: 'php' command should come before xdebug args
        $phpPos = strpos($command, 'php php');
        $xdebugPos = strpos($command, '-dxdebug.mode=trace');
        $this->assertNotFalse($phpPos);
        $this->assertNotFalse($xdebugPos);
        $this->assertLessThan($xdebugPos, $phpPos);
    }

    #[Test]
    public function throwsExceptionWhenPhpNotFoundInDockerCommand(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            'docker',
            'run',
            '--rm',
            'python:3.11',
            'python',
            '/app/test.py',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHP command not found in Docker command');

        $runner->buildCommand();
    }

    #[Test]
    public function fluentInterface(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);

        $result = $runner
            ->setMode('profile')
            ->setContext('Test context')
            ->setIncludeVendor('vendor/*')
            ->setXdebugOptions(['-dmemory_limit=512M'])
            ->setOutputDir('/var/tmp');

        $this->assertSame($runner, $result);
    }

    #[Test]
    #[DataProvider('containerCommandProvider')]
    public function detectsVariousContainerCommands(array $argv, bool $expected): void
    {
        $runner = new XdebugRunner($argv);

        $this->assertSame($expected, $runner->isDockerCommand($runner->getCommandParts()));
    }

    /** @return array<string, array{array<string>, bool}> */
    public static function containerCommandProvider(): array
    {
        return [
            'docker run' => [
                ['script', '--', 'docker', 'run', '--rm', 'php:8.4', 'php', 'test.php'],
                true,
            ],
            'docker compose run' => [
                ['script', '--', 'docker', 'compose', 'run', '--rm', 'php', 'php', 'test.php'],
                true,
            ],
            'docker compose exec' => [
                ['script', '--', 'docker', 'compose', 'exec', '-T', 'php', 'php', 'test.php'],
                true,
            ],
            'podman run' => [
                ['script', '--', 'podman', 'run', '--rm', 'php:8.4', 'php', 'test.php'],
                true,
            ],
            'kubectl exec' => [
                ['script', '--', 'kubectl', 'exec', '-it', 'pod', '--', 'php', 'test.php'],
                true,
            ],
            'local php' => [
                ['script', '--', 'php', 'test.php'],
                false,
            ],
            'local script only' => [
                ['script', '--', 'test.php'],
                false,
            ],
        ];
    }

    #[Test]
    public function handlesMultipleOptionsBeforeSeparator(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--json',
            '--context=Test',
            '--include-vendor=*',
            '--',
            'php',
            'test.php',
        ]);

        $this->assertSame(['php', 'test.php'], $runner->getCommandParts());
    }

    #[Test]
    public function validateLocalFileThrowsForNonExistentFile(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php', '/nonexistent/file.php']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $runner->buildCommand();
    }

    #[Test]
    public function validateLocalFileAcceptsExistingFile(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php', __FILE__]);

        $command = $runner->buildCommand();

        $this->assertStringContainsString(__FILE__, $command);
    }

    #[Test]
    public function validateLocalFileWithoutPhpPrefix(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);

        $command = $runner->buildCommand();

        $this->assertStringContainsString('php', $command);
        $this->assertStringContainsString(__FILE__, $command);
    }

    #[Test]
    public function appliesCustomXdebugOptions(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setXdebugOptions(['-dmemory_limit=512M', '-dmax_execution_time=300']);

        $command = $runner->buildCommand();

        $this->assertStringContainsString('-dmemory_limit=512M', $command);
        $this->assertStringContainsString('-dmax_execution_time=300', $command);
    }

    #[Test]
    public function setAndGetOutputDir(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setOutputDir('/custom/output/dir');

        $command = $runner->buildCommand();

        $this->assertStringContainsString('-dxdebug.output_dir=/custom/output/dir', $command);
    }

    #[Test]
    public function getLatestTraceFileReturnsNullWhenNoFiles(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setOutputDir('/nonexistent/directory');

        $this->assertNull($runner->getLatestTraceFile());
    }

    #[Test]
    public function getLatestProfileFileReturnsNullWhenNoFiles(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setOutputDir('/nonexistent/directory');

        $this->assertNull($runner->getLatestProfileFile());
    }

    #[Test]
    public function runExecutesCommandSuccessfully(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php exit(0);');

        $runner = new XdebugRunner(['script', '--', 'php', $tempFile]);
        $exitCode = $runner->run();

        $this->assertSame(0, $exitCode);

        unlink($tempFile);
    }

    #[Test]
    public function buildsTraceCommandWithIncludeVendor(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setMode('trace');
        $runner->setIncludeVendor('symfony/*,doctrine/*');

        $command = $runner->buildCommand();

        $this->assertStringContainsString('-dxdebug.mode=trace', $command);
        $this->assertStringContainsString('XDEBUG_MCP_INCLUDE_VENDOR=', $command);
        // prepend_filter.php is loaded for local traces so vendor filtering can
        // exclude all vendor code by default or include selected packages.
        if (! file_exists(__DIR__ . '/../../src/prepend_filter.php')) {
            return;
        }

        $this->assertStringContainsString('-dauto_prepend_file=', $command);
    }

    #[Test]
    public function buildsCommandWithoutIncludeVendor(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setMode('trace');
        // Don't set includeVendor

        $command = $runner->buildCommand();

        $this->assertStringContainsString('-dxdebug.mode=trace', $command);
        $this->assertStringContainsString('-dauto_prepend_file=', $command);
        $this->assertStringNotContainsString('XDEBUG_MCP_INCLUDE_VENDOR=', $command);
    }

    #[Test]
    public function getLatestTraceFileReturnsFileWhenExists(): void
    {
        $tempDir = sys_get_temp_dir() . '/xdebug_test_' . uniqid();
        mkdir($tempDir);

        $traceFile = $tempDir . '/trace.12345.xt';
        file_put_contents($traceFile, 'trace content');

        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setOutputDir($tempDir);

        $result = $runner->getLatestTraceFile();

        $this->assertSame($traceFile, $result);

        unlink($traceFile);
        rmdir($tempDir);
    }

    #[Test]
    public function getLatestProfileFileReturnsFileWhenExists(): void
    {
        $tempDir = sys_get_temp_dir() . '/xdebug_test_' . uniqid();
        mkdir($tempDir);

        $profileFile = $tempDir . '/cachegrind.out.12345';
        file_put_contents($profileFile, 'profile content');

        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setOutputDir($tempDir);

        $result = $runner->getLatestProfileFile();

        $this->assertSame($profileFile, $result);

        unlink($profileFile);
        rmdir($tempDir);
    }

    #[Test]
    #[DataProvider('phpBinaryProvider')]
    public function isPhpBinaryDetectsVariousPhpPaths(string $path, bool $expected): void
    {
        $this->assertSame($expected, XdebugRunner::isPhpBinary($path));
    }

    /** @return array<string, array{string, bool}> */
    public static function phpBinaryProvider(): array
    {
        return [
            // Valid PHP binaries (Unix)
            'simple php' => ['php', true],
            'php with major version' => ['php8', true],
            'php with version' => ['php8.3', true],
            'php with full version' => ['php8.3.12', true],
            'php with dash version' => ['php-8.3', true],
            'php with at version' => ['php@8.3', true],
            'php with double digit version' => ['php83', true],
            'absolute path php' => ['/usr/bin/php', true],
            'absolute path versioned php' => ['/usr/bin/php8.3', true],
            'homebrew php path' => ['/opt/homebrew/opt/php@8.3/bin/php', true],
            'custom path php' => ['/custom/path/to/php', true],
            'versioned in path' => ['/usr/local/bin/php8', true],

            // Valid PHP binaries (Windows)
            'windows php.exe' => ['php.exe', true],
            'windows path php.exe' => ['C:\\php\\php.exe', true],
            'windows program files php' => ['C:\\Program Files\\php\\php.exe', true],
            'windows versioned php' => ['C:\\php8.3\\php.exe', true],
            'windows php8.exe' => ['php8.exe', true],
            'windows php83.exe' => ['php83.exe', true],
            'unix php.exe' => ['/usr/bin/php.exe', true],

            // Invalid - not PHP binaries
            'phpunit' => ['phpunit', false],
            'phpcbf' => ['phpcbf', false],
            'phpcs' => ['phpcs', false],
            'phpstan' => ['phpstan', false],
            'php script file' => ['script.php', false],
            'path to php script' => ['/path/to/script.php', false],
            'vendor phpunit' => ['vendor/bin/phpunit', false],
            'not php at all' => ['python', false],
            'empty string' => ['', false],

            // Invalid - server SAPIs (intentionally excluded)
            'php-fpm' => ['php-fpm', false],
            'php-cgi' => ['php-cgi', false],
            'php-fpm with path' => ['/usr/sbin/php-fpm', false],
            'php-cgi with path' => ['/usr/bin/php-cgi', false],
            'php8.3-fpm' => ['php8.3-fpm', false],
        ];
    }

    #[Test]
    public function buildsCommandWithFullPathPhpBinary(): void
    {
        $runner = new XdebugRunner(['script', '--', '/usr/bin/php', __FILE__]);
        $runner->setMode('trace');

        $command = $runner->buildCommand();

        // Should use the specified PHP binary path
        $this->assertStringContainsString('/usr/bin/php', $command);
        $this->assertStringContainsString('-dxdebug.mode=trace', $command);
        $this->assertStringContainsString(__FILE__, $command);
    }

    #[Test]
    public function buildsCommandWithVersionedPhpBinary(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php8.3', __FILE__]);
        $runner->setMode('profile');

        $command = $runner->buildCommand();

        // Should use the specified versioned PHP binary
        $this->assertStringContainsString('php8.3', $command);
        $this->assertStringContainsString('-dxdebug.mode=profile', $command);
    }

    #[Test]
    public function buildsCommandWithHomebrewPhpPath(): void
    {
        $runner = new XdebugRunner([
            'script',
            '--',
            '/opt/homebrew/opt/php@8.3/bin/php',
            __FILE__,
        ]);
        $runner->setMode('trace');

        $command = $runner->buildCommand();

        // Should use the Homebrew PHP path, properly escaped
        $this->assertStringContainsString('/opt/homebrew/opt/php@8.3/bin/php', $command);
        $this->assertStringContainsString('-dxdebug.mode=trace', $command);
        // Should NOT have 'php' prepended
        $this->assertStringNotContainsString("'php' ", $command);
    }

    #[Test]
    public function buildsCommandWithPhpAtVersionPath(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php@8.3', __FILE__]);
        $runner->setMode('coverage');

        $command = $runner->buildCommand();

        $this->assertStringContainsString('php@8.3', $command);
        $this->assertStringContainsString('-dxdebug.mode=coverage', $command);
    }

    #[Test]
    public function validateLocalFileWithFullPathPhpBinary(): void
    {
        // Should not throw when using full path PHP binary
        $runner = new XdebugRunner(['script', '--', '/usr/bin/php', __FILE__]);

        $command = $runner->buildCommand();

        $this->assertStringContainsString(__FILE__, $command);
    }
}
