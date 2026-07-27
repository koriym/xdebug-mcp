<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Profiler;

use Koriym\XdebugMcp\Utilities\VendorFilter;
use RuntimeException;

use function array_slice;
use function array_sum;
use function arsort;
use function count;
use function explode;
use function fclose;
use function fgets;
use function file_exists;
use function fopen;
use function in_array;
use function is_readable;
use function preg_match;
use function preg_split;
use function round;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * @phpstan-type Stats array{
 *     total_lines: int,
 *     functions_count: int,
 *     user_functions: int,
 *     internal_functions: int,
 *     total_calls: int,
 *     execution_time_ms: float,
 *     peak_memory_mb: float,
 *     file_io_operations: int,
 *     database_operations: int,
 *     bottleneck_functions: list<array{function: string, percentage: float, time_ms?: float}>,
 *     optimization_suggestions: list<string>
 * }
 */
final class CachegrindAnalyzer
{
    private const REAL_FILE_IO_FUNCTIONS = [
        'fopen',
        'fread',
        'fwrite',
        'fclose',
        'readfile',
    ];

    private const DB_FUNCTIONS = [
        'mysqli_query',
        'mysqli_prepare',
        'mysqli_execute',
        'mysqli_stmt_execute',
        'PDO::query',
        'PDO::prepare',
        'PDO::exec',
        'PDOStatement::execute',
        'mysql_query',
        'pg_query',
        'sqlite_query',
    ];

    /**
     * Analyze a Cachegrind profile file and return aggregated statistics.
     *
     * @return Stats
     */
    public function analyze(string $profileFile, string|null $includeVendor = null): array
    {
        if (! file_exists($profileFile) || ! is_readable($profileFile)) {
            throw new RuntimeException("Profile file not found or not readable: {$profileFile}");
        }

        /** @var Stats $stats */
        $stats = [
            'total_lines' => 0,
            'functions_count' => 0,
            'user_functions' => 0,
            'internal_functions' => 0,
            'total_calls' => 0,
            'execution_time_ms' => 0.0,
            'peak_memory_mb' => 0.0,
            'file_io_operations' => 0,
            'database_operations' => 0,
            'bottleneck_functions' => [],
            'optimization_suggestions' => [],
        ];

        $timeIndex = null;
        $memoryIndex = null;
        $timeToMs = null;
        $measuredTimeMs = null;
        $measuredMemoryMb = null;
        $eventsParsed = false;
        $summaryParsed = false;
        $newlineCount = 0;

        $uniqueFunctions = [];
        $userFunctions = [];
        $internalFunctions = [];
        $functionCosts = [];
        $currentFunction = null;
        $currentFile = null;
        $fileAliases = [];
        $functionAliases = [];

        $handle = fopen($profileFile, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open profile file: {$profileFile}");
        }

        while (($rawLine = fgets($handle)) !== false) {
            if (str_ends_with($rawLine, "\n")) {
                $newlineCount++;
            }

            $line = trim($rawLine);

            if (! $eventsParsed && str_starts_with($line, 'events:')) {
                $eventsParsed = true;
                $columns = preg_split('/\s+/', trim(substr($line, strlen('events:'))));
                if ($columns !== false) {
                    foreach ($columns as $i => $col) {
                        if (preg_match('/^Time_\((\d*)(ns|us|µs|ms|s)\)$/', $col, $unit)) {
                            $timeIndex = $i;
                            $multiplier = $unit[1] === '' ? 1 : (int) $unit[1];
                            $nsPerUnit = ['ns' => 1, 'us' => 1000, 'µs' => 1000, 'ms' => 1000000, 's' => 1000000000][$unit[2]];
                            $timeToMs = $multiplier * $nsPerUnit / 1000000.0;
                        } elseif (str_starts_with($col, 'Memory_(')) {
                            $memoryIndex = $i;
                        }
                    }
                }

                continue;
            }

            if (! $summaryParsed && str_starts_with($line, 'summary:')) {
                $summaryParsed = true;
                $summaryCols = preg_split('/\s+/', trim(substr($line, strlen('summary:'))));
                if ($summaryCols !== false) {
                    if ($timeIndex !== null && $timeToMs !== null && isset($summaryCols[$timeIndex])) {
                        $measuredTimeMs = round((int) $summaryCols[$timeIndex] * $timeToMs, 3);
                    }

                    if ($memoryIndex !== null && isset($summaryCols[$memoryIndex])) {
                        $measuredMemoryMb = round((int) $summaryCols[$memoryIndex] / 1048576, 2);
                    }
                }

                continue;
            }

            if (str_starts_with($line, 'calls=')) {
                $stats['total_calls']++;
                continue;
            }

            if (preg_match('/^fl=\((\d+)\)\s+(.+)$/', $line, $matches)) {
                $currentFile = $matches[2];
                $fileAliases[$matches[1]] = $currentFile;
                continue;
            }

            if (preg_match('/^fl=\((\d+)\)$/', $line, $matches)) {
                $currentFile = $fileAliases[$matches[1]] ?? null;
                continue;
            }

            if (strpos($line, 'fn=') === 0) {
                $currentFunction = substr($line, 3);

                // Callgrind name compression: fn=(N) name defines an alias,
                // a bare fn=(N) references it. Resolve aliases like fl= does.
                if (preg_match('/^\((\d+)\)\s+.+$/', $currentFunction, $defMatch)) {
                    $functionAliases[$defMatch[1]] = $currentFunction;
                } elseif (preg_match('/^\((\d+)\)$/', $currentFunction, $aliasMatch)) {
                    $currentFunction = $functionAliases[$aliasMatch[1]] ?? null;
                    if ($currentFunction === null) {
                        continue;
                    }
                }

                if (! $this->shouldIncludeProfileFile($currentFile, $includeVendor)) {
                    $currentFunction = null;
                    continue;
                }

                if (! isset($uniqueFunctions[$currentFunction])) {
                    $uniqueFunctions[$currentFunction] = true;
                    $functionName = $this->extractFunctionName($currentFunction);
                    if ($functionName === null) {
                        continue;
                    }

                    if (strpos($functionName, 'php::') === 0 || str_contains($functionName, '{main}') || str_contains($functionName, 'require')) {
                        $internalFunctions[$currentFunction] = true;
                    } else {
                        $userFunctions[$currentFunction] = true;
                    }

                    $this->classifyOperations($functionName, $stats);
                }
            }

            if (! $currentFunction || ! preg_match('/^\d+(?:\s+\d+)+$/', $line)) {
                continue;
            }

            $costCols = preg_split('/\s+/', $line);
            if ($costCols === false) {
                continue;
            }

            $timeCol = $timeIndex !== null ? $timeIndex + 1 : 1;
            $functionCosts[$currentFunction] = ($functionCosts[$currentFunction] ?? 0)
                + (int) ($costCols[$timeCol] ?? 0);
        }

        fclose($handle);

        $stats['total_lines'] = $newlineCount + 1;
        $stats['functions_count'] = count($uniqueFunctions);
        $stats['user_functions'] = count($userFunctions);
        $stats['internal_functions'] = count($internalFunctions);

        if (! empty($functionCosts)) {
            arsort($functionCosts);
            $stats['bottleneck_functions'] = $this->buildBottlenecks($functionCosts, $timeToMs);
            $stats['execution_time_ms'] = $measuredTimeMs
                ?? round(array_sum($functionCosts) / 50000, 2);

            if ($measuredMemoryMb !== null) {
                $stats['peak_memory_mb'] = $measuredMemoryMb;
            } else {
                $baseMemory = 2.5;
                $functionMemory = $stats['functions_count'] * 0.1;
                $callMemory = $stats['total_calls'] * 0.001;
                $stats['peak_memory_mb'] = round($baseMemory + $functionMemory + $callMemory, 1);
            }
        }

        return $stats;
    }

    private function shouldIncludeProfileFile(string|null $file, string|null $includeVendor): bool
    {
        if ($file === null || $file === '' || $file === 'php:internal') {
            return true;
        }

        $normalised = str_replace('\\', '/', $file);
        $vendorPos = strpos($normalised, '/vendor/');
        if ($vendorPos === false) {
            return true;
        }

        if ($includeVendor === null || trim($includeVendor) === '') {
            return false;
        }

        $relative = substr($normalised, $vendorPos + strlen('/vendor/'));
        $segments = explode('/', $relative);
        if (count($segments) < 2) {
            return VendorFilter::matchesPackage('*/*', $includeVendor);
        }

        return VendorFilter::matchesPackage($segments[0] . '/' . $segments[1], $includeVendor);
    }

    private function extractFunctionName(string $currentFunction): string|null
    {
        $functionName = $currentFunction;
        if (preg_match('/\(\d+\)\s+(.+)/', $functionName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /** @param Stats $stats */
    private function classifyOperations(string $functionName, array &$stats): void
    {
        if (str_contains($functionName, 'require') || str_contains($functionName, 'include')) {
            return;
        }

        $cleanFunctionName = $functionName;
        if (strpos($cleanFunctionName, 'php::') === 0) {
            $cleanFunctionName = substr($cleanFunctionName, 5);
        }

        $functionBase = explode('(', $cleanFunctionName)[0];
        $functionBase = explode('->', $functionBase)[0];

        if (in_array($functionBase, self::REAL_FILE_IO_FUNCTIONS, true)) {
            $stats['file_io_operations']++;
        }

        if (! in_array($functionBase, self::DB_FUNCTIONS, true)) {
            return;
        }

        $stats['database_operations']++;
    }

    /**
     * @param array<string, int> $functionCosts
     *
     * @return list<array{function: string, percentage: float, time_ms?: float}>
     */
    private function buildBottlenecks(array $functionCosts, float|null $timeToMs): array
    {
        $meaningfulFunctions = [];
        foreach ($functionCosts as $func => $cost) {
            if (preg_match('/^\(\d+\)$/', $func)) {
                continue;
            }

            $displayName = $func;
            if (preg_match('/\(\d+\)\s+(.+)/', $func, $matches)) {
                $displayName = $matches[1];
            }

            if (str_contains($displayName, 'require') || str_contains($displayName, 'include')) {
                continue;
            }

            $meaningfulFunctions[$displayName] = $cost;
        }

        $topFunctions = [];
        $totalCost = array_sum($functionCosts);
        if ($totalCost > 0) {
            foreach (array_slice($meaningfulFunctions, 0, 5, true) as $func => $cost) {
                $entry = [
                    'function' => $func,
                    'percentage' => round($cost / $totalCost * 100, 1),
                ];
                if ($timeToMs !== null) {
                    $entry['time_ms'] = round($cost * $timeToMs, 3);
                }

                $topFunctions[] = $entry;
            }
        }

        return $topFunctions;
    }
}
