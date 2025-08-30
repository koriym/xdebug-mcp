<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;

use function dirname;
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
        $this->projectRoot = dirname(__DIR__, 2);
        $this->testScript = $this->projectRoot . '/tests/fake/loop-counter.php';
    }

    public function testXdebugProfileWithContext(): void
    {
        $context = 'Test context for profile';
        $command = sprintf(
            '%s/bin/xdebug-profile --json --context="%s" -- php %s 2>/dev/null',
            $this->projectRoot,
            $context,
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        // Validate JSON output with context
        $this->assertStringContainsString('"🎯 analysis_context":', $output);
        $this->assertStringContainsString('"' . $context . '"', $output);
    }

    public function testXdebugCoverageWithContext(): void
    {
        $this->markTestSkipped('Coverage context test temporarily disabled due to PHPUnit interaction issues');

        $context = 'Test context for coverage';
        $command = sprintf(
            '%s/bin/xdebug-coverage --context="%s" -- php %s 2>/dev/null',
            $this->projectRoot,
            $context,
            $this->testScript,
        );
        $output = shell_exec($command);
        $this->assertNotNull($output);
        $this->assertSzringContainsString($context, $output);
    }

    public function testXdebugTraceWithContext(): void
    {
        $context = 'Test context for trace';
        $command = sprintf(
            '%s/bin/xdebug-trace --json --context="%s" -- php %s 2>/dev/null',
            $this->projectRoot,
            $context,
            $this->testScript,
        );

        $output = shell_exec($command);
        $this->assertNotNull($output);

        // Validate JSON output with context
        $this->assertStringContainsString('"analysis_context":', $output);
        $this->assertStringContainsString('"' . $context . '"', $output);
    }

    public function testXdebugDebugWithContext(): void
    {
        $context = 'Test context for debug';
        $command = sprintf(
            '%s/bin/xdebug-debug --exit-on-break --context="%s" -- php %s 2>/dev/null',
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

    public function testContextOptionInHelpText(): void
    {
        $tools = ['xdebug-profile', 'xdebug-coverage', 'xdebug-trace', 'xdebug-debug'];

        foreach ($tools as $tool) {
            $command = sprintf('%s/bin/%s --help 2>&1', $this->projectRoot, $tool);
            $output = shell_exec($command);

            $this->assertNotNull($output);
            $this->assertStringContainsString('--context', $output, "Tool {$tool} should have --context option in help");
        }
    }
}
