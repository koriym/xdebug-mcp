<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use RuntimeException;

use function array_column;
use function array_map;
use function array_merge;
use function array_slice;
use function array_sum;
use function count;
use function end;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_get_contents;
use function filemtime;
use function filesize;
use function glob;
use function implode;
use function ini_get;
use function is_numeric;
use function is_readable;
use function json_decode;
use function json_encode;
use function passthru;
use function preg_match;
use function round;
use function shell_exec;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strpos;
use function substr;
use function substr_count;
use function trim;
use function uasort;
use function usort;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Xdebug Cachegrind profile analyzer
 *
 * Executes PHP scripts with Xdebug profiling enabled and analyzes Cachegrind output.
 * Provides performance statistics and bottleneck identification from profile data.
 */
class XdebugProfiler
{
    public function executeProfile(string $targetFile, array $phpArgs = [], bool $jsonOutput = false): string
    {
        if (! file_exists($targetFile)) {
            throw new InvalidArgumentException("Target file not found: $targetFile");
        }

        // Get Xdebug output directory
        $xdebugOutputDir = ini_get('xdebug.output_dir') ?: '/tmp';

        if (! $jsonOutput) {
            echo "📊 Profiling: $targetFile\n";
        }

        // Build command with Xdebug profiling enabled
        $xdebugOptions = [
            '-dzend_extension=xdebug',
            '-dxdebug.mode=profile',
            '-dxdebug.start_with_request=yes',
            "-dxdebug.output_dir={$xdebugOutputDir}",
            '-dxdebug.profiler_output_name=cachegrind.out.%%s',
            '-dxdebug.use_compression=0',
        ];

        // Combine all arguments
        $allArgs = array_merge($xdebugOptions, [$targetFile], $phpArgs);
        $cmd = 'php ' . implode(' ', array_map('escapeshellarg', $allArgs));

        // Execute with passthru to show output
        $exitCode = 0;
        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("PHP execution failed with exit code: $exitCode");
        }

        // Find the created profile file (Xdebug generates its own filename)
        $profileFiles = glob("{$xdebugOutputDir}/cachegrind.out.*");
        if (empty($profileFiles)) {
            throw new RuntimeException('Profile file not created. Check Xdebug installation.');
        }

        // Get the most recent profile file
        usort($profileFiles, static function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $profileFiles[0];
    }

    public function parseProfileFile(string $profileFile): array
    {
        if (! file_exists($profileFile) || ! is_readable($profileFile)) {
            throw new InvalidArgumentException("Profile file not found or not readable: $profileFile");
        }

        $fileSize = filesize($profileFile);
        if ($fileSize === 0) {
            throw new RuntimeException("Profile file is empty: $profileFile");
        }

        $content = file_get_contents($profileFile);

        // Parse Cachegrind format
        $stats = [
            'file_path' => $profileFile,
            'file_size' => $fileSize,
            'functions_count' => substr_count($content, "\nfn="),
            'calls_count' => substr_count($content, "\ncalls="),
            'target_file' => '',
            'creator' => '',
            'command' => '',
        ];

        // Extract header information
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strpos($line, 'creator: ') === 0) {
                $stats['creator'] = substr($line, 9);
            } elseif (strpos($line, 'cmd: ') === 0) {
                $stats['command'] = substr($line, 5);
                // Extract target file from command
                $parts = explode(' ', $stats['command']);
                $stats['target_file'] = end($parts);
            }
        }

        return $stats;
    }

    /**
     * Generate schema-compliant JSON output for AI analysis
     */
    private function generateSchemaCompliantOutput(array $stats): array
    {
        // Parse the actual profile file for detailed analysis
        $detailedStats = $this->analyzeProfileContent($stats['file_path']);

        return [
            '📁 profile_file' => $stats['file_path'],
            '📊 total_lines' => $detailedStats['total_lines'] . ' lines',
            '💾 file_size_bytes' => $stats['file_size'] . ' bytes',
            '📏 file_size_formatted' => $stats['file_size_formatted'],
            '📈 functions_count' => $detailedStats['functions_count'] . ' functions',
            '👤 user_functions' => $detailedStats['user_functions'] . ' user',
            '⚙️ internal_functions' => $detailedStats['internal_functions'] . ' internal',
            '📞 total_calls' => $detailedStats['total_calls'] . ' calls',
            '⏱️ execution_time_ms' => $detailedStats['execution_time_ms'] . 'ms',
            '🧠 peak_memory_mb' => $detailedStats['peak_memory_mb'] . 'MB',
            '📂 file_io_operations' => $detailedStats['file_io_operations'] . ' operations',
            '🗃️ database_operations' => $detailedStats['database_operations'] . ' queries',
            '🎯 bottleneck_functions' => $detailedStats['bottleneck_functions'],
            '💡 optimization_suggestions' => [],
            '📋 specification' => 'https://kcachegrind.github.io/html/CallgrindFormat.html',
            '🔗 schema' => 'https://koriym.github.io/xdebug-mcp/schemas/xdebug-profile.json',
        ];
    }

    /**
     * Analyze profile content for detailed statistics
     */
    private function がいanalyzeProfileContent(string $profileFile): array
    {
        $content = file_get_contents($profileFile);
        $lines = explode("\n", $content);

        $analysis = [
            'total_lines' => count($lines),
            'functions_count' => 0,
            'user_functions' => 0,
            'internal_functions' => 0,
            'total_calls' => 0,
            'execution_time_ms' => 0,
            'peak_memory_mb' => 0,
            'file_io_operations' => 0,
            'database_operations' => 0,
            'bottleneck_functions' => [],
        ];

        $functions = [];
        $currentFunction = null;
        $summary = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'fn=')) {
                $analysis['functions_count']++;
                $functionName = substr($line, 3);
                $currentFunction = $functionName;

                // Classify function type
                if (str_contains($functionName, 'php::') || str_contains($functionName, '{main}')) {
                    $analysis['internal_functions']++;
                } else {
                    $analysis['user_functions']++;
                }

                $functions[$functionName] = ['cost' => 0, 'calls' => 0];
            } elseif (str_starts_with($line, 'calls=')) {
                $analysis['total_calls'] += (int) explode(' ', $line)[0];
                if ($currentFunction && isset($functions[$currentFunction])) {
                    $functions[$currentFunction]['calls']++;
                }
            } elseif (str_starts_with($line, 'summary:')) {
                $parts = explode(' ', $line);
                if (count($parts) >= 2) {
                    $totalCost = (int) $parts[1];
                    $analysis['execution_time_ms'] = round($totalCost / 100000, 2); // Rough estimate
                    $analysis['peak_memory_mb'] = round($totalCost / 1000000, 1); // Rough estimate
                }
            } elseif (preg_match('/^\d+/', $line) && $currentFunction) {
                // Cost line
                $costs = explode(' ', $line);
                if (count($costs) > 0 && is_numeric($costs[0])) {
                    $cost = (int) $costs[0];
                    if (isset($functions[$currentFunction])) {
                        $functions[$currentFunction]['cost'] += $cost;
                    }
                }
            }
        }

        // Find bottleneck functions (top 5 by cost)
        uasort($functions, static fn ($a, $b) => $b['cost'] <=> $a['cost']);
        $topFunctions = array_slice($functions, 0, 5, true);
        $totalCost = array_sum(array_column($functions, 'cost'));

        if ($totalCost > 0) {
            foreach ($topFunctions as $name => $data) {
                $percentage = round($data['cost'] / $totalCost * 100, 1);
                $analysis['bottleneck_functions'][] = "{$name} ({$percentage}%)";
            }
        }

        return $analysis;
    }

    /**
     * Validate JSON output against xdebug-profile.json schema
     */
    private function validateJsonOutput(array $data): void
    {
        $schemaPath = __DIR__ . '/../docs/schemas/xdebug-profile.json';

        if (! file_exists($schemaPath)) {
            // Schema validation is optional if schema file doesn't exist
            return;
        }

        $validator = new Validator();
        $schema = json_decode(file_get_contents($schemaPath));

        // Convert to object for validation
        $jsonData = json_decode(json_encode($data));

        $validator->validate($jsonData, $schema, Constraint::CHECK_MODE_NORMAL);

        if (! $validator->isValid()) {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf("Property '%s': %s", $error['property'], $error['message']);
            }

            throw new RuntimeException(
                "Profile JSON output does not conform to schema:\n" . implode("\n", $errors),
            );
        }
    }

    public function generateStatistics(array $stats): array
    {
        $fileSize = $stats['file_size'];
        $sizeFormatted = $fileSize > 1024 ? round($fileSize / 1024, 1) . 'K' : $fileSize . 'B';

        return [
            'profile_file' => $stats['file_path'],
            'file_size_bytes' => $fileSize,
            'file_size_formatted' => $sizeFormatted,
            'functions_count' => $stats['functions_count'],
            'calls_count' => $stats['calls_count'],
            'target_file' => $stats['target_file'],
            'creator' => $stats['creator'],
        ];
    }

    public function displayResults(array $stats, bool $jsonOutput = false): void
    {
        if ($jsonOutput) {
            // Generate schema-compliant JSON output
            $schemaCompliantOutput = $this->generateSchemaCompliantOutput($stats);

            // Always validate against schema (performance cost is negligible)
            $this->validateJsonOutput($schemaCompliantOutput);

            echo json_encode($schemaCompliantOutput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo "✅ Profile complete: {$stats['profile_file']}\n";
            echo "📊 Size: {$stats['file_size_formatted']}\n";

            if ($stats['functions_count'] > 0) {
                echo "📈 Functions: {$stats['functions_count']}\n";
                echo "📞 Calls: {$stats['calls_count']}\n";
            }

            echo "\n💡 Analyze with Claude Code:\n";
            echo "   claude \"Analyze {$stats['profile_file']}\"\n";
            echo "\n💡 Or use KCachegrind/qcachegrind:\n";
            echo "   kcachegrind {$stats['profile_file']}\n";
        }
    }

    public function analyzeWithClaude(string $profileFile): void
    {
        $languageOutput = shell_exec('defaults read -g AppleLanguages') ?: '';
        $lang = str_contains($languageOutput, 'ja') ? 'Japanese' : 'English';
        $claudePrompt = "Analyze this Cachegrind profile file for comprehensive code quality: 1) Performance bottlenecks (slow functions, memory usage, execution time), 2) Security concerns (resource exhaustion, timing attacks), 3) Efficiency violations (redundant computations, unnecessary I/O), 4) Architecture principles (separation of concerns, single responsibility). Focus especially on AI/Junior developer code that passes tests but has hidden performance and quality issues: $profileFile. Answer in $lang.";

        echo "\n🤖 Starting Claude Code analysis...\n";
        passthru('claude ' . escapeshellarg($claudePrompt));
        echo "\n";
        echo "🤖 Claude Code analysis completed.\n";
    }
}
