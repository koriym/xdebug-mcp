<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\CompareRunner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

use function array_merge;
use function exec;
use function implode;
use function json_decode;
use function ob_get_clean;
use function ob_start;

class CompareRunnerTest extends TestCase
{
    private function invokeMethod(CompareRunner $runner, string $method, array $args = []): mixed
    {
        $ref = new ReflectionClass($runner);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($runner, $args);
    }

    private function createRunner(): CompareRunner
    {
        return new CompareRunner([
            'break' => 'test.php:10',
            'run_a' => 'php test.php 1',
            'run_b' => 'php test.php 2',
        ]);
    }

    private function createFakeRunner(array $options = []): FakeCompareRunner
    {
        return new FakeCompareRunner(array_merge([
            'break' => 'test.php:10',
            'run_a' => 'php test.php 1',
            'run_b' => 'php test.php 2',
        ], $options));
    }

    public function testComputeDiffWithChangedVariables(): void
    {
        $runner = $this->createRunner();

        $varsA = ['$x' => 'int: 10', '$y' => 'int: 20', '$name' => 'string: hello'];
        $varsB = ['$x' => 'int: 99', '$y' => 'int: 20', '$name' => 'string: world'];

        $diff = $this->invokeMethod($runner, 'computeDiff', [$varsA, $varsB]);

        $this->assertSame(['a' => 'int: 10', 'b' => 'int: 99'], $diff['changed']['$x']);
        $this->assertSame(['a' => 'string: hello', 'b' => 'string: world'], $diff['changed']['$name']);
        $this->assertSame(['$y'], $diff['unchanged']);
        $this->assertSame([], $diff['only_in_a']);
        $this->assertSame([], $diff['only_in_b']);
    }

    public function testComputeDiffWithOnlyInA(): void
    {
        $runner = $this->createRunner();

        $varsA = ['$x' => 'int: 10', '$extra' => 'string: only_a'];
        $varsB = ['$x' => 'int: 10'];

        $diff = $this->invokeMethod($runner, 'computeDiff', [$varsA, $varsB]);

        $this->assertSame([], $diff['changed']);
        $this->assertSame(['$x'], $diff['unchanged']);
        $this->assertSame(['$extra'], $diff['only_in_a']);
        $this->assertSame([], $diff['only_in_b']);
    }

    public function testComputeDiffWithOnlyInB(): void
    {
        $runner = $this->createRunner();

        $varsA = ['$x' => 'int: 10'];
        $varsB = ['$x' => 'int: 10', '$new_var' => 'string: only_b'];

        $diff = $this->invokeMethod($runner, 'computeDiff', [$varsA, $varsB]);

        $this->assertSame([], $diff['changed']);
        $this->assertSame(['$x'], $diff['unchanged']);
        $this->assertSame([], $diff['only_in_a']);
        $this->assertSame(['$new_var'], $diff['only_in_b']);
    }

    public function testComputeDiffWithEmptyVariables(): void
    {
        $runner = $this->createRunner();

        $diff = $this->invokeMethod($runner, 'computeDiff', [[], []]);

        $this->assertSame([], $diff['changed']);
        $this->assertSame([], $diff['unchanged']);
        $this->assertSame([], $diff['only_in_a']);
        $this->assertSame([], $diff['only_in_b']);
    }

    public function testComputeDiffWithCompletelyDifferentVariables(): void
    {
        $runner = $this->createRunner();

        $varsA = ['$a' => 'int: 1', '$b' => 'int: 2'];
        $varsB = ['$c' => 'int: 3', '$d' => 'int: 4'];

        $diff = $this->invokeMethod($runner, 'computeDiff', [$varsA, $varsB]);

        $this->assertSame([], $diff['changed']);
        $this->assertSame([], $diff['unchanged']);
        $this->assertSame(['$a', '$b'], $diff['only_in_a']);
        $this->assertSame(['$c', '$d'], $diff['only_in_b']);
    }

    public function testComputeDiffWithAllIdenticalVariables(): void
    {
        $runner = $this->createRunner();

        $vars = ['$x' => 'int: 10', '$y' => 'string: hello', '$z' => 'array: [3 items]'];

        $diff = $this->invokeMethod($runner, 'computeDiff', [$vars, $vars]);

        $this->assertSame([], $diff['changed']);
        $this->assertSame(['$x', '$y', '$z'], $diff['unchanged']);
        $this->assertSame([], $diff['only_in_a']);
        $this->assertSame([], $diff['only_in_b']);
    }

    public function testGenerateHints(): void
    {
        $runner = $this->createRunner();

        $diff = [
            'changed' => ['$x' => ['a' => 'int: 10', 'b' => 'int: 99']],
            'unchanged' => ['$y'],
            'only_in_a' => ['$extra'],
            'only_in_b' => [],
        ];
        $varsA = ['$x' => 'int: 10', '$y' => 'int: 20', '$extra' => 'string: test'];
        $varsB = ['$x' => 'int: 99', '$y' => 'int: 20'];

        $hints = $this->invokeMethod($runner, 'generateHints', [$diff, $varsA, $varsB]);

        $this->assertContains('$x: int: 10 → int: 99 (changed)', $hints);
        $this->assertContains('$extra: string: test (only in run_a)', $hints);
        $this->assertContains('1 variable(s) unchanged, 1 variable(s) changed, 3 total', $hints);
    }

    public function testGenerateHintsWithOnlyInB(): void
    {
        $runner = $this->createRunner();

        $diff = [
            'changed' => [],
            'unchanged' => ['$x'],
            'only_in_a' => [],
            'only_in_b' => ['$error'],
        ];
        $varsA = ['$x' => 'int: 10'];
        $varsB = ['$x' => 'int: 10', '$error' => 'string: Division by zero'];

        $hints = $this->invokeMethod($runner, 'generateHints', [$diff, $varsA, $varsB]);

        $this->assertContains('$error: string: Division by zero (only in run_b)', $hints);
        $this->assertContains('1 variable(s) unchanged, 0 variable(s) changed, 2 total', $hints);
    }

    public function testGenerateHintsWithNoVariables(): void
    {
        $runner = $this->createRunner();

        $diff = [
            'changed' => [],
            'unchanged' => [],
            'only_in_a' => [],
            'only_in_b' => [],
        ];

        $hints = $this->invokeMethod($runner, 'generateHints', [$diff, [], []]);

        $this->assertCount(1, $hints);
        $this->assertSame('0 variable(s) unchanged, 0 variable(s) changed, 0 total', $hints[0]);
    }

    public function testParseBreakSpec(): void
    {
        $runner = $this->createRunner();

        $result = $this->invokeMethod($runner, 'parseBreakSpec', ['src/Calculator.php:25']);
        $this->assertSame(['file' => 'src/Calculator.php', 'line' => 25], $result);
    }

    public function testParseBreakSpecWithCondition(): void
    {
        $runner = $this->createRunner();

        $result = $this->invokeMethod($runner, 'parseBreakSpec', ['src/Calculator.php:25:$x>0']);
        $this->assertSame(['file' => 'src/Calculator.php', 'line' => 25], $result);
    }

    public function testParseBreakSpecWithAbsolutePath(): void
    {
        $runner = $this->createRunner();

        $result = $this->invokeMethod($runner, 'parseBreakSpec', ['/home/user/project/src/File.php:100']);
        $this->assertSame(['file' => '/home/user/project/src/File.php', 'line' => 100], $result);
    }

    public function testParseBreakSpecWithInvalidFormat(): void
    {
        $runner = $this->createRunner();

        $result = $this->invokeMethod($runner, 'parseBreakSpec', ['noformat']);
        $this->assertSame(['file' => 'noformat', 'line' => 0], $result);
    }

    public function testExtractVariablesFromResult(): void
    {
        $runner = $this->createRunner();

        $result = [
            'breaks' => [
                [
                    'step' => 1,
                    'location' => ['file' => 'test.php', 'line' => 10],
                    'variables' => ['$x' => 'int: 10', '$y' => 'int: 20'],
                    'recording_type' => 'full',
                ],
            ],
        ];

        $vars = $this->invokeMethod($runner, 'extractVariables', [$result]);
        $this->assertSame(['$x' => 'int: 10', '$y' => 'int: 20'], $vars);
    }

    public function testExtractVariablesFromEmptyResult(): void
    {
        $runner = $this->createRunner();

        $vars = $this->invokeMethod($runner, 'extractVariables', [['breaks' => []]]);
        $this->assertSame([], $vars);
    }

    public function testExtractVariablesFromResultWithMissingBreaksKey(): void
    {
        $runner = $this->createRunner();

        $vars = $this->invokeMethod($runner, 'extractVariables', [[]]);
        $this->assertSame([], $vars);
    }

    public function testExtractVariablesFromBreakWithNoVariablesKey(): void
    {
        $runner = $this->createRunner();

        $result = [
            'breaks' => [
                ['location' => ['file' => 'test.php', 'line' => 10]],
            ],
        ];

        $vars = $this->invokeMethod($runner, 'extractVariables', [$result]);
        $this->assertSame([], $vars);
    }

    public function testExtractLocationFromResult(): void
    {
        $runner = $this->createRunner();

        $result = [
            'breaks' => [
                [
                    'location' => ['file' => 'src/test.php', 'line' => 42],
                    'variables' => [],
                ],
            ],
        ];

        $location = $this->invokeMethod($runner, 'extractLocation', [$result]);
        $this->assertSame(['file' => 'src/test.php', 'line' => 42], $location);
    }

    public function testExtractLocationFromEmptyBreaks(): void
    {
        $runner = $this->createRunner();

        $location = $this->invokeMethod($runner, 'extractLocation', [['breaks' => []]]);
        $this->assertSame(['file' => '', 'line' => 0], $location);
    }

    public function testExtractStatusBreak(): void
    {
        $runner = $this->createRunner();

        $result = [
            'breaks' => [
                ['location' => ['file' => 'test.php', 'line' => 1], 'variables' => []],
            ],
        ];

        $status = $this->invokeMethod($runner, 'extractStatus', [$result]);
        $this->assertSame('break', $status);
    }

    public function testExtractStatusNoBreak(): void
    {
        $runner = $this->createRunner();

        $status = $this->invokeMethod($runner, 'extractStatus', [['breaks' => []]]);
        $this->assertSame('no_break', $status);
    }

    public function testRunProducesCorrectOutputStructure(): void
    {
        $runner = $this->createFakeRunner();
        $runner->setFakeResults([
            'php test.php 1' => [
                'breaks' => [
                    [
                        'step' => 1,
                        'location' => ['file' => 'test.php', 'line' => 10],
                        'variables' => ['$x' => 'int: 1', '$sum' => 'int: 0'],
                        'recording_type' => 'full',
                    ],
                ],
                'trace' => ['file' => '', 'lines' => 0, 'functions' => 0, 'max_depth' => 0, 'db_queries' => 0],
            ],
            'php test.php 2' => [
                'breaks' => [
                    [
                        'step' => 1,
                        'location' => ['file' => 'test.php', 'line' => 10],
                        'variables' => ['$x' => 'int: 2', '$sum' => 'int: 0'],
                        'recording_type' => 'full',
                    ],
                ],
                'trace' => ['file' => '', 'lines' => 0, 'functions' => 0, 'max_depth' => 0, 'db_queries' => 0],
            ],
        ]);

        $result = $runner->run();

        // Schema
        $this->assertSame('https://koriym.github.io/xdebug-mcp/schemas/xcompare.json', $result['$schema']);

        // Breakpoint
        $this->assertSame('test.php', $result['breakpoint']['file']);
        $this->assertSame(10, $result['breakpoint']['line']);

        // Run A
        $this->assertSame('php test.php 1', $result['run_a']['command']);
        $this->assertSame('break', $result['run_a']['status']);
        $this->assertSame(['$x' => 'int: 1', '$sum' => 'int: 0'], $result['run_a']['variables']);

        // Run B
        $this->assertSame('php test.php 2', $result['run_b']['command']);
        $this->assertSame('break', $result['run_b']['status']);
        $this->assertSame(['$x' => 'int: 2', '$sum' => 'int: 0'], $result['run_b']['variables']);

        // Diff
        $this->assertSame(['a' => 'int: 1', 'b' => 'int: 2'], $result['diff']['changed']['$x']);
        $this->assertSame(['$sum'], $result['diff']['unchanged']);
        $this->assertSame([], $result['diff']['only_in_a']);
        $this->assertSame([], $result['diff']['only_in_b']);

        // Hints
        $this->assertNotEmpty($result['analysis_hints']);

        // Context should not be present when not provided
        $this->assertArrayNotHasKey('context', $result);
    }

    public function testRunWithContext(): void
    {
        $runner = $this->createFakeRunner(['context' => 'Testing context output']);
        $runner->setFakeResults([
            'php test.php 1' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
            'php test.php 2' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
        ]);

        $result = $runner->run();

        $this->assertArrayHasKey('context', $result);
        $this->assertSame('Testing context output', $result['context']);
    }

    public function testRunWithEmptyContextIsOmitted(): void
    {
        $runner = $this->createFakeRunner(['context' => '']);
        $runner->setFakeResults([
            'php test.php 1' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
            'php test.php 2' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
        ]);

        $result = $runner->run();

        $this->assertArrayNotHasKey('context', $result);
    }

    public function testRunWithCustomLabels(): void
    {
        $runner = $this->createFakeRunner([
            'label_a' => 'Normal input',
            'label_b' => 'Edge case',
        ]);
        $runner->setFakeResults([
            'php test.php 1' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
            'php test.php 2' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
        ]);

        $result = $runner->run();

        $this->assertSame('Normal input', $result['run_a']['label']);
        $this->assertSame('Edge case', $result['run_b']['label']);
    }

    public function testRunDefaultLabelsAreCommandStrings(): void
    {
        $runner = $this->createFakeRunner();
        $runner->setFakeResults([
            'php test.php 1' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
            'php test.php 2' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
        ]);

        $result = $runner->run();

        $this->assertSame('php test.php 1', $result['run_a']['label']);
        $this->assertSame('php test.php 2', $result['run_b']['label']);
    }

    public function testRunWhenNoBreakpointHit(): void
    {
        $runner = $this->createFakeRunner();
        $runner->setFakeResults([
            'php test.php 1' => ['breaks' => []],
            'php test.php 2' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 10], 'variables' => ['$x' => 'int: 1']]]],
        ]);

        $result = $runner->run();

        $this->assertSame('no_break', $result['run_a']['status']);
        $this->assertSame([], $result['run_a']['variables']);
        $this->assertSame('break', $result['run_b']['status']);
        $this->assertSame(['$x'], $result['diff']['only_in_b']);
    }

    public function testRunWithVariablesOnlyInEachRun(): void
    {
        $runner = $this->createFakeRunner();
        $runner->setFakeResults([
            'php test.php 1' => [
                'breaks' => [
                    [
                        'location' => ['file' => 'f.php', 'line' => 10],
                        'variables' => ['$a' => 'int: 1', '$shared' => 'int: 0'],
                    ],
                ],
            ],
            'php test.php 2' => [
                'breaks' => [
                    [
                        'location' => ['file' => 'f.php', 'line' => 10],
                        'variables' => ['$b' => 'int: 2', '$shared' => 'int: 0'],
                    ],
                ],
            ],
        ]);

        $result = $runner->run();

        $this->assertSame(['$shared'], $result['diff']['unchanged']);
        $this->assertSame(['$a'], $result['diff']['only_in_a']);
        $this->assertSame(['$b'], $result['diff']['only_in_b']);
    }

    public function testRunThrowsWhenXstepReturnsNoOutput(): void
    {
        $runner = $this->createFakeRunner();
        // Only set result for run_a, not run_b
        $runner->setFakeResults([
            'php test.php 1' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => []]]],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('xstep returned no output for command: php test.php 2');

        $runner->run();
    }

    public function testCliShowsHelpWithNoArgs(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare 2>&1', $output, $exitCode);

        $firstLine = $output[0] ?? '';
        $this->assertStringContainsString('Usage: xcompare', $firstLine);
    }

    public function testCliShowsHelpWithHelpFlag(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare --help 2>&1', $output, $exitCode);

        $joined = implode("\n", $output);
        $this->assertStringContainsString('--break=FILE:LINE', $joined);
        $this->assertStringContainsString('--run-a=', $joined);
        $this->assertStringContainsString('--run-b=', $joined);
    }

    public function testCliErrorWithoutBreak(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare --run-a="php a.php" --run-b="php b.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--break=FILE:LINE is required', $joined);
    }

    public function testCliErrorWithoutRunA(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare --break=test.php:10 --run-b="php b.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--run-a=', $joined);
    }

    public function testCliErrorWithoutRunB(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare --break=test.php:10 --run-a="php a.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--run-b=', $joined);
    }

    public function testCliErrorMixingModes(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare --break=test.php:10 --run-a="php a.php" --run="php x.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('Cannot mix', $joined);
    }

    public function testCliErrorCompareWithWithoutRun(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare --break=test.php:10 --compare-with=main 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--run=', $joined);
    }

    public function testCliErrorRunWithoutCompareWith(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare --break=test.php:10 --run="php a.php" 2>&1', $output, $exitCode);

        $this->assertNotSame(0, $exitCode);
        $joined = implode("\n", $output);
        $this->assertStringContainsString('--compare-with=', $joined);
    }

    public function testCliHelpShowsCompareWithMode(): void
    {
        $output = [];
        $exitCode = 0;
        exec('php ' . __DIR__ . '/../../bin/xcompare --help 2>&1', $output, $exitCode);

        $joined = implode("\n", $output);
        $this->assertStringContainsString('--compare-with=', $joined);
        $this->assertStringContainsString('--run=', $joined);
        $this->assertStringContainsString('MODE 2:', $joined);
    }

    public function testOutputProducesValidJson(): void
    {
        $runner = $this->createFakeRunner();
        $runner->setFakeResults([
            'php test.php 1' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => ['$x' => 'int: 1']]]],
            'php test.php 2' => ['breaks' => [['location' => ['file' => 'f.php', 'line' => 1], 'variables' => ['$x' => 'int: 2']]]],
        ]);

        ob_start();
        $runner->output();
        $json = ob_get_clean();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('$schema', $decoded);
        $this->assertArrayHasKey('diff', $decoded);
    }
}
