<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_merge;
use function count;
use function escapeshellarg;
use function explode;
use function fclose;
use function fgets;
use function file_exists;
use function file_get_contents;
use function filemtime;
use function filesize;
use function fopen;
use function getenv;
use function glob;
use function gzclose;
use function gzdecode;
use function gzgets;
use function gzopen;
use function implode;
use function in_array;
use function ini_get;
use function is_readable;
use function max;
use function number_format;
use function passthru;
use function round;
use function shell_exec;
use function str_contains;
use function str_ends_with;
use function strtolower;
use function trim;
use function usort;

/**
 * AI-Native Xdebug trace data generator with comprehensive statistics
 *
 * Executes PHP scripts with Xdebug tracing enabled and provides detailed
 * execution analysis with accurate timing, memory, and function call data.
 */
class XdebugTracer
{
    private array $fileIOFunctions = [
        'file_get_contents',
        'file_put_contents',
        'fopen',
        'fread',
        'fwrite',
        'fclose',
        'glob',
        'scandir',
        'is_file',
        'file_exists',
        'is_dir',
        'mkdir',
        'rmdir',
        'copy',
        'rename',
        'unlink',
        'chmod',
        'touch',
        'readfile',
    ];
    private array $dbFunctions = [
        'mysqli_query',
        'mysqli_prepare',
        'mysqli_execute',
        'mysqli_stmt_execute',
        'PDO->query',
        'PDO->prepare',
        'PDO->exec',
        'PDOStatement->execute',
        'PDOStatement->fetchAll',
        'PDOStatement->fetch',
        'mysql_query',
        'pg_query',
        'sqlite_query',
    ];

    public function executeTrace(string $targetFile, array $phpArgs = []): string
    {
        if (! file_exists($targetFile)) {
            throw new InvalidArgumentException("Target file not found: $targetFile");
        }

        // Get Xdebug output directory
        $xdebugOutputDir = ini_get('xdebug.output_dir') ?: '/tmp';

        echo "🔍 Tracing: $targetFile\n";

        // Build command with Xdebug trace enabled (detailed mode)
        $prependFilter = __DIR__ . '/prepend_filter.php';
        $xdebugOptions = [
            '-dzend_extension=xdebug',
            '-dxdebug.mode=trace',
            '-dxdebug.start_with_request=yes',
            '-dxdebug.collect_params=4',
            '-dxdebug.collect_return=1',
            "-dxdebug.output_dir={$xdebugOutputDir}",
            '-dxdebug.trace_format=1',
            '-dxdebug.use_compression=0',
            "-dauto_prepend_file={$prependFilter}",
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

        // Find the created trace file (Xdebug generates its own filename)
        $traceFiles = glob("{$xdebugOutputDir}/trace.*.xt");
        if (empty($traceFiles)) {
            throw new RuntimeException('Trace file not created. Check Xdebug installation.');
        }

        // Get the most recent trace file
        usort($traceFiles, static function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $traceFiles[0];
    }

    public function parseTraceFile(string $traceFile): array
    {
        if (! file_exists($traceFile) || ! is_readable($traceFile)) {
            throw new InvalidArgumentException("Trace file not found or not readable: $traceFile");
        }

        $fileSize = filesize($traceFile);
        if ($fileSize === 0) {
            throw new RuntimeException("Trace file is empty: $traceFile");
        }

        // Initialize statistics
        $stats = [
            'file_path' => $traceFile,
            'file_size' => $fileSize,
            'total_lines' => 0,
            'function_calls' => 0,
            'user_function_calls' => 0,
            'internal_function_calls' => 0,
            'unique_functions' => [],
            'file_io_operations' => 0,
            'db_operations' => 0,
            'max_depth' => 0,
            'max_call_depth' => 0,  // Add missing key
            'peak_memory' => 0,
            'start_time' => null,
            'end_time' => 0,
            'execution_time' => 0,
        ];

        // Parse trace file line by line (handle both compressed and uncompressed)
        if (str_ends_with($traceFile, '.gz')) {
            $handle = gzopen($traceFile, 'r');
            if (! $handle) {
                throw new RuntimeException("Cannot open compressed trace file: $traceFile");
            }

            while (($line = gzgets($handle)) !== false) {
                $stats['total_lines']++;
                $this->parseTraceLine(trim($line), $stats);
            }

            gzclose($handle);
        } else {
            $handle = fopen($traceFile, 'r');
            if (! $handle) {
                throw new RuntimeException("Cannot open trace file: $traceFile");
            }

            while (($line = fgets($handle)) !== false) {
                $stats['total_lines']++;
                $this->parseTraceLine(trim($line), $stats);
            }

            fclose($handle);
        }

        // Calculate execution time
        if ($stats['start_time'] !== null) {
            $stats['execution_time'] = $stats['end_time'] - $stats['start_time'];
        }

        return $stats;
    }

    private function parseTraceLine(string $line, array &$stats): void
    {
        $parts = explode("\t", $line);
        if (count($parts) < 6) {
            return;
        }

        $level = (int) $parts[0];
        $entryExit = $parts[2]; // 0=Entry, 1=Exit, R=Return
        $time = (float) $parts[3];
        $memory = (int) $parts[4];
        $function = $parts[5] ?? '';

        // Only count function entries (not exits or returns)
        if ($entryExit === '0') {
            $stats['function_calls']++;

            // Check if user-defined (1) or internal (0) function
            $isUserDefined = (int) ($parts[6] ?? 0);
            if ($isUserDefined === 1) {
                $stats['user_function_calls']++;
            } else {
                $stats['internal_function_calls']++;
            }

            // Track unique functions
            if ($function && $function !== '') {
                $stats['unique_functions'][$function] = true;

                // Count file I/O operations
                if (in_array($function, $this->fileIOFunctions, true)) {
                    $stats['file_io_operations']++;
                }

                // Count database operations
                if (in_array($function, $this->dbFunctions, true)) {
                    $stats['db_operations']++;
                }
            }
        }

        // Track max depth (from all lines)
        $stats['max_depth'] = max($stats['max_depth'], $level);

        // Track peak memory (from all lines)
        $stats['peak_memory'] = max($stats['peak_memory'], $memory);

        // Track timing (from all lines)
        if ($stats['start_time'] === null) {
            $stats['start_time'] = $time;
        }

        $stats['end_time'] = $time;
    }

    /**
     * Generate AI-optimized trace data with strategic metadata
     *
     * @param string $targetFile PHP file to trace
     * @param array  $phpArgs    Additional arguments for PHP script
     *
     * @return array JSON structure conforming to xdebug-trace schema
     */
    public function generateTraceData(string $targetFile, array $phpArgs = []): array
    {
        $traceFile = $this->executeTrace($targetFile, $phpArgs);
        $stats = $this->parseTraceFile($traceFile);

        return [
            'trace_file' => $traceFile,
            'total_lines' => $stats['total_lines'],
            'specification' => 'https://xdebug.org/docs/trace',
        ];
    }

    /**
     * Generate comprehensive trace statistics from existing trace file
     * Used by both standalone trace analysis and debug output
     */
    public function generateTraceStatistics(string $traceFile): array
    {
        if (! file_exists($traceFile)) {
            throw new RuntimeException("Trace file not found: $traceFile");
        }

        $stats = $this->parseTraceFile($traceFile);

        // Handle both compressed and uncompressed trace files
        if (str_ends_with($traceFile, '.gz')) {
            $content = array_filter(explode("\n", gzdecode(file_get_contents($traceFile))), 'trim');
        } else {
            $content = array_filter(explode("\n", file_get_contents($traceFile)), 'trim');
        }

        return [
            // Compatibility with debug schema (old format)
            'file' => $traceFile,
            'content' => $content,

            // Full trace schema compliance (new format)
            'trace_file' => $traceFile,
            'total_lines' => $stats['total_lines'],
            'unique_functions' => count($stats['unique_functions']),
            'max_call_depth' => $stats['max_call_depth'],
            'database_queries' => $this->countDatabaseQueries($stats),
            'specification' => 'https://xdebug.org/docs/trace',
        ];
    }

    /**
     * Count database queries in trace statistics
     */
    private function countDatabaseQueries(array $stats): int
    {
        $dbQueryCount = 0;
        foreach ($stats['unique_functions'] as $function => $unused) {
            if (
                str_contains(strtolower($function), 'query') ||
                str_contains(strtolower($function), 'execute') ||
                str_contains(strtolower($function), 'prepare')
            ) {
                $dbQueryCount++;
            }
        }

        return $dbQueryCount;
    }

    public function generateStatistics(array $stats): array
    {
        return [
            'file_path' => $stats['file_path'],
            'total_lines' => number_format($stats['total_lines']),
            'function_calls' => number_format($stats['function_calls']),
            'user_function_calls' => number_format($stats['user_function_calls']),
            'internal_function_calls' => number_format($stats['internal_function_calls']),
            'file_io_operations' => $stats['file_io_operations'],
            'db_operations' => $stats['db_operations'],
            'execution_time_ms' => round($stats['execution_time'] * 1000),
            'peak_memory_mb' => round($stats['peak_memory'] / 1024 / 1024, 1),
            'unique_function_count' => number_format(count($stats['unique_functions'])),
            'max_depth' => $stats['max_depth'],
        ];
    }

    public function displayResults(array $stats): void
    {
        echo "✅ Trace complete: {$stats['file_path']}\n";
        echo "📊 {$stats['total_lines']} lines generated\n";
        echo "📞 {$stats['function_calls']} function calls ({$stats['user_function_calls']} user + {$stats['internal_function_calls']} internal)\n";
        echo "📂 {$stats['file_io_operations']} file I/O operations\n";
        echo "🗃️ {$stats['db_operations']} database queries\n";

        $executionTimeMs = round($stats['execution_time'] * 1000);
        echo "⏱️ {$executionTimeMs}ms execution time\n";

        $peakMemoryMb = round($stats['peak_memory'] / 1024 / 1024, 1);
        echo "🧠 {$peakMemoryMb}MB peak memory\n";

        $uniqueFunctionCount = count($stats['unique_functions']);
        echo "📚 {$uniqueFunctionCount} unique functions\n";

        echo "🔄 {$stats['max_depth']} max call depth\n";
    }

    public function analyzeWithClaude(string $traceFile): void
    {
        $languageOutput = shell_exec('defaults read -g AppleLanguages') ?: (getenv('LANG') ?: getenv('LC_ALL') ?: '');
        $lang = str_contains($languageOutput, 'ja') ? 'Japanese' : 'English';
        $claudePrompt = "Analyze this Xdebug trace file for code quality across multiple dimensions: 1) Security vulnerabilities (SQL injection, XSS, unsafe operations), 2) Performance efficiency (N+1 queries, redundant operations, memory leaks), 3) Code principles violations (DRY, SOLID, separation of concerns), 4) Execution patterns and debugging insights. Focus especially on AI/Junior developer code that passes tests but has hidden quality issues: $traceFile. Answer in $lang.";

        echo "\n🤖 Starting Claude Code analysis...\n";
        passthru('claude ' . escapeshellarg($claudePrompt));
        echo "\n";
        echo "🤖 Claude Code analysis completed.\n";
    }
}
