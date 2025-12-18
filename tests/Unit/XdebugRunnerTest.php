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
use function strpos;
use function sys_get_temp_dir;
use function tempnam;
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
        $runner = new XdebugRunner(['script', '--', 'php', 'test.php']);
        $runner->setOutputDir('/custom/output/dir');

        $command = $runner->buildCommand();

        $this->assertStringContainsString('-dxdebug.output_dir=/custom/output/dir', $command);
    }

    #[Test]
    public function getLatestTraceFileReturnsNullWhenNoFiles(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php', 'test.php']);
        $runner->setOutputDir('/nonexistent/directory');

        $this->assertNull($runner->getLatestTraceFile());
    }

    #[Test]
    public function getLatestProfileFileReturnsNullWhenNoFiles(): void
    {
        $runner = new XdebugRunner(['script', '--', 'php', 'test.php']);
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
        // prepend_filter.php is loaded when includeVendor is set
        if (file_exists(__DIR__ . '/../../src/prepend_filter.php')) {
            $this->assertStringContainsString('-dauto_prepend_file=', $command);
        }
    }

    #[Test]
    public function buildsCommandWithoutIncludeVendor(): void
    {
        $runner = new XdebugRunner(['script', '--', __FILE__]);
        $runner->setMode('trace');
        // Don't set includeVendor

        $command = $runner->buildCommand();

        $this->assertStringContainsString('-dxdebug.mode=trace', $command);
        $this->assertStringNotContainsString('-dauto_prepend_file=', $command);
    }
}
