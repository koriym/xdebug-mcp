<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use FilesystemIterator;
use Koriym\XdebugMcp\CompareRunner;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

use function chdir;
use function clearstatcache;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function getcwd;
use function implode;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function unlink;
use function var_export;

use const PHP_BINARY;

/**
 * Exercises the real `git worktree` lifecycle in --compare-with mode.
 */
class CompareRunnerWorktreeTest extends TestCase
{
    public function testCreateWorktreeCreatesAndCleanupRemovesIt(): void
    {
        $originalCwd = $this->currentWorkingDirectory();
        $repoDir = $this->createTemporaryRepository();
        $runner = $this->newRunner();

        try {
            chdir($repoDir);
            $path = $this->invoke($runner, 'createWorktree', ['HEAD']);
            $this->assertIsString($path);
            $this->assertDirectoryExists($path);

            $this->invoke($runner, 'cleanupWorktree', []);
            clearstatcache(true, $path);
            $this->assertDirectoryDoesNotExist($path);
        } finally {
            chdir($originalCwd);
            $this->removeDirectory($repoDir);
        }
    }

    public function testCreateWorktreeUsesDetachedCheckoutForAlreadyCheckedOutBranch(): void
    {
        $originalCwd = $this->currentWorkingDirectory();
        $repoDir = $this->createTemporaryRepository();
        $worktreePath = null;
        $runner = $this->newRunner();

        try {
            chdir($repoDir);
            $worktreePath = $this->invoke($runner, 'createWorktree', ['main']);
            $this->assertIsString($worktreePath);
            $this->assertDirectoryExists($worktreePath);

            $output = [];
            $exit = 0;
            exec('git -C ' . escapeshellarg($worktreePath) . ' symbolic-ref -q HEAD 2>&1', $output, $exit);
            $this->assertNotSame(0, $exit, 'comparison worktree should be detached');
        } finally {
            if ($worktreePath !== null) {
                $this->invoke($runner, 'cleanupWorktree', []);
            }

            chdir($originalCwd);
            $this->removeDirectory($repoDir);
        }
    }

    public function testCreateWorktreeFailsForUnknownRef(): void
    {
        $originalCwd = $this->currentWorkingDirectory();
        $repoDir = $this->createTemporaryRepository();
        $runner = $this->newRunner();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create worktree for ref');

        try {
            chdir($repoDir);
            try {
                $this->invoke($runner, 'createWorktree', ['definitely-not-a-real-ref-xcompare']);
            } finally {
                // Harmless if createWorktree() failed before registering a worktree.
                $this->invoke($runner, 'cleanupWorktree', []);
            }
        } finally {
            chdir($originalCwd);
            $this->removeDirectory($repoDir);
        }
    }

    /**
     * Regression test for F5: register_shutdown_function only fires at process
     * end, so this runs in a child process. The child creates a worktree,
     * prints its path, then hits a fatal error (run()'s try/finally never
     * executes) — the shutdown handler registered in createWorktree() must
     * still remove the worktree instead of leaking it.
     */
    public function testWorktreeIsRemovedOnFatalErrorViaShutdownHandler(): void
    {
        $originalCwd = $this->currentWorkingDirectory();
        $repoDir = $this->createTemporaryRepository();
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $childScript = sys_get_temp_dir() . '/xcompare-fatal-' . uniqid('', true) . '.php';

        $code = "<?php\n"
            . 'require ' . var_export($autoload, true) . ";\n"
            . 'chdir(' . var_export($repoDir, true) . ");\n"
            . '$runner = new \\Koriym\\XdebugMcp\\CompareRunner('
            . "['break' => 'x:1', 'run' => 'php -v', 'compare_with' => 'HEAD']);\n"
            . '$method = (new \\ReflectionClass($runner))->getMethod(' . "'createWorktree');\n"
            . '$path = $method->invoke($runner, ' . "'HEAD');\n"
            . "echo \$path;\n"
            . "xcompare_trigger_fatal_undefined_function();\n";
        file_put_contents($childScript, $code);

        try {
            $output = [];
            $exit = 0;
            exec(
                escapeshellarg(PHP_BINARY) . ' -d display_errors=0 '
                . escapeshellarg($childScript) . ' 2>/dev/null',
                $output,
                $exit,
            );
            $worktreePath = trim(implode('', $output));

            $this->assertNotSame('', $worktreePath, 'child should print the worktree path before the fatal error');
            $this->assertNotSame(0, $exit, 'child must exit non-zero via the fatal error this test exercises');
            clearstatcache(true, $worktreePath);
            $this->assertDirectoryDoesNotExist(
                $worktreePath,
                'shutdown handler must remove the worktree even when a fatal error skips run()\'s finally',
            );
        } finally {
            unlink($childScript);
            chdir($originalCwd);
            $this->removeDirectory($repoDir);
        }
    }

    private function newRunner(): CompareRunner
    {
        return new CompareRunner([
            'break' => 'test.php:1',
            'run' => 'php -v',
            'compare_with' => 'HEAD',
        ]);
    }

    /** @param array<int, mixed> $args */
    private function invoke(CompareRunner $runner, string $method, array $args): mixed
    {
        $ref = new ReflectionClass($runner);
        $m = $ref->getMethod($method);

        return $m->invokeArgs($runner, $args);
    }

    private function runCommand(string $command): void
    {
        $output = [];
        $exit = 0;
        exec($command . ' 2>&1', $output, $exit);

        $this->assertSame(0, $exit, implode("\n", $output));
    }

    private function currentWorkingDirectory(): string
    {
        $cwd = getcwd();
        $this->assertIsString($cwd);

        return $cwd;
    }

    private function createTemporaryRepository(): string
    {
        $repoDir = sys_get_temp_dir() . '/xcompare-repo-' . uniqid('', true);
        mkdir($repoDir);

        $this->runCommand('git -C ' . escapeshellarg($repoDir) . ' init');
        file_put_contents($repoDir . '/fixture.txt', "fixture\n");
        $this->runCommand('git -C ' . escapeshellarg($repoDir) . ' add fixture.txt');
        $this->runCommand(
            'git -C ' . escapeshellarg($repoDir)
            . ' -c user.email=xcompare@example.com -c user.name=xcompare commit -m init',
        );
        $this->runCommand('git -C ' . escapeshellarg($repoDir) . ' branch -M main');

        return $repoDir;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $pathname = $item->getPathname();
            if ($item->isDir() && ! $item->isLink()) {
                rmdir($pathname);
                continue;
            }

            unlink($pathname);
        }

        rmdir($path);
    }
}
