<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration\Cli;

use PHPUnit\Framework\TestCase;

use function exec;
use function implode;

class XcompareCliTest extends TestCase
{
    public function testCliShowsHelpWithNoArgs(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare 2>&1', $output, $exitCode);

        $firstLine = $output[0] ?? '';
        $this->assertStringContainsString('Usage: xcompare', $firstLine);
    }

    public function testCliShowsHelpWithHelpFlag(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare --help 2>&1', $output, $exitCode);

        $joined = implode("\n", $output);
        $this->assertStringContainsString('--break=FILE:LINE', $joined);
        $this->assertStringContainsString('--run-a=', $joined);
        $this->assertStringContainsString('--run-b=', $joined);
    }

    public function testCliErrorWithoutBreak(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare --run-a="php a.php" --run-b="php b.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--break=FILE:LINE is required', $joined);
    }

    public function testCliErrorWithoutRunA(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare --break=test.php:10 --run-b="php b.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--run-a=', $joined);
    }

    public function testCliErrorWithoutRunB(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare --break=test.php:10 --run-a="php a.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--run-b=', $joined);
    }

    public function testCliErrorMixingModes(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare --break=test.php:10 --run-a="php a.php" --run="php x.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('Cannot mix', $joined);
    }

    public function testCliErrorCompareWithWithoutRun(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare --break=test.php:10 --compare-with=main 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--run=', $joined);
    }

    public function testCliErrorRunWithoutCompareWith(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare --break=test.php:10 --run="php a.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--compare-with=', $joined);
    }

    public function testCliHelpShowsCompareWithMode(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../../bin/xcompare --help 2>&1', $output, $exitCode);

        $joined = implode("\n", $output);
        $this->assertStringContainsString('--compare-with=', $joined);
        $this->assertStringContainsString('--run=', $joined);
        $this->assertStringContainsString('MODE 2:', $joined);
    }
}
