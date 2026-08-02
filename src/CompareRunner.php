<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

use function array_diff_key;
use function array_intersect_key;
use function array_keys;
use function count;
use function escapeshellarg;
use function exec;
use function fclose;
use function file_get_contents;
use function fwrite;
use function implode;
use function is_array;
use function is_resource;
use function json_decode;
use function json_encode;
use function ksort;
use function preg_match;
use function proc_close;
use function proc_open;
use function register_shutdown_function;
use function sort;
use function stream_get_contents;
use function strlen;
use function strpos;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const STDERR;

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
     *   breakpoint: array{file: string, line: int, condition?: string},
     *   run_a: array{label: string, command: string, status: string, location: array{file: string, line: int}, variables: array<string, string>},
     *   run_b: array{label: string, command: string, status: string, location: array{file: string, line: int}, variables: array<string, string>},
     *   diff: array{changed: array<string, array{a: string, b: string}>, unchanged: list<string>, only_in_a: list<string>, only_in_b: list<string>},
     *   analysis_hints: list<string>
     * }
     */
    public function run(): array
    {
        try {
            [$commandA, $commandB, $labelA, $labelB] = $this->resolveCommands();
            $cwdB = $this->worktreePath;

            $resultA = $this->executeXstep($commandA);
            $resultB = $this->executeXstep($commandB, $cwdB);
        } finally {
            $this->cleanupWorktree();
        }

        $breakSpec = $this->parseBreakSpec($this->options['break']);
        $requestedLocation = ['file' => $breakSpec['file'], 'line' => $breakSpec['line']];

        $varsA = $this->extractVariables($resultA);
        $varsB = $this->extractVariables($resultB);
        $locationA = $this->extractLocation($resultA, $requestedLocation);
        $locationB = $this->extractLocation($resultB, $requestedLocation);
        $statusA = $this->extractStatus($resultA);
        $statusB = $this->extractStatus($resultB);

        $diff = $this->computeDiff($varsA, $varsB);
        $hints = $this->generateHints($diff, $varsA, $varsB, $statusA, $statusB);

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

        $commandA = $run;
        $commandB = $run;

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
     *
     * The git-repo pre-check surfaces a clear error for non-git
     * directories; the high-entropy uniqid avoids path collisions
     * between parallel runs.
     */
    private function createWorktree(string $ref): string
    {
        $checkOutput = [];
        $checkExit = 0;
        exec('git rev-parse --is-inside-work-tree 2>&1', $checkOutput, $checkExit);
        if ($checkExit !== 0 || trim(implode('', $checkOutput)) !== 'true') {
            $message = '--compare-with requires the current directory to be inside a git repository';
            $detail = trim(implode("\n", $checkOutput));
            if ($detail !== '' && $detail !== 'true' && $detail !== 'false') {
                $message .= "\n" . $detail;
            }

            throw new RuntimeException($message);
        }

        $tempDir = sys_get_temp_dir() . '/xcompare-' . uniqid('', true);
        $this->worktreePath = $tempDir;

        // run()'s try/finally already removes the worktree on a normal return or
        // a thrown exception. This shutdown handler additionally covers a fatal
        // error (the finally never runs then), so the worktree is not leaked.
        // cleanupWorktree() nulls worktreePath, so a normal run makes this a
        // no-op. NOTE: a SIGTERM/SIGKILL delivered while blocked in
        // executeXstep()'s proc_close() still cannot be cleaned here — PHP
        // shutdown functions do not run on signal-default termination.
        register_shutdown_function(function (): void {
            $this->cleanupWorktree();
        });

        $output = [];
        $exitCode = 0;
        exec('git worktree add --detach ' . escapeshellarg($tempDir) . ' ' . escapeshellarg($ref) . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $cleanupOutput = [];
            $cleanupExit = 0;
            exec('git worktree remove ' . escapeshellarg($tempDir) . ' --force 2>&1', $cleanupOutput, $cleanupExit);
            if ($cleanupExit === 0) {
                $this->worktreePath = null;
            }

            $cleanupDetail = $cleanupExit !== 0
                ? "\nFailed to cleanup partial worktree:\n" . implode("\n", $cleanupOutput)
                : '';

            throw new RuntimeException(
                'Failed to create worktree for ref: ' . $ref . "\n" . implode("\n", $output) . $cleanupDetail,
            );
        }

        return $tempDir;
    }

    /**
     * Clean up the temporary worktree if it was created
     *
     * A failed cleanup is not fatal (the primary result has already been
     * produced), but we surface a warning to stderr so the leaked worktree
     * is visible to the caller instead of silently lingering.
     */
    private function cleanupWorktree(): void
    {
        if ($this->worktreePath === null) {
            return;
        }

        $path = $this->worktreePath;
        $this->worktreePath = null;

        $output = [];
        $exitCode = 0;
        exec('git worktree remove ' . escapeshellarg($path) . ' --force 2>&1', $output, $exitCode);

        if ($exitCode === 0) {
            return;
        }

        $detail = implode("\n", $output);
        fwrite(STDERR, "xcompare: warning: failed to remove worktree {$path}\n{$detail}\n");
    }

    /**
     * Execute xstep and return parsed JSON result
     *
     * `$command` is intentionally shell-interpreted so users can pass
     * quoting/redirection as with `xstep` itself. Stderr goes to a temp
     * file to avoid a stdout/stderr pipe-fill deadlock, and `$cwd` sets
     * the process working directory (worktree for --compare-with).
     *
     * @return array{breaks?: list<array{stack?: list<array{function: string, file: string, line: int}>, variables?: array<string, string>}>}
     */
    protected function executeXstep(string $command, string|null $cwd = null): array
    {
        $fullCommand = $this->buildXstepCommand($command);

        $stderrPath = tempnam(sys_get_temp_dir(), 'xcompare-stderr-');
        if ($stderrPath === false) {
            throw new RuntimeException('Failed to create temporary stderr file for xstep');
        }

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $stderrPath, 'w'],
        ];

        $pipes = [];
        // Inherits the parent env untouched: the spawned bin/xstep pins its
        // own Xdebug env downstream (see XdebugEnv). If this ever spawns a
        // PHP process directly, it must pin the env the same way.
        $process = proc_open($fullCommand, $descriptors, $pipes, $cwd);
        if (! is_resource($process)) {
            unlink($stderrPath);

            throw new RuntimeException("Failed to start xstep for command: {$command}");
        }

        try {
            $stdout = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exitCode = proc_close($process);
            $stderr = (string) file_get_contents($stderrPath);
        } finally {
            unlink($stderrPath);
        }

        if ($exitCode !== 0) {
            $detail = $this->summarizeProcessError($stderr);
            $suffix = $detail !== '' ? "\n{$detail}" : '';

            throw new RuntimeException("xstep failed (exit {$exitCode}) for command: {$command}{$suffix}");
        }

        if ($stdout === '') {
            throw new RuntimeException("xstep returned no output for command: {$command}");
        }

        /** @var array{breaks?: list<array{stack?: list<array{function: string, file: string, line: int}>, variables?: array<string, string>}>} $result */
        $result = $this->decodeXstepOutput($stdout, $command);

        return $result;
    }

    /**
     * Decode xstep JSON and include a stdout excerpt when parsing fails.
     *
     * @return array{breaks?: list<array{stack?: list<array{function: string, file: string, line: int}>, variables?: array<string, string>}>}
     */
    private function decodeXstepOutput(string $stdout, string $command): array
    {
        try {
            $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $excerpt = $this->excerpt($stdout);

            throw new RuntimeException(
                "xstep returned invalid JSON for command: {$command}\n"
                . $e->getMessage() . "\n"
                . "stdout excerpt:\n{$excerpt}",
                0,
                $e,
            );
        }

        if (! is_array($result)) {
            $excerpt = $this->excerpt($stdout);

            throw new RuntimeException(
                "xstep returned non-array JSON for command: {$command}\n"
                . "stdout excerpt:\n{$excerpt}",
            );
        }

        /** @var array{breaks?: list<array{stack?: list<array{function: string, file: string, line: int}>, variables?: array<string, string>}>} $result */
        return $result;
    }

    private function summarizeProcessError(string $stderr): string
    {
        $stderr = trim($stderr);
        if ($stderr === '') {
            return '';
        }

        if (preg_match('/PHP Fatal error:\s+Uncaught [^:]+:\s+(.*?)(?:\s+in\s+[^\n]+)?\nStack trace:/s', $stderr, $m)) {
            return $this->excerpt(trim($m[1]));
        }

        $stackTracePosition = strpos($stderr, "\nStack trace:");
        if ($stackTracePosition !== false) {
            $stderr = substr($stderr, 0, $stackTracePosition);
        }

        $fatalDuplicatePosition = strpos($stderr, "\nFatal error:");
        if ($fatalDuplicatePosition !== false) {
            $stderr = substr($stderr, 0, $fatalDuplicatePosition);
        }

        return $this->excerpt(trim($stderr));
    }

    private function excerpt(string $text): string
    {
        $maxBytes = 1000;

        return strlen($text) > $maxBytes ? substr($text, 0, $maxBytes) . '...' : $text;
    }

    /**
     * Build the shell command for invoking xstep.
     *
     * Always passes --steps: xstep emits two JSON documents on stdout when
     * --steps is omitted, which breaks json_decode.
     */
    private function buildXstepCommand(string $command): string
    {
        $xstepBin = __DIR__ . '/../bin/xstep';
        $breakArg = escapeshellarg('--break=' . $this->options['break']);
        $steps = isset($this->options['steps']) ? (int) $this->options['steps'] : 1;
        $stepsArg = ' --steps=' . $steps;

        $vendorArg = '';
        if (isset($this->options['include_vendor'])) {
            $vendorArg = ' --include-vendor=' . escapeshellarg($this->options['include_vendor']);
        }

        return 'php ' . escapeshellarg($xstepBin)
            . " {$breakArg}{$stepsArg}{$vendorArg} -- {$command}";
    }

    /**
     * Extract variables from the first breakpoint in xstep result
     *
     * @param array{breaks?: list<array{stack?: list<array{function: string, file: string, line: int}>, variables?: array<string, string>}>} $result
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
     * Extract location from the first breakpoint in xstep result.
     *
     * When the breakpoint was not hit, fall back to the requested location so
     * consumers see where xcompare was looking rather than an empty sentinel.
     *
     * @param array{breaks?: list<array{stack?: list<array{function: string, file: string, line: int}>, variables?: array<string, string>}>} $result
     * @param array{file: string, line: int}                                                                                                 $fallback
     *
     * @return array{file: string, line: int}
     */
    private function extractLocation(array $result, array $fallback): array
    {
        $breaks = $result['breaks'] ?? [];
        if ($breaks === []) {
            return $fallback;
        }

        $frame = $breaks[0]['stack'][0] ?? null;
        if ($frame === null) {
            return $fallback;
        }

        return ['file' => $frame['file'], 'line' => $frame['line']];
    }

    /**
     * Extract execution status
     *
     * @param array{breaks?: list<array{stack?: list<array{function: string, file: string, line: int}>, variables?: array<string, string>}>} $result
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
        ksort($changed);

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
     * @param array<string, string>                                                                                                                 $varsA
     * @param array<string, string>                                                                                                                 $varsB
     *
     * @return list<string>
     */
    private function generateHints(array $diff, array $varsA, array $varsB, string $statusA = 'break', string $statusB = 'break'): array
    {
        $hints = [];

        if ($statusA === 'no_break') {
            $hints[] = 'run_a did not hit the breakpoint (status: no_break)';
        }

        if ($statusB === 'no_break') {
            $hints[] = 'run_b did not hit the breakpoint (status: no_break)';
        }

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
     * Parse breakpoint spec string into file/line[/condition]
     *
     * The condition is preserved in the result so callers can tell the
     * comparison was run against a conditional breakpoint.
     *
     * @return array{file: string, line: int, condition?: string}
     */
    private function parseBreakSpec(string $spec): array
    {
        if (preg_match('/^(.*):(\d+)(?::(.*))?$/', $spec, $m)) {
            $result = ['file' => $m[1], 'line' => (int) $m[2]];
            if (isset($m[3]) && $m[3] !== '') {
                $result['condition'] = $m[3];
            }

            return $result;
        }

        throw new InvalidArgumentException("Invalid breakpoint spec: '{$spec}' (expected FILE:LINE[:CONDITION])");
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
