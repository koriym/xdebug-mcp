<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use Koriym\XdebugMcp\CompareRunner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

use function clearstatcache;
use function exec;
use function trim;

/**
 * Exercises the real `git worktree` lifecycle in --compare-with mode.
 *
 * Skipped when the test run is not inside a git work tree (e.g. a tarball
 * install), since the whole code path only ever runs inside one.
 */
class CompareRunnerWorktreeTest extends TestCase
{
    protected function setUp(): void
    {
        $output = [];
        $exit = 0;
        exec('git rev-parse --is-inside-work-tree 2>/dev/null', $output, $exit);
        $insideWorkTree = $exit === 0 && trim($output[0] ?? '') === 'true';
        if ($insideWorkTree) {
            return;
        }

        $this->markTestSkipped('not inside a git work tree');
    }

    public function testCreateWorktreeCreatesAndCleanupRemovesIt(): void
    {
        $runner = $this->newRunner();

        $path = $this->invoke($runner, 'createWorktree', ['HEAD']);
        $this->assertIsString($path);
        $this->assertDirectoryExists($path);

        // cleanupWorktree() keys off the private $worktreePath property, which is
        // normally set by resolveCompareWithMode(). Set it directly here so we can
        // exercise cleanup in isolation without running a full xstep.
        $ref = new ReflectionClass($runner);
        $prop = $ref->getProperty('worktreePath');
        $prop->setValue($runner, $path);

        $this->invoke($runner, 'cleanupWorktree', []);
        clearstatcache(true, $path);
        $this->assertDirectoryDoesNotExist($path);
    }

    public function testCreateWorktreeFailsForUnknownRef(): void
    {
        $runner = $this->newRunner();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create worktree for ref');

        try {
            $this->invoke($runner, 'createWorktree', ['definitely-not-a-real-ref-xcompare']);
        } finally {
            // Defensive: if git somehow left a dir behind, clean up.
            $this->invoke($runner, 'cleanupWorktree', []);
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
}
