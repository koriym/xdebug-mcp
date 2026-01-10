<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function dirname;
use function extension_loaded;
use function shell_exec;
use function sprintf;

/**
 * Test context option functionality across all xdebug tools
 */
class ContextOptionTest extends TestCase
{
    private string $projectRoot;
    private string $testScript;

    protected function setUp(): void
    {
        if (! extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension is not loaded');
        }

        $this->projectRoot = dirname(__DIR__, 2);
        $this->testScript = $this->projectRoot . '/tests/fake/loop-counter.php';
    }

    public function testXdebugProfileWithContext(): void
    {
        $context = 'Test context for profile';
        $command = sprintf(
            '%s/bin/xprofile --json --context="%s" -- php %s 2>/dev/null',
            $this->projectRoot,
            $context,
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        // Validate JSON output with context
        $this->assertStringContainsString('"context":', $output);
        $this->assertStringContainsString('"' . $context . '"', $output);
    }

    public function testXdebugTraceWithContext(): void
    {
        $context = 'Test context for trace';
        $command = sprintf(
            '%s/bin/xtrace --json --context="%s" -- php %s 2>/dev/null',
            $this->projectRoot,
            $context,
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        // Validate JSON output with context
        $this->assertStringContainsString('"context":', $output);
        $this->assertStringContainsString('"' . $context . '"', $output);
    }

    public function testXdebugDebugWithContext(): void
    {
        $context = 'Test context for debug';
        $command = sprintf(
            '%s/bin/xstep --exit-on-break --context="%s" -- php %s 2>/dev/null',
            $this->projectRoot,
            $context,
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        // Validate JSON output with context
        $this->assertStringContainsString('"context":', $output);
        $this->assertStringContainsString('"' . $context . '"', $output);
    }
}
