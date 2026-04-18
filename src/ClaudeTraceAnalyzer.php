<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Closure;

use function array_slice;
use function basename;
use function escapeshellarg;
use function explode;
use function file;
use function implode;
use function in_array;
use function ini_get;
use function is_file;
use function is_readable;
use function is_string;
use function realpath;
use function rtrim;
use function shell_exec;
use function str_starts_with;
use function sys_get_temp_dir;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Optional adapter for Claude-specific trace analysis.
 *
 * @phpstan-type DebugContext array{
 *     target_script: string,
 *     debug_port: int,
 *     trace_file: string|null,
 *     breakpoint_line: int|null,
 *     current_variables?: array<string, string>,
 *     current_stack?: list<string>
 * }
 */
final class ClaudeTraceAnalyzer
{
    /** @var Closure(string): (string|null) */
    private readonly Closure $commandRunner;

    /** @param (Closure(string): (string|null))|null $commandRunner Overridable for tests; defaults to shell_exec. */
    public function __construct(Closure|null $commandRunner = null)
    {
        $this->commandRunner = $commandRunner ?? static function (string $command): string|null {
            $result = shell_exec($command);

            return is_string($result) ? $result : null;
        };
    }

    /** @param DebugContext $context */
    public function analyze(array $context, string $userArgs, Closure $logger): void
    {
        $logger('🤖 Analyzing execution trace with Claude...');

        $prompt = $this->buildPrompt($context, $userArgs);
        $claudeCommand = 'claude --print ' . escapeshellarg($prompt);
        $logger('💭 Executing: ' . $claudeCommand);

        $output = ($this->commandRunner)($claudeCommand . ' 2>&1');
        if (! is_string($output) || trim($output) === '') {
            $logger('❌ Claude analysis failed or produced no output');

            return;
        }

        $logger('📊 Claude Analysis Result:');
        foreach (explode("\n", trim($output)) as $line) {
            if (in_array(trim($line), ['', '0'], true)) {
                continue;
            }

            $logger('   ' . $line);
        }
    }

    /** @param DebugContext $context */
    public function buildPrompt(array $context, string $userArgs): string
    {
        $targetScript = basename($context['target_script']);
        $prompt = "Analyze PHP debugging session for {$targetScript}:\n\n";

        $traceFile = $context['trace_file'];
        if (is_string($traceFile) && $traceFile !== '' && $this->isSafeTraceFile($traceFile)) {
            $prompt .= "## Trace Analysis\n";
            $prompt .= "Please analyze the execution trace: {$traceFile}\n\n";

            $traceLines = file($traceFile);
            if ($traceLines !== false && $traceLines !== []) {
                $lastLines = array_slice($traceLines, -20);
                $prompt .= "Recent trace data:\n```\n" . implode('', $lastLines) . "```\n\n";
            }
        }

        $currentVariables = $context['current_variables'] ?? null;
        if ($currentVariables !== null && $currentVariables !== []) {
            $prompt .= "## Current Variables\n";
            foreach ($currentVariables as $var => $value) {
                $prompt .= "- \${$var} = {$value}\n";
            }

            $prompt .= "\n";
        }

        $breakpointLine = $context['breakpoint_line'] ?? null;
        if ($breakpointLine !== null && $breakpointLine !== 0) {
            $prompt .= "## Breakpoint Context\n";
            $prompt .= "Stopped at line {$breakpointLine} in {$targetScript}\n\n";
        }

        if ($userArgs !== '' && $userArgs !== '0') {
            $prompt .= "## Specific Analysis Request\n";
            $prompt .= $userArgs . "\n\n";
        }

        $prompt .= "## Analysis Focus\n";
        $prompt .= "Please provide:\n";
        $prompt .= "1. Call chain analysis leading to current breakpoint\n";
        $prompt .= "2. Variable state analysis and any anomalies\n";
        $prompt .= "3. Root cause identification if this is a bug investigation\n";
        $prompt .= "4. Performance insights from trace data\n";

        return $prompt . "5. Suggested next debugging steps or code fixes\n";
    }

    /**
     * Accept only regular, readable files inside the Xdebug output directory or the system temp dir.
     *
     * Trace files are produced by DebugServer/XdebugTracer into xdebug.output_dir. Restricting
     * reads to that directory prevents an attacker-controlled context from leaking arbitrary
     * filesystem contents into the Claude prompt.
     */
    private function isSafeTraceFile(string $traceFile): bool
    {
        $resolved = realpath($traceFile);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            return false;
        }

        $allowedRoots = [];
        $xdebugOutputDir = ini_get('xdebug.output_dir');
        if (is_string($xdebugOutputDir) && $xdebugOutputDir !== '') {
            $allowedRoot = realpath($xdebugOutputDir);
            if (is_string($allowedRoot)) {
                $allowedRoots[] = $allowedRoot;
            }
        }

        $tempRoot = realpath(sys_get_temp_dir());
        if (is_string($tempRoot)) {
            $allowedRoots[] = $tempRoot;
        }

        foreach ($allowedRoots as $root) {
            if (str_starts_with($resolved, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
