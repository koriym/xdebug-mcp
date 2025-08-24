<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

/**
 * Xdebug trace file profiler and analyzer
 * 
 * Executes PHP scripts with Xdebug tracing enabled and analyzes the results.
 * Provides comprehensive statistics including function calls, I/O operations, memory usage, and timing.
 */
class XdebugProfiler
{
    private array $fileIOFunctions = [
        'file_get_contents', 'file_put_contents', 'fopen', 'fread', 'fwrite', 'fclose',
        'glob', 'scandir', 'is_file', 'file_exists', 'is_dir', 'mkdir', 'rmdir',
        'copy', 'rename', 'unlink', 'chmod', 'touch', 'readfile'
        // require/include excluded (uses OPcache)
    ];

    private array $dbFunctions = [
        'mysqli_query', 'mysqli_prepare', 'mysqli_execute', 'mysqli_stmt_execute',
        'PDO::query', 'PDO::prepare', 'PDO::exec', 'PDOStatement::execute',
        'mysql_query', 'pg_query', 'sqlite_query'
    ];

    public function executeTrace(string $targetFile, array $phpArgs = []): string
    {
        if (!file_exists($targetFile)) {
            throw new \InvalidArgumentException("Target file not found: $targetFile");
        }

        // Get Xdebug output directory
        $xdebugOutputDir = ini_get('xdebug.output_dir') ?: '/tmp';

        echo "🔍 Tracing: $targetFile\n";

        // Build command with Xdebug trace enabled (detailed mode)
        $xdebugOptions = [
            '-dzend_extension=xdebug',
            '-dxdebug.mode=trace',
            '-dxdebug.start_with_request=yes',
            '-dxdebug.collect_params=4',
            '-dxdebug.collect_return=1',
            "-dxdebug.output_dir={$xdebugOutputDir}",
            '-dxdebug.trace_format=1',
            '-dxdebug.use_compression=0',
        ];

        // Combine all arguments
        $allArgs = array_merge($xdebugOptions, [$targetFile], $phpArgs);
        $cmd = 'php ' . implode(' ', array_map('escapeshellarg', $allArgs));

        // Execute with passthru to show output
        $exitCode = 0;
        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException("PHP execution failed with exit code: $exitCode");
        }

        // Find the created trace file (Xdebug generates its own filename)
        $traceFiles = glob("{$xdebugOutputDir}/trace.*.xt");
        if (empty($traceFiles)) {
            throw new \RuntimeException("Trace file not created. Check Xdebug installation.");
        }

        // Get the most recent trace file
        usort($traceFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $traceFiles[0];
    }

    public function parseTraceFile(string $traceFile): array
    {
        if (!file_exists($traceFile) || !is_readable($traceFile)) {
            throw new \InvalidArgumentException("Trace file not found or not readable: $traceFile");
        }

        $fileSize = filesize($traceFile);
        if ($fileSize === 0) {
            throw new \RuntimeException("Trace file is empty: $traceFile");
        }

        $lines = count(file($traceFile));
        $content = file_get_contents($traceFile);
        $traceLines = explode("\n", $content);

        $stats = [
            'file_path' => $traceFile,
            'file_size' => $fileSize,
            'total_lines' => $lines,
            'function_calls' => 0,
            'user_function_calls' => 0,
            'internal_function_calls' => 0,
            'file_io_operations' => 0,
            'db_operations' => 0,
            'execution_time' => 0,
            'peak_memory' => 0,
            'unique_functions' => [],
            'max_depth' => 0,
            'start_time' => null,
            'end_time' => null
        ];

        foreach ($traceLines as $line) {
            if (preg_match('/^\d+\t/', $line)) {
                $this->parseTraceLine($line, $stats);
            } elseif (preg_match('/TRACE START \[([\d-]+ [\d:]+\.\d+)\]/', $line, $matches)) {
                $stats['start_time'] = $this->parseTimestamp($matches[1]);
            } elseif (preg_match('/TRACE END\s+\[([\d-]+ [\d:]+\.\d+)\]/', $line, $matches)) {
                $stats['end_time'] = $this->parseTimestamp($matches[1]);
            }
        }

        // Calculate execution time
        if ($stats['start_time'] && $stats['end_time']) {
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

        $level = (int)$parts[0];
        $entryExit = (int)$parts[2]; // 0=Entry, 1=Exit
        $time = (float)$parts[3];
        $memory = (int)$parts[4];
        $function = $parts[5] ?? '';

        // Only count function entries (not exits or returns)
        if ($entryExit === 0) {
            $stats['function_calls']++;
            
            // Check if user-defined (1) or internal (0) function
            $isUserDefined = (int)($parts[6] ?? 0);
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

    private function parseTimestamp(string $timestamp): float
    {
        // Parse timestamp with microseconds: "2025-08-24 12:02:19.999751"
        return (float)strtotime(substr($timestamp, 0, 19)) + (float)('0.' . substr($timestamp, 20));
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
            'max_depth' => $stats['max_depth']
        ];
    }

    public function displayResults(array $stats): void
    {
        echo "✅ Trace complete: {$stats['file_path']}\n";
        echo "📊 {$stats['total_lines']} lines generated\n";
        echo "📞 {$stats['function_calls']} function calls ({$stats['user_function_calls']} user + {$stats['internal_function_calls']} internal)\n";
        echo "📂 {$stats['file_io_operations']} file I/O operations\n";
        echo "🗃️ {$stats['db_operations']} database queries\n";
        echo "⏱️ {$stats['execution_time_ms']}ms execution time\n";
        echo "🧠 {$stats['peak_memory_mb']}MB peak memory\n";
        echo "📚 {$stats['unique_function_count']} unique functions\n";
        echo "🔄 {$stats['max_depth']} max call depth\n";
    }
}