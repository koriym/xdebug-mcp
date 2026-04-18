<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use RuntimeException;

use function array_column;
use function array_merge;
use function array_slice;
use function array_sum;
use function array_unshift;
use function count;
use function end;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_get_contents;
use function filesize;
use function implode;
use function ini_get;
use function is_array;
use function is_numeric;
use function is_readable;
use function is_string;
use function json_decode;
use function json_encode;
use function passthru;
use function preg_match;
use function round;
use function shell_exec;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;
use function substr_count;
use function trim;
use function uasort;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Xdebug Cachegrind profile analyzer
 *
 * Executes PHP scripts with Xdebug profiling enabled and analyzes Cachegrind output.
 * Provides performance statistics and bottleneck identification from profile data.
 *
 * @codeCoverageIgnore Requires live Xdebug profiling runtime
 */
class XdebugProfiler
{
    /** @param list<string> $phpArgs */
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
        // Get appropriate Xdebug flag (empty if already loaded)
        $xdebugFlag = XdebugFinder::getXdebugFlag();

        $xdebugOptions = [
            '-dxdebug.mode=profile',
            '-dxdebug.start_with_request=yes',
            "-dxdebug.output_dir={$xdebugOutputDir}",
            '-dxdebug.profiler_output_name=cachegrind.out.%u',
            '-dxdebug.use_compression=0',
        ];

        // Add Xdebug extension flag if needed
        if ($xdebugFlag !== '') {
            array_unshift($xdebugOptions, trim($xdebugFlag));
        }

        $allArgs = array_merge($xdebugOptions, [$targetFile], $phpArgs);
        $cmd = XdebugCommandExecutor::buildPhpCommand($allArgs);
        XdebugCommandExecutor::executeAndAssertSuccess($cmd);

        return XdebugCommandExecutor::findLatestArtifact(
            "{$xdebugOutputDir}/cachegrind.out.*",
            'Profile file not created. Check Xdebug installation.',
        );
    }

    public function parseProfileFile(string $profileFile): DTO\ProfileStatistics
    {
        if (! file_exists($profileFile) || ! is_readable($profileFile)) {
            throw new InvalidArgumentException("Profile file not found or not readable: $profileFile");
        }

        $fileSize = filesize($profileFile);
        if ($fileSize === false || $fileSize === 0) {
            throw new RuntimeException("Profile file is empty or unreadable: $profileFile");
        }

        $content = file_get_contents($profileFile);
        if ($content === false) {
            throw new RuntimeException("Failed to read profile file: $profileFile");
        }

        // Parse Cachegrind format
        $functionsCount = substr_count($content, "\nfn=");
        $callsCount = substr_count($content, "\ncalls=");
        $targetFile = '';
        $creator = '';
        $command = '';

        // Extract header information
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (str_starts_with($line, 'creator: ')) {
                $creator = substr($line, 9);
            } elseif (str_starts_with($line, 'cmd: ')) {
                $command = substr($line, 5);
                // Extract target file from command
                $parts = explode(' ', $command);
                $targetFile = end($parts);
            }
        }

        return new DTO\ProfileStatistics(
            filePath: $profileFile,
            fileSize: $fileSize,
            functionsCount: $functionsCount,
            callsCount: $callsCount,
            targetFile: $targetFile,
            creator: $creator,
            command: $command,
        );
    }

    /**
     * Generate schema-compliant JSON output for AI analysis
     *
     * @return array<string, string|list<string>>
     */
    private function generateSchemaCompliantOutput(DTO\ProfileStatistics $stats): array
    {
        // Parse the actual profile file for detailed analysis
        $detailedStats = $this->analyzeProfileContent($stats->filePath);

        return [
            '📁 profile_file' => $stats->filePath,
            '📊 total_lines' => $detailedStats->totalLines . ' lines',
            '💾 file_size_bytes' => $stats->fileSize . ' bytes',
            '📏 file_size_formatted' => $stats->getFileSizeFormatted(),
            '📈 functions_count' => $detailedStats->functionsCount . ' functions',
            '👤 user_functions' => $detailedStats->userFunctions . ' user',
            '⚙️ internal_functions' => $detailedStats->internalFunctions . ' internal',
            '📞 total_calls' => $detailedStats->totalCalls . ' calls',
            '⏱️ execution_time_ms' => $detailedStats->executionTimeMs . 'ms',
            '🧠 peak_memory_mb' => $detailedStats->peakMemoryMb . 'MB',
            '📂 file_io_operations' => $detailedStats->fileIoOperations . ' operations',
            '🗃️ database_operations' => $detailedStats->databaseOperations . ' queries',
            '🎯 bottleneck_functions' => $detailedStats->bottleneckFunctions,
            '💡 optimization_suggestions' => [],
            '📋 specification' => 'https://kcachegrind.github.io/html/CallgrindFormat.html',
            '🔗 schema' => 'https://koriym.github.io/xdebug-mcp/schemas/xdebug-profile.json',
        ];
    }

    private function analyzeProfileContent(string $profileFile): DTO\ProfileAnalysis
    {
        $content = file_get_contents($profileFile);
        if ($content === false) {
            throw new RuntimeException("Failed to read profile file: $profileFile");
        }

        $lines = explode("\n", $content);

        $totalLines = count($lines);
        $functionsCount = 0;
        $userFunctions = 0;
        $internalFunctions = 0;
        $totalCalls = 0;
        $executionTimeMs = 0.0;
        $peakMemoryMb = 0.0;
        /** @var array<string, array{cost: int, calls: int}> $functions */
        $functions = [];
        $currentFunction = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'fn=')) {
                $functionsCount++;
                $functionName = substr($line, 3);
                $currentFunction = $functionName;

                // Classify function type
                if (str_contains($functionName, 'php::') || str_contains($functionName, '{main}')) {
                    $internalFunctions++;
                } else {
                    $userFunctions++;
                }

                $functions[$functionName] = ['cost' => 0, 'calls' => 0];
            } elseif (str_starts_with($line, 'calls=')) {
                $callsParts = explode(' ', $line);
                $callsValue = substr($callsParts[0], 6); // Remove 'calls=' prefix
                $totalCalls += (int) $callsValue;
                if ($currentFunction !== null && isset($functions[$currentFunction])) {
                    $functions[$currentFunction]['calls']++;
                }
            } elseif (str_starts_with($line, 'summary:')) {
                $parts = explode(' ', $line);
                if (count($parts) >= 2) {
                    $totalCost = (int) $parts[1];
                    $executionTimeMs = round($totalCost / 100000, 2); // Rough estimate
                    $peakMemoryMb = round($totalCost / 1000000, 1); // Rough estimate
                }
            } elseif (preg_match('/^\d+/', $line) === 1 && $currentFunction !== null) {
                // Cost line
                $costs = explode(' ', $line);
                if (is_numeric($costs[0])) {
                    $cost = (int) $costs[0];
                    if (isset($functions[$currentFunction])) {
                        $functions[$currentFunction]['cost'] += $cost;
                    }
                }
            }
        }

        // Find bottleneck functions (top 5 by cost)
        uasort($functions, static fn (array $a, array $b): int => $b['cost'] <=> $a['cost']);
        $topFunctions = array_slice($functions, 0, 5, true);
        $totalCost = array_sum(array_column($functions, 'cost'));

        /** @var list<string> $bottleneckFunctions */
        $bottleneckFunctions = [];
        if ($totalCost > 0) {
            foreach ($topFunctions as $name => $data) {
                $percentage = round($data['cost'] / $totalCost * 100, 1);
                $bottleneckFunctions[] = "{$name} ({$percentage}%)";
            }
        }

        return new DTO\ProfileAnalysis(
            totalLines: $totalLines,
            functionsCount: $functionsCount,
            userFunctions: $userFunctions,
            internalFunctions: $internalFunctions,
            totalCalls: $totalCalls,
            executionTimeMs: $executionTimeMs,
            peakMemoryMb: $peakMemoryMb,
            fileIoOperations: 0,
            databaseOperations: 0,
            bottleneckFunctions: $bottleneckFunctions,
        );
    }

    /**
     * Validate JSON output against xdebug-profile.json schema
     *
     * @param array<string, string|list<string>> $data
     */
    private function validateJsonOutput(array $data): void
    {
        $schemaPath = __DIR__ . '/../docs/schemas/xdebug-profile.json';

        if (! file_exists($schemaPath)) {
            // Schema validation is optional if schema file doesn't exist
            return;
        }

        $validator = new Validator();
        $schemaContent = file_get_contents($schemaPath);
        if ($schemaContent === false) {
            throw new RuntimeException("Failed to read schema file: $schemaPath");
        }

        $schema = json_decode($schemaContent, false, 512, JSON_THROW_ON_ERROR);

        // Convert to object for validation
        $jsonData = json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        $validator->validate($jsonData, $schema, Constraint::CHECK_MODE_NORMAL);

        if (! $validator->isValid()) {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                if (! is_array($error)) {
                    continue;
                }

                $property = isset($error['property']) && is_string($error['property'])
                    ? $error['property']
                    : 'unknown';
                $message = isset($error['message']) && is_string($error['message'])
                    ? $error['message']
                    : 'unknown error';
                $errors[] = sprintf("Property '%s': %s", $property, $message);
            }

            throw new RuntimeException(
                "Profile JSON output does not conform to schema:\n" . implode("\n", $errors),
            );
        }
    }

    /** @return array{profile_file: string, file_size_bytes: int, file_size_formatted: string, functions_count: int, calls_count: int, target_file: string, creator: string} */
    public function generateStatistics(DTO\ProfileStatistics $stats): array
    {
        return [
            'profile_file' => $stats->filePath,
            'file_size_bytes' => $stats->fileSize,
            'file_size_formatted' => $stats->getFileSizeFormatted(),
            'functions_count' => $stats->functionsCount,
            'calls_count' => $stats->callsCount,
            'target_file' => $stats->targetFile,
            'creator' => $stats->creator,
        ];
    }

    public function displayResults(DTO\ProfileStatistics $stats, bool $jsonOutput = false): void
    {
        if ($jsonOutput) {
            // Generate schema-compliant JSON output
            $schemaCompliantOutput = $this->generateSchemaCompliantOutput($stats);

            // Always validate against schema (performance cost is negligible)
            $this->validateJsonOutput($schemaCompliantOutput);

            echo json_encode($schemaCompliantOutput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo "✅ Profile complete: {$stats->filePath}\n";
            echo "📊 Size: {$stats->getFileSizeFormatted()}\n";

            if ($stats->functionsCount > 0) {
                echo "📈 Functions: {$stats->functionsCount}\n";
                echo "📞 Calls: {$stats->callsCount}\n";
            }

            echo "\n💡 Analyze with Claude Code:\n";
            echo "   claude \"Analyze {$stats->filePath}\"\n";
            echo "\n💡 Or use KCachegrind/qcachegrind:\n";
            echo "   kcachegrind {$stats->filePath}\n";
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
