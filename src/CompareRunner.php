<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use RuntimeException;

use function array_diff_key;
use function array_intersect_key;
use function array_keys;
use function escapeshellarg;
use function exec;
use function getcwd;
use function implode;
use function json_decode;
use function json_encode;
use function preg_match;
use function sort;
use function str_replace;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Compare variable states at the same breakpoint across two different executions
 *
 * Runs xstep twice with different commands/inputs and produces a diff
 * of variable states at the specified breakpoint.
 */
class CompareRunner
{
    private string|null $worktreePath = null;

    /** @param array{break: string, run_a?: string, run_b?: string, run?: string, compare_with?: string, label_a?: string, label_b?: string, context?: string, steps?: int, include_vendor?: string} $options */
    public function __construct(
        private readonly array $options,
    ) {
    }

    /**
     * @return array{
     *   '$schema': string,
     *   context?: string,
     *   breakpoint: array{file: string, line: int},
     *   run_a: array{label: string, command: string, status: string, location: array{file: string, line: int}, variables: array<string, string>},
     *   run_b: array{label: string, command: string, status: string, location: array{file: string, line: int}, variables: array<string, string>},
     *   diff: array{changed: array<string, array{a: string, b: string}>, unchanged: list<string>, only_in_a: list<string>, only_in_b: list<string>},
     *   analysis_hints: list<string>
     * }
     */
    public function run(): array
    {
        [$commandA, $commandB, $labelA, $labelB] = $this->resolveCommands();

        try {
            $resultA = $this->executeXstep($commandA);
            $resultB = $this->executeXstep($commandB);
        } finally {
            $this->cleanupWorktree();
        }

        $varsA = $this->extractVariables($resultA);
        $varsB = $this->extractVariables($resultB);
        $locationA = $this->extractLocation($resultA);
        $locationB = $this->extractLocation($resultB);
        $statusA = $this->extractStatus($resultA);
        $statusB = $this->extractStatus($resultB);

        $diff = $this->computeDiff($varsA, $varsB);
        $hints = $this->generateHints($diff, $varsA, $varsB);

        $breakSpec = $this->parseBreakSpec($this->options['break']);

        $output = [
            '$schema' => 'https://koriym.github.io/xdebug-mcp/schemas/xcompare.json',
            'breakpoint' => $breakSpec,
            'run_a' => [
                'label' => $labelA,
                'command' => $commandA,
                'status' => $statusA,
                'location' => $locationA,
                'variables' => $varsA,
            ],
            'run_b' => [
                'label' => $labelB,
                'command' => $commandB,
                'status' => $statusB,
                'location' => $locationB,
                'variables' => $varsB,
            ],
            'diff' => $diff,
            'analysis_hints' => $hints,
        ];

        if (($this->options['context'] ?? '') !== '') {
            $output['context'] = $this->options['context'];
        }

        return $output;
    }

    /**
     * Resolve commands and labels based on mode (run_a/run_b or compare_with)
     *
     * @return array{0: string, 1: string, 2: string, 3: string} [commandA, commandB, labelA, labelB]
     */
    private function resolveCommands(): array
    {
        if (isset($this->options['compare_with'])) {
            return $this->resolveCompareWithMode();
        }

        return $this->resolveExplicitMode();
    }

    /**
     * Resolve for --compare-with mode (same command, different git refs)
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function resolveCompareWithMode(): array
    {
        $run = $this->options['run'] ?? '';
        if ($run === '') {
            throw new RuntimeException('--run is required when using --compare-with');
        }

        $ref = $this->options['compare_with'] ?? '';
        $this->worktreePath = $this->createWorktree($ref);

        $cwd = (string) getcwd();
        $commandA = $run;
        $commandB = str_replace($cwd, $this->worktreePath, $run);

        $labelA = $this->options['label_a'] ?? 'HEAD (current)';
        $labelB = $this->options['label_b'] ?? $ref;

        return [$commandA, $commandB, $labelA, $labelB];
    }

    /**
     * Resolve for explicit --run-a/--run-b mode
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function resolveExplicitMode(): array
    {
        $commandA = $this->options['run_a'] ?? '';
        $commandB = $this->options['run_b'] ?? '';

        if ($commandA === '' || $commandB === '') {
            throw new RuntimeException('--run-a and --run-b are required');
        }

        $labelA = $this->options['label_a'] ?? $commandA;
        $labelB = $this->options['label_b'] ?? $commandB;

        return [$commandA, $commandB, $labelA, $labelB];
    }

    /**
     * Create a temporary git worktree for the specified ref
     */
    private function createWorktree(string $ref): string
    {
        $tempDir = sys_get_temp_dir() . '/xcompare-' . uniqid();

        $output = [];
        $exitCode = 0;
        exec('git worktree add ' . escapeshellarg($tempDir) . ' ' . escapeshellarg($ref) . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to create worktree for ref: ' . $ref . "\n" . implode("\n", $output));
        }

        return $tempDir;
    }

    /**
     * Clean up the temporary worktree if it was created
     */
    private function cleanupWorktree(): void
    {
        if ($this->worktreePath === null) {
            return;
        }

        exec('git worktree remove ' . escapeshellarg($this->worktreePath) . ' --force 2>/dev/null');
        $this->worktreePath = null;
    }

    /**
     * Execute xstep and return parsed JSON result
     *
     * @return array{breaks?: list<array{location?: array{file: string, line: int}, variables?: array<string, string>}>}
     */
    protected function executeXstep(string $command): array
    {
        $xstepBin = __DIR__ . '/../bin/xstep';
        $breakArg = escapeshellarg('--break=' . $this->options['break']);
        $stepsArg = '';
        if (isset($this->options['steps'])) {
            $stepsArg = ' --steps=' . (int) $this->options['steps'];
        }

        $vendorArg = '';
        if (isset($this->options['include_vendor'])) {
            $vendorArg = ' --include-vendor=' . escapeshellarg($this->options['include_vendor']);
        }

        $fullCommand = "php {$xstepBin} {$breakArg}{$stepsArg}{$vendorArg} -- {$command} 2>/dev/null";

        $output = [];
        $exitCode = 0;
        exec($fullCommand, $output, $exitCode);

        $jsonOutput = implode("\n", $output);
        if ($jsonOutput === '') {
            throw new RuntimeException("xstep returned no output for command: {$command}");
        }

        /** @var array{breaks?: list<array{location?: array{file: string, line: int}, variables?: array<string, string>}>} $result */
        $result = json_decode($jsonOutput, true, 512, JSON_THROW_ON_ERROR);

        return $result;
    }

    /**
     * Extract variables from the first breakpoint in xstep result
     *
     * @param array{breaks?: list<array{location?: array{file: string, line: int}, variables?: array<string, string>}>} $result
     *
     * @return array<string, string>
     */
    private function extractVariables(array $result): array
    {
        $breaks = $result['breaks'] ?? [];
        if ($breaks === []) {
            return [];
        }

        return $breaks[0]['variables'] ?? [];
    }

    /**
     * Extract location from the first breakpoint in xstep result
     *
     * @param array{breaks?: list<array{location?: array{file: string, line: int}, variables?: array<string, string>}>} $result
     *
     * @return array{file: string, line: int}
     */
    private function extractLocation(array $result): array
    {
        $breaks = $result['breaks'] ?? [];
        if ($breaks === []) {
            return ['file' => '', 'line' => 0];
        }

        return $breaks[0]['location'] ?? ['file' => '', 'line' => 0];
    }

    /**
     * Extract execution status
     *
     * @param array{breaks?: list<array{location?: array{file: string, line: int}, variables?: array<string, string>}>} $result
     */
    private function extractStatus(array $result): string
    {
        $breaks = $result['breaks'] ?? [];

        return $breaks !== [] ? 'break' : 'no_break';
    }

    /**
     * Compute diff between two variable sets
     *
     * @param array<string, string> $varsA
     * @param array<string, string> $varsB
     *
     * @return array{changed: array<string, array{a: string, b: string}>, unchanged: list<string>, only_in_a: list<string>, only_in_b: list<string>}
     */
    private function computeDiff(array $varsA, array $varsB): array
    {
        $changed = [];
        $unchanged = [];

        // Variables present in both
        $commonKeys = array_keys(array_intersect_key($varsA, $varsB));
        foreach ($commonKeys as $key) {
            if ($varsA[$key] === $varsB[$key]) {
                $unchanged[] = $key;
            } else {
                $changed[$key] = ['a' => $varsA[$key], 'b' => $varsB[$key]];
            }
        }

        // Variables only in A
        $onlyInA = array_keys(array_diff_key($varsA, $varsB));
        sort($onlyInA);

        // Variables only in B
        $onlyInB = array_keys(array_diff_key($varsB, $varsA));
        sort($onlyInB);

        sort($unchanged);

        return [
            'changed' => $changed,
            'unchanged' => $unchanged,
            'only_in_a' => $onlyInA,
            'only_in_b' => $onlyInB,
        ];
    }

    /**
     * Generate human/AI-readable analysis hints
     *
     * @param array{changed: array<string, array{a: string, b: string}>, unchanged: list<string>, only_in_a: list<string>, only_in_b: list<string>} $diff
     * @param array<string, string> $varsA
     * @param array<string, string> $varsB
     *
     * @return list<string>
     */
    private function generateHints(array $diff, array $varsA, array $varsB): array
    {
        $hints = [];

        foreach ($diff['changed'] as $name => $values) {
            $hints[] = "{$name}: {$values['a']} → {$values['b']} (changed)";
        }

        foreach ($diff['only_in_a'] as $name) {
            $hints[] = "{$name}: {$varsA[$name]} (only in run_a)";
        }

        foreach ($diff['only_in_b'] as $name) {
            $hints[] = "{$name}: {$varsB[$name]} (only in run_b)";
        }

        $changedCount = count($diff['changed']);
        $unchangedCount = count($diff['unchanged']);
        $totalVars = $changedCount + $unchangedCount + count($diff['only_in_a']) + count($diff['only_in_b']);
        $hints[] = "{$unchangedCount} variable(s) unchanged, {$changedCount} variable(s) changed, {$totalVars} total";

        return $hints;
    }

    /**
     * Parse breakpoint spec string into file/line
     *
     * @return array{file: string, line: int}
     */
    private function parseBreakSpec(string $spec): array
    {
        // Remove condition part if present (file:line:condition)
        if (preg_match('/^(.*):(\d+)/', $spec, $m)) {
            return ['file' => $m[1], 'line' => (int) $m[2]];
        }

        return ['file' => $spec, 'line' => 0];
    }

    /**
     * Output the comparison result as JSON
     */
    public function output(): void
    {
        $result = $this->run();
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
