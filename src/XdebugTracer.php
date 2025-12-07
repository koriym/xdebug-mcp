<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\DTO\TraceStatistics;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use RuntimeException;

use function array_filter;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unshift;
use function count;
use function dirname;
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
use function preg_replace;
use function round;
use function shell_exec;
use function sprintf;
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
    /** @var list<string> */
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

    /** @var list<string> */
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

    /**
     * @param list<string> $phpArgs
     */
    public function executeTrace(string $targetFile, array $phpArgs = []): string
    {
        if (! file_exists($targetFile)) {
            throw new InvalidArgumentException("Target file not found: $targetFile");
        }

        // Get Xdebug output directory
        $xdebugOutputDir = ini_get('xdebug.output_dir') ?: '/tmp';
        // Get current trace_output_name setting
        $traceOutputName = ini_get('xdebug.trace_output_name') ?: 'trace.%c';

        echo "🔍 Tracing: $targetFile\n";

        // Build command with Xdebug trace enabled (detailed mode)
        $prependFilter = dirname(__DIR__) . '/prepend_filter.php';

        // Get appropriate Xdebug flag (empty if already loaded)
        $xdebugFlag = XdebugFinder::getXdebugFlag();

        $xdebugOptions = [
            '-dxdebug.mode=trace',
            '-dxdebug.collect_params=4',
            '-dxdebug.collect_return=1',
            "-dxdebug.output_dir={$xdebugOutputDir}",
            "-dxdebug.trace_output_name={$traceOutputName}",
            '-dxdebug.trace_format=1',
            '-dxdebug.use_compression=0',
            "-dauto_prepend_file={$prependFilter}",
        ];

        // Add Xdebug extension flag if needed
        if ($xdebugFlag !== '') {
            array_unshift($xdebugOptions, trim($xdebugFlag));
        }

        // Combine all arguments
        $allArgs = array_merge($xdebugOptions, [$targetFile], $phpArgs);
        $cmd = 'php ' . implode(' ', array_map(escapeshellarg(...), $allArgs));

        // Execute with passthru to show output
        $exitCode = 0;
        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("PHP execution failed with exit code: $exitCode");
        }

        // Find the created trace file using dynamic pattern detection
        // First escape special glob characters to prevent unintended matches
        $escapedTraceOutputName = preg_replace('/([*?\[\]])/', '\\\\$1', $traceOutputName);

        // Convert Xdebug format specifiers to glob wildcards
        // %c=CRC32, %p=PID, %r=Random, %s=Script, %t=Timestamp, %u=Microseconds, etc.
        // @see https://xdebug.org/docs/trace#trace_output_name
        $filePattern = preg_replace('/%(c|p|r|s|t|u|H|R|U|S)/', '*', (string) $escapedTraceOutputName);

        // Find trace files using dynamic pattern
        $traceFiles = glob("{$xdebugOutputDir}/{$filePattern}.xt");
        if ($traceFiles === [] || $traceFiles === false) {
            throw new RuntimeException(sprintf(
                'Trace file not found. Looked in "%s" with pattern based on trace_output_name "%s".',
                $xdebugOutputDir,
                $traceOutputName,
            ));
        }

        // Get the most recent trace file
        usort($traceFiles, static fn($a, $b): int => filemtime($b) - filemtime($a));

        return $traceFiles[0];
    }

    public function parseTraceFile(string $traceFile): TraceStatistics
    {
        if (! file_exists($traceFile) || ! is_readable($traceFile)) {
            throw new InvalidArgumentException("Trace file not found or not readable: $traceFile");
        }

        $fileSize = filesize($traceFile);
        if ($fileSize === 0 || $fileSize === false) {
            throw new RuntimeException("Trace file is empty: $traceFile");
        }

        // Initialize statistics using DTO
        $stats = new TraceStatistics(
            filePath: $traceFile,
            fileSize: $fileSize,
        );

        // Parse trace file line by line (handle both compressed and uncompressed)
        if (str_ends_with($traceFile, '.gz')) {
            $handle = gzopen($traceFile, 'r');
            if (! $handle) {
                throw new RuntimeException("Cannot open compressed trace file: $traceFile");
            }

            while (($line = gzgets($handle)) !== false) {
                $stats->incrementTotalLines();
                $this->parseTraceLine(trim($line), $stats);
            }

            gzclose($handle);
        } else {
            $handle = fopen($traceFile, 'r');
            if (! $handle) {
                throw new RuntimeException("Cannot open trace file: $traceFile");
            }

            while (($line = fgets($handle)) !== false) {
                $stats->incrementTotalLines();
                $this->parseTraceLine(trim($line), $stats);
            }

            fclose($handle);
        }

        // Calculate execution time
        $stats->calculateExecutionTime();

        return $stats;
    }

    private function parseTraceLine(string $line, TraceStatistics $stats): void
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
            $stats->incrementFunctionCalls();

            // Check if user-defined (1) or internal (0) function
            $isUserDefined = (int) ($parts[6] ?? 0);
            if ($isUserDefined === 1) {
                $stats->incrementUserFunctionCalls();
            } else {
                $stats->incrementInternalFunctionCalls();
            }

            // Track unique functions
            if ($function !== '') {
                $stats->trackFunction($function);

                // Count file I/O operations
                if (in_array($function, $this->fileIOFunctions, true)) {
                    $stats->incrementFileIoOperations();
                }

                // Count database operations
                if (in_array($function, $this->dbFunctions, true)) {
                    $stats->incrementDbOperations();
                }
            }
        }

        // Track max depth (from all lines)
        $stats->updateMaxDepth($level);

        // Track peak memory (from all lines)
        $stats->updatePeakMemory($memory);

        // Track timing (from all lines)
        $stats->setStartTime($time);
        $stats->setEndTime($time);
    }

    /**
     * Generate AI-optimized trace data with strategic metadata
     *
     * @param list<string> $phpArgs Additional arguments for PHP script
     *
     * @return array{trace_file: string, total_lines: int, specification: string}
     */
    public function generateTraceData(string $targetFile, array $phpArgs = []): array
    {
        $traceFile = $this->executeTrace($targetFile, $phpArgs);
        $stats = $this->parseTraceFile($traceFile);

        return [
            'trace_file' => $traceFile,
            'total_lines' => $stats->totalLines,
            'specification' => 'https://xdebug.org/docs/trace',
        ];
    }

    /**
     * Generate comprehensive trace statistics from existing trace file
     * Used by both standalone trace analysis and debug output
     *
     * @return array{file: string, content: list<string>, trace_file: string, total_lines: int, unique_functions: int, max_call_depth: int, database_queries: int, specification: string}
     */
    public function generateTraceStatistics(string $traceFile): array
    {
        if (! file_exists($traceFile)) {
            throw new RuntimeException("Trace file not found: $traceFile");
        }

        $stats = $this->parseTraceFile($traceFile);

        // Handle both compressed and uncompressed trace files
        $filterNonEmpty = static fn(string $line): bool => trim($line) !== '';
        if (str_ends_with($traceFile, '.gz')) {
            $content = array_filter(explode("\n", (string) gzdecode((string) file_get_contents($traceFile))), $filterNonEmpty);
        } else {
            $content = array_filter(explode("\n", (string) file_get_contents($traceFile)), $filterNonEmpty);
        }

        return [
            // Compatibility with debug schema (old format)
            'file' => $traceFile,
            'content' => array_values($content),

            // Full trace schema compliance (new format)
            'trace_file' => $traceFile,
            'total_lines' => $stats->totalLines,
            'unique_functions' => $stats->getUniqueFunctionCount(),
            'max_call_depth' => $stats->maxCallDepth,
            'database_queries' => $this->countDatabaseQueries($stats),
            'specification' => 'https://xdebug.org/docs/trace',
        ];
    }

    private function countDatabaseQueries(TraceStatistics $stats): int
    {
        $dbQueryCount = 0;
        foreach (array_keys($stats->uniqueFunctions) as $function) {
            if (
                str_contains(strtolower($function), 'query')
                || str_contains(strtolower($function), 'execute')
                || str_contains(strtolower($function), 'prepare')
            ) {
                $dbQueryCount++;
            }
        }

        return $dbQueryCount;
    }

    /**
     * @return array{file_path: string, total_lines: string, function_calls: string, user_function_calls: string, internal_function_calls: string, file_io_operations: int, db_operations: int, execution_time_ms: float, peak_memory_mb: float, unique_function_count: string, max_depth: int}
     */
    public function generateStatistics(TraceStatistics $stats): array
    {
        return [
            'file_path' => $stats->filePath,
            'total_lines' => number_format($stats->totalLines),
            'function_calls' => number_format($stats->functionCalls),
            'user_function_calls' => number_format($stats->userFunctionCalls),
            'internal_function_calls' => number_format($stats->internalFunctionCalls),
            'file_io_operations' => $stats->fileIoOperations,
            'db_operations' => $stats->dbOperations,
            'execution_time_ms' => round($stats->executionTime * 1000),
            'peak_memory_mb' => round($stats->peakMemory / 1024 / 1024, 1),
            'unique_function_count' => number_format($stats->getUniqueFunctionCount()),
            'max_depth' => $stats->maxDepth,
        ];
    }

    public function displayResults(TraceStatistics $stats): void
    {
        echo "✅ Trace complete: {$stats->filePath}\n";
        echo "📊 {$stats->totalLines} lines generated\n";
        echo "📞 {$stats->functionCalls} function calls ({$stats->userFunctionCalls} user + {$stats->internalFunctionCalls} internal)\n";
        echo "📂 {$stats->fileIoOperations} file I/O operations\n";
        echo "🗃️ {$stats->dbOperations} database queries\n";

        $executionTimeMs = round($stats->executionTime * 1000);
        echo "⏱️ {$executionTimeMs}ms execution time\n";

        $peakMemoryMb = round($stats->peakMemory / 1024 / 1024, 1);
        echo "🧠 {$peakMemoryMb}MB peak memory\n";

        $uniqueFunctionCount = $stats->getUniqueFunctionCount();
        echo "📚 {$uniqueFunctionCount} unique functions\n";

        echo "🔄 {$stats->maxDepth} max call depth\n";
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
