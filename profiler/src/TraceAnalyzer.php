<?php

class XdebugTraceAnalyzerV2
{
    private string $traceFile;
    private array $analysisResult;
    private array $performanceData;
    private array $functionCallCounts;

    public function __construct(string $traceFile)
    {
        $this->traceFile = $traceFile;
        $this->analysisResult = [];
        $this->performanceData = [];
        $this->functionCallCounts = [];
    }

    public function analyzeTrace(string $context = ''): array
    {
        if (!file_exists($this->traceFile)) {
            throw new RuntimeException("Trace file not found: {$this->traceFile}");
        }

        $handle = fopen($this->traceFile, 'r');
        if (!$handle) {
            throw new RuntimeException("Cannot open trace file: {$this->traceFile}");
        }

        $this->initializeResult($context);
        $this->parseTraceFile($handle);
        fclose($handle);

        $this->performPerformanceAnalysis();
        $this->generateWarnings();

        return $this->analysisResult;
    }

    private function initializeResult(string $context): void
    {
        $fileSize = filesize($this->traceFile);
        $this->analysisResult = [
            '📊 metadata' => [
                '🕒 generated_at' => date('c'),
                '📁 source_trace_file' => $this->traceFile,
                '📏 source_file_size' => $this->formatBytes($fileSize),
                '📋 total_trace_lines' => 0,
                '⏱️ total_execution_time' => 0,
                '💾 peak_memory_usage' => 0,
                '🔍 analysis_version' => '2.0.0',
            ],
            '📈 statistics' => [
                '🔢 unique_functions_count' => 0,
                '📂 unique_files_count' => 0,
                '📞 total_function_calls' => 0,
                '📊 performance_entries_count' => 0,
                '🏗️ max_call_depth' => 0,
                '🏷️ vendor_vs_user' => [
                    '👤 user_functions' => 0,
                    '📦 vendor_functions' => 0,
                    '⚙️ internal_functions' => 0,
                ],
            ],
            '🚀 performance_analysis' => [
                '🐌 slowest_functions' => [],
                '💾 memory_intensive_functions' => [],
                '🔄 frequently_called_functions' => [],
                '⚠️ performance_warnings' => [],
            ],
            '🗂️ function_index' => [],
            '📂 file_index' => [],
            '🎯 analysis_context' => $context,
            '🔗 specification' => 'https://xdebug.org/docs/trace',
            '📋 schema' => 'https://bear.sunday/schemas/trace-analysis-schema.json',
        ];
    }

    private function parseTraceFile($handle): void
    {
        $functionIndex = [];
        $fileIndex = [];
        $performanceData = [];
        $callStack = [];
        $lineNumber = 0;
        $uniqueFunctions = [];
        $uniqueFiles = [];
        $functionCallCounts = [];

        // Skip header lines until TRACE START
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            if (strpos($line, 'TRACE START') !== false) {
                break;
            }
        }

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);

            if (strpos($line, 'TRACE END') !== false) {
                break;
            }

            if (empty($line)) {
                continue;
            }

            $parts = preg_split('/\t/', $line);
            if (count($parts) < 5) {
                continue;
            }

            $level = (int) $parts[0];
            $type = $parts[2];
            $time = (float) $parts[3];
            $memory = (int) $parts[4];

            // Update peak values
            $this->analysisResult['📊 metadata']['⏱️ total_execution_time'] = max(
                $this->analysisResult['📊 metadata']['⏱️ total_execution_time'],
                $time,
            );
            $this->analysisResult['📊 metadata']['💾 peak_memory_usage'] = max(
                $this->analysisResult['📊 metadata']['💾 peak_memory_usage'],
                $memory,
            );
            $this->analysisResult['📈 statistics']['🏗️ max_call_depth'] = max(
                $this->analysisResult['📈 statistics']['🏗️ max_call_depth'],
                $level,
            );

            if ($type === '0' && count($parts) >= 7) {
                // Function entry
                $functionName = $parts[5] ?? '';
                $userDefined = (int) ($parts[6] ?? 0);
                $filename = $parts[8] ?? '';
                $sourceLine = (int) ($parts[9] ?? 0);

                if (!empty($functionName)) {
                    $uniqueFunctions[$functionName] = true;
                    $functionCallCounts[$functionName] = ($functionCallCounts[$functionName] ?? 0) + 1;

                    // Count function types
                    if ($userDefined) {
                        if (strpos($filename, '/vendor/') !== false) {
                            $this->analysisResult['📈 statistics']['🏷️ vendor_vs_user']['📦 vendor_functions']++;
                        } else {
                            $this->analysisResult['📈 statistics']['🏷️ vendor_vs_user']['👤 user_functions']++;
                        }
                    } else {
                        $this->analysisResult['📈 statistics']['🏷️ vendor_vs_user']['⚙️ internal_functions']++;
                    }

                    // Function index entry
                    if (!isset($functionIndex[$functionName])) {
                        $functionIndex[$functionName] = [];
                    }
                    $functionIndex[$functionName][] = [
                        '📍 trace_line' => $lineNumber,
                        '🕒 timestamp' => $time,
                        '💾 memory_usage' => $memory,
                        '📂 file_path' => $filename,
                        '📏 source_line' => $sourceLine,
                        '🏗️ call_depth_level' => $level,
                    ];

                    // File index
                    if (!empty($filename)) {
                        $uniqueFiles[$filename] = true;
                        if (!isset($fileIndex[$filename])) {
                            $fileIndex[$filename] = [];
                        }
                        $fileIndex[$filename][] = [
                            '🏷️ function_name' => $functionName,
                            '📍 trace_line' => $lineNumber,
                            '🕒 timestamp' => $time,
                            '💾 memory_usage' => $memory,
                            '📏 source_line' => $sourceLine,
                        ];
                    }

                    // Track for performance analysis
                    $callStack[$level] = [
                        'function' => $functionName,
                        'start_time' => $time,
                        'start_memory' => $memory,
                        'start_line' => $lineNumber,
                        'file_path' => $filename,
                        'source_line' => $sourceLine,
                        'level' => $level,
                    ];

                    $this->analysisResult['📈 statistics']['📞 total_function_calls']++;
                }
            } elseif ($type === '1' && isset($callStack[$level])) {
                // Function exit
                $call = $callStack[$level];
                $duration = $time - $call['start_time'];
                $memoryDelta = $memory - $call['start_memory'];

                $performanceData[] = [
                    '🏷️ function_name' => $call['function'],
                    '⏱️ duration_seconds' => $duration,
                    '💾 memory_delta_bytes' => $memoryDelta,
                    '📊 memory_delta_formatted' => $this->formatBytes($memoryDelta, true),
                    '📍 trace_start_line' => $call['start_line'],
                    '📍 trace_end_line' => $lineNumber,
                    '🏗️ call_depth_level' => $level,
                    '📂 file_path' => $call['file_path'],
                    '📏 source_line' => $call['source_line'],
                ];

                unset($callStack[$level]);
                $this->analysisResult['📈 statistics']['📊 performance_entries_count']++;
            }
        }

        $this->analysisResult['📊 metadata']['📋 total_trace_lines'] = $lineNumber;
        $this->analysisResult['📈 statistics']['🔢 unique_functions_count'] = count($uniqueFunctions);
        $this->analysisResult['📈 statistics']['📂 unique_files_count'] = count($uniqueFiles);
        $this->analysisResult['🗂️ function_index'] = $functionIndex;
        $this->analysisResult['📂 file_index'] = $fileIndex;

        // Store performance data for later analysis
        $this->performanceData = $performanceData;
        $this->functionCallCounts = $functionCallCounts;
    }

    private function performPerformanceAnalysis(): void
    {
        $performanceData = $this->performanceData ?? [];

        // Sort by duration for slowest functions
        $slowestFunctions = $performanceData;
        usort($slowestFunctions, fn($a, $b) => $b['⏱️ duration_seconds'] <=> $a['⏱️ duration_seconds']);
        $this->analysisResult['🚀 performance_analysis']['🐌 slowest_functions'] = array_slice($slowestFunctions, 0, 50);

        // Sort by memory usage
        $memoryIntensive = $performanceData;
        usort($memoryIntensive, fn($a, $b) => $b['💾 memory_delta_bytes'] <=> $a['💾 memory_delta_bytes']);

        $memoryEntries = [];
        foreach (array_slice($memoryIntensive, 0, 30) as $entry) {
            $memoryEntries[] = [
                '🏷️ function_name' => $entry['🏷️ function_name'],
                '💾 memory_delta_bytes' => $entry['💾 memory_delta_bytes'],
                '📊 memory_delta_formatted' => $entry['📊 memory_delta_formatted'],
                '⏱️ duration_seconds' => $entry['⏱️ duration_seconds'],
                '🏗️ call_depth_level' => $entry['🏗️ call_depth_level'],
            ];
        }
        $this->analysisResult['🚀 performance_analysis']['💾 memory_intensive_functions'] = $memoryEntries;

        // Frequent function calls
        $callCounts = $this->functionCallCounts ?? [];
        arsort($callCounts);

        $frequentFunctions = [];
        foreach (array_slice($callCounts, 0, 25, true) as $functionName => $count) {
            // Calculate totals for this function
            $totalDuration = 0;
            $totalMemoryDelta = 0;
            foreach ($performanceData as $entry) {
                if ($entry['🏷️ function_name'] === $functionName) {
                    $totalDuration += $entry['⏱️ duration_seconds'];
                    $totalMemoryDelta += $entry['💾 memory_delta_bytes'];
                }
            }

            $frequentFunctions[] = [
                '🏷️ function_name' => $functionName,
                '🔢 call_count' => $count,
                '⏱️ total_duration_seconds' => $totalDuration,
                '📊 average_duration_seconds' => $count > 0 ? $totalDuration / $count : 0,
                '💾 total_memory_delta_bytes' => $totalMemoryDelta,
            ];
        }
        $this->analysisResult['🚀 performance_analysis']['🔄 frequently_called_functions'] = $frequentFunctions;
    }

    private function generateWarnings(): void
    {
        $warnings = [];
        $performanceData = $this->performanceData ?? [];

        // Check for slow functions
        foreach ($performanceData as $entry) {
            if ($entry['⏱️ duration_seconds'] > 0.5) {
                $warnings[] = [
                    '🏷️ type' => 'slow_function',
                    '📄 message' => sprintf(
                        'Function %s took %.3fs to execute',
                        $entry['🏷️ function_name'],
                        $entry['⏱️ duration_seconds'],
                    ),
                    '🎯 function_name' => $entry['🏷️ function_name'],
                    '📊 severity' => $entry['⏱️ duration_seconds'] > 2.0 ? 'high' : 'medium',
                    '💡 suggestion' => 'Consider profiling this function for optimization opportunities',
                ];
            }
        }

        // Check for memory intensive functions
        foreach ($performanceData as $entry) {
            if ($entry['💾 memory_delta_bytes'] > 5 * 1024 * 1024) { // > 5MB
                $warnings[] = [
                    '🏷️ type' => 'memory_leak',
                    '📄 message' => sprintf(
                        'Function %s allocated %s of memory',
                        $entry['🏷️ function_name'],
                        $entry['📊 memory_delta_formatted'],
                    ),
                    '🎯 function_name' => $entry['🏷️ function_name'],
                    '📊 severity' => $entry['💾 memory_delta_bytes'] > 10 * 1024 * 1024 ? 'high' : 'medium',
                    '💡 suggestion' => 'Review memory usage patterns and consider optimization',
                ];
            }
        }

        // Check for deep recursion
        $maxDepth = $this->analysisResult['📈 statistics']['🏗️ max_call_depth'];
        if ($maxDepth > 100) {
            $warnings[] = [
                '🏷️ type' => 'deep_recursion',
                '📄 message' => sprintf('Maximum call depth reached %d levels', $maxDepth),
                '📊 severity' => $maxDepth > 500 ? 'critical' : 'medium',
                '💡 suggestion' => 'Check for potential infinite recursion or optimize recursive algorithms',
            ];
        }

        // Check for excessive function calls
        $callCounts = $this->functionCallCounts ?? [];
        foreach ($callCounts as $functionName => $count) {
            if ($count > 1000) {
                $warnings[] = [
                    '🏷️ type' => 'excessive_calls',
                    '📄 message' => sprintf('Function %s was called %d times', $functionName, $count),
                    '🎯 function_name' => $functionName,
                    '📊 severity' => $count > 5000 ? 'high' : 'medium',
                    '💡 suggestion' => 'Consider caching or optimizing this frequently called function',
                ];
            }
        }

        $this->analysisResult['🚀 performance_analysis']['⚠️ performance_warnings'] = $warnings;
    }

    private function formatBytes(int $bytes, bool $withSign = false): string
    {
        $sign = '';
        if ($withSign && $bytes > 0) {
            $sign = '+';
        }

        $absBytes = abs($bytes);
        if ($absBytes >= 1073741824) {
            return $sign . round($absBytes / 1073741824, 2) . 'GB';
        } elseif ($absBytes >= 1048576) {
            return $sign . round($absBytes / 1048576, 2) . 'MB';
        } elseif ($absBytes >= 1024) {
            return $sign . round($absBytes / 1024, 2) . 'KB';
        }
        return $sign . $absBytes . 'B';
    }

    public function searchFunction(string $functionName): array
    {
        if (empty($this->analysisResult)) {
            throw new RuntimeException("Analysis not performed. Call analyzeTrace() first.");
        }

        $results = [];
        $functionIndex = $this->analysisResult['🗂️ function_index'];

        foreach ($functionIndex as $name => $calls) {
            if (stripos($name, $functionName) !== false) {
                $results[$name] = $calls;
            }
        }

        return $results;
    }

    public function exportJson(array $options = []): string
    {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        if ($options['compact'] ?? false) {
            $flags = JSON_UNESCAPED_SLASHES;
        }

        return json_encode($this->analysisResult, $flags);
    }

    public function validateSchema(): array
    {
        // Basic validation - in production this could use a JSON schema validator
        $errors = [];

        if (empty($this->analysisResult['📊 metadata']['📁 source_trace_file'])) {
            $errors[] = 'Missing source trace file in metadata';
        }

        if ($this->analysisResult['📈 statistics']['🔢 unique_functions_count'] < 0) {
            $errors[] = 'Invalid unique functions count';
        }

        return $errors;
    }
}

// CLI usage
if ($argc < 2) {
    echo json_encode([
        'error' => 'Missing arguments',
        'usage' => 'php trace-analyzer-v2.php <trace-file> [options]',
        'options' => [
            '--context="description"' => 'Add analysis context',
            '--compact' => 'Output compact JSON',
            '--search="function"' => 'Search for specific function',
            '--validate' => 'Validate result against schema',
        ],
    ], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$traceFile = $argv[1];
$options = [];
$context = '';
$searchFunction = '';

// Parse command line options
for ($i = 2; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--context=') === 0) {
        $context = substr($arg, 10);
    } elseif (strpos($arg, '--search=') === 0) {
        $searchFunction = substr($arg, 9);
    } elseif ($arg === '--compact') {
        $options['compact'] = true;
    } elseif ($arg === '--validate') {
        $options['validate'] = true;
    }
}

try {
    $analyzer = new XdebugTraceAnalyzerV2($traceFile);

    if (!empty($searchFunction)) {
        $result = $analyzer->analyzeTrace($context);
        $searchResults = $analyzer->searchFunction($searchFunction);
        echo json_encode([
            'search_query' => $searchFunction,
            'results' => $searchResults,
            'total_matches' => count($searchResults),
        ], JSON_PRETTY_PRINT) . "\n";
    } else {
        $result = $analyzer->analyzeTrace($context);

        if ($options['validate'] ?? false) {
            $validationErrors = $analyzer->validateSchema();
            if (!empty($validationErrors)) {
                echo json_encode([
                    'validation_errors' => $validationErrors,
                ], JSON_PRETTY_PRINT) . "\n";
                exit(1);
            }
        }

        echo $analyzer->exportJson($options) . "\n";
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace_file' => $traceFile,
    ], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
