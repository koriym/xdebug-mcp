<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Closure;

use function array_slice;
use function basename;
use function escapeshellarg;
use function explode;
use function file;
use function file_exists;
use function implode;
use function in_array;
use function is_string;
use function shell_exec;
use function trim;

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
    /** @param DebugContext $context */
    public function analyze(array $context, string $userArgs, Closure $logger): void
    {
        $logger('🤖 Analyzing execution trace with Claude...');

        $prompt = $this->buildPrompt($context, $userArgs);
        $claudeCommand = 'claude --print ' . escapeshellarg($prompt);
        $logger('💭 Executing: ' . $claudeCommand);

        $output = shell_exec($claudeCommand . ' 2>&1');
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
        if (is_string($traceFile) && $traceFile !== '' && file_exists($traceFile)) {
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
}
