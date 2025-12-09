<?php

/**
 * Trace Analysis Utilities
 *
 * Provides additional utilities for working with trace analysis results
 */
class TraceUtils
{
    public static function compareTwoAnalyses(array $analysis1, array $analysis2): array
    {
        return [
            '📊 comparison_metadata' => [
                '🕒 compared_at' => date('c'),
                '📁 trace1_file' => $analysis1['📊 metadata']['📁 source_trace_file'] ?? 'unknown',
                '📁 trace2_file' => $analysis2['📊 metadata']['📁 source_trace_file'] ?? 'unknown',
                '🔍 comparison_version' => '1.0.0',
            ],
            '📈 execution_time_diff' => [
                '⏱️ trace1_time' => $analysis1['📊 metadata']['⏱️ total_execution_time'] ?? 0,
                '⏱️ trace2_time' => $analysis2['📊 metadata']['⏱️ total_execution_time'] ?? 0,
                '📊 time_difference' => ($analysis2['📊 metadata']['⏱️ total_execution_time'] ?? 0)
                                      - ($analysis1['📊 metadata']['⏱️ total_execution_time'] ?? 0),
                '📊 percentage_change' => self::calculatePercentageChange(
                    $analysis1['📊 metadata']['⏱️ total_execution_time'] ?? 0,
                    $analysis2['📊 metadata']['⏱️ total_execution_time'] ?? 0,
                ),
            ],
            '💾 memory_usage_diff' => [
                '💾 trace1_memory' => $analysis1['📊 metadata']['💾 peak_memory_usage'] ?? 0,
                '💾 trace2_memory' => $analysis2['📊 metadata']['💾 peak_memory_usage'] ?? 0,
                '📊 memory_difference' => ($analysis2['📊 metadata']['💾 peak_memory_usage'] ?? 0)
                                        - ($analysis1['📊 metadata']['💾 peak_memory_usage'] ?? 0),
                '📊 percentage_change' => self::calculatePercentageChange(
                    $analysis1['📊 metadata']['💾 peak_memory_usage'] ?? 0,
                    $analysis2['📊 metadata']['💾 peak_memory_usage'] ?? 0,
                ),
            ],
            '🔢 function_changes' => [
                '🔢 trace1_functions' => $analysis1['📈 statistics']['🔢 unique_functions_count'] ?? 0,
                '🔢 trace2_functions' => $analysis2['📈 statistics']['🔢 unique_functions_count'] ?? 0,
                '📊 function_difference' => ($analysis2['📈 statistics']['🔢 unique_functions_count'] ?? 0)
                                          - ($analysis1['📈 statistics']['🔢 unique_functions_count'] ?? 0),
            ],
            '🚀 performance_comparison' => self::comparePerformance($analysis1, $analysis2),
            '⚠️ warning_changes' => self::compareWarnings($analysis1, $analysis2),
        ];
    }

    private static function calculatePercentageChange(float $old, float $new): float
    {
        if ($old == 0) {
            return $new > 0 ? 100.0 : 0.0;
        }
        return (($new - $old) / $old) * 100;
    }

    private static function comparePerformance(array $analysis1, array $analysis2): array
    {
        $slowest1 = $analysis1['🚀 performance_analysis']['🐌 slowest_functions'] ?? [];
        $slowest2 = $analysis2['🚀 performance_analysis']['🐌 slowest_functions'] ?? [];

        $improvements = [];
        $regressions = [];

        // Create lookup for trace1 functions
        $trace1Lookup = [];
        foreach ($slowest1 as $func) {
            $trace1Lookup[$func['🏷️ function_name']] = $func['⏱️ duration_seconds'];
        }

        // Compare with trace2 functions
        foreach ($slowest2 as $func) {
            $funcName = $func['🏷️ function_name'];
            $newDuration = $func['⏱️ duration_seconds'];

            if (isset($trace1Lookup[$funcName])) {
                $oldDuration = $trace1Lookup[$funcName];
                $change = $newDuration - $oldDuration;

                if ($change < -0.001) { // Improvement
                    $improvements[] = [
                        '🏷️ function_name' => $funcName,
                        '⏱️ old_duration' => $oldDuration,
                        '⏱️ new_duration' => $newDuration,
                        '📊 improvement_seconds' => -$change,
                        '📊 improvement_percentage' => self::calculatePercentageChange($oldDuration, $newDuration),
                    ];
                } elseif ($change > 0.001) { // Regression
                    $regressions[] = [
                        '🏷️ function_name' => $funcName,
                        '⏱️ old_duration' => $oldDuration,
                        '⏱️ new_duration' => $newDuration,
                        '📊 regression_seconds' => $change,
                        '📊 regression_percentage' => self::calculatePercentageChange($oldDuration, $newDuration),
                    ];
                }
            }
        }

        // Sort improvements and regressions by impact
        usort($improvements, fn($a, $b) => $b['📊 improvement_seconds'] <=> $a['📊 improvement_seconds']);
        usort($regressions, fn($a, $b) => $b['📊 regression_seconds'] <=> $a['📊 regression_seconds']);

        return [
            '✅ improvements' => array_slice($improvements, 0, 10),
            '❌ regressions' => array_slice($regressions, 0, 10),
            '📊 summary' => [
                'total_improvements' => count($improvements),
                'total_regressions' => count($regressions),
                'net_change' => count($improvements) - count($regressions),
            ],
        ];
    }

    private static function compareWarnings(array $analysis1, array $analysis2): array
    {
        $warnings1 = $analysis1['🚀 performance_analysis']['⚠️ performance_warnings'] ?? [];
        $warnings2 = $analysis2['🚀 performance_analysis']['⚠️ performance_warnings'] ?? [];

        $new_warnings = [];
        $resolved_warnings = [];

        // Find warnings by type and function
        $warnings1Map = [];
        foreach ($warnings1 as $warning) {
            $key = ($warning['🏷️ type'] ?? 'unknown') . '::' . ($warning['🎯 function_name'] ?? 'global');
            $warnings1Map[$key] = $warning;
        }

        $warnings2Map = [];
        foreach ($warnings2 as $warning) {
            $key = ($warning['🏷️ type'] ?? 'unknown') . '::' . ($warning['🎯 function_name'] ?? 'global');
            $warnings2Map[$key] = $warning;
        }

        // Find new warnings (in trace2 but not in trace1)
        foreach ($warnings2Map as $key => $warning) {
            if (!isset($warnings1Map[$key])) {
                $new_warnings[] = $warning;
            }
        }

        // Find resolved warnings (in trace1 but not in trace2)
        foreach ($warnings1Map as $key => $warning) {
            if (!isset($warnings2Map[$key])) {
                $resolved_warnings[] = $warning;
            }
        }

        return [
            '🆕 new_warnings' => $new_warnings,
            '✅ resolved_warnings' => $resolved_warnings,
            '📊 summary' => [
                'total_new' => count($new_warnings),
                'total_resolved' => count($resolved_warnings),
                'net_change' => count($new_warnings) - count($resolved_warnings),
            ],
        ];
    }

    public static function extractTopBottlenecks(array $analysis, int $limit = 10): array
    {
        $bottlenecks = [];

        $slowFunctions = array_slice(
            $analysis['🚀 performance_analysis']['🐌 slowest_functions'] ?? [],
            0,
            $limit,
        );

        foreach ($slowFunctions as $func) {
            $bottlenecks[] = [
                '🏷️ function_name' => $func['🏷️ function_name'],
                '⏱️ duration_seconds' => $func['⏱️ duration_seconds'],
                '💾 memory_delta_formatted' => $func['📊 memory_delta_formatted'] ?? '0B',
                '🏗️ call_depth_level' => $func['🏗️ call_depth_level'],
                '💡 optimization_priority' => self::calculateOptimizationPriority($func),
                '📊 impact_score' => self::calculateImpactScore($func),
            ];
        }

        return $bottlenecks;
    }

    private static function calculateOptimizationPriority(array $func): string
    {
        $duration = $func['⏱️ duration_seconds'] ?? 0;
        $memoryDelta = abs($func['💾 memory_delta_bytes'] ?? 0);

        if ($duration > 1.0 || $memoryDelta > 10 * 1024 * 1024) {
            return 'critical';
        } elseif ($duration > 0.5 || $memoryDelta > 5 * 1024 * 1024) {
            return 'high';
        } elseif ($duration > 0.1 || $memoryDelta > 1024 * 1024) {
            return 'medium';
        }
        return 'low';
    }

    private static function calculateImpactScore(array $func): float
    {
        $duration = $func['⏱️ duration_seconds'] ?? 0;
        $memoryDelta = abs($func['💾 memory_delta_bytes'] ?? 0);

        // Weighted score: time is 70%, memory is 30%
        $timeScore = min($duration * 10, 100); // Cap at 100
        $memoryScore = min(($memoryDelta / (1024 * 1024)) * 5, 100); // Cap at 100

        return round(($timeScore * 0.7) + ($memoryScore * 0.3), 2);
    }

    public static function generateSummaryReport(array $analysis): array
    {
        $metadata = $analysis['📊 metadata'] ?? [];
        $stats = $analysis['📈 statistics'] ?? [];
        $performance = $analysis['🚀 performance_analysis'] ?? [];

        $topBottlenecks = self::extractTopBottlenecks($analysis, 5);
        $criticalWarnings = array_filter(
            $performance['⚠️ performance_warnings'] ?? [],
            fn($w) => ($w['📊 severity'] ?? '') === 'critical',
        );

        return [
            '📋 executive_summary' => [
                '⏱️ total_execution_time' => $metadata['⏱️ total_execution_time'] ?? 0,
                '💾 peak_memory_usage_formatted' => self::formatBytes($metadata['💾 peak_memory_usage'] ?? 0),
                '🔢 functions_analyzed' => $stats['🔢 unique_functions_count'] ?? 0,
                '📊 performance_score' => self::calculateOverallPerformanceScore($analysis),
                '⚠️ critical_issues_count' => count($criticalWarnings),
            ],
            '🎯 top_optimization_targets' => $topBottlenecks,
            '⚠️ critical_warnings' => array_values($criticalWarnings),
            '💡 recommendations' => self::generateRecommendations($analysis),
            '🔗 analysis_context' => $analysis['🎯 analysis_context'] ?? 'General analysis',
        ];
    }

    private static function calculateOverallPerformanceScore(array $analysis): float
    {
        $warnings = $analysis['🚀 performance_analysis']['⚠️ performance_warnings'] ?? [];
        $criticalCount = count(array_filter($warnings, fn($w) => ($w['📊 severity'] ?? '') === 'critical'));
        $highCount = count(array_filter($warnings, fn($w) => ($w['📊 severity'] ?? '') === 'high'));
        $mediumCount = count(array_filter($warnings, fn($w) => ($w['📊 severity'] ?? '') === 'medium'));

        // Base score of 100, deduct points for issues
        $score = 100.0;
        $score -= $criticalCount * 30;  // -30 for each critical
        $score -= $highCount * 15;      // -15 for each high
        $score -= $mediumCount * 5;     // -5 for each medium

        return max(0.0, round($score, 1));
    }

    private static function generateRecommendations(array $analysis): array
    {
        $recommendations = [];
        $performance = $analysis['🚀 performance_analysis'] ?? [];
        $stats = $analysis['📈 statistics'] ?? [];

        // Execution time recommendations
        $totalTime = $analysis['📊 metadata']['⏱️ total_execution_time'] ?? 0;
        if ($totalTime > 2.0) {
            $recommendations[] = [
                '💡 category' => 'performance',
                '📄 recommendation' => 'Application execution time is high (' . round($totalTime, 3) . 's). Focus on optimizing the slowest functions.',
                '🎯 priority' => 'high',
            ];
        }

        // Memory recommendations
        $peakMemory = $analysis['📊 metadata']['💾 peak_memory_usage'] ?? 0;
        if ($peakMemory > 50 * 1024 * 1024) { // > 50MB
            $recommendations[] = [
                '💡 category' => 'memory',
                '📄 recommendation' => 'High memory usage detected (' . self::formatBytes($peakMemory) . '). Review memory-intensive functions.',
                '🎯 priority' => 'medium',
            ];
        }

        // Function call recommendations
        $functionCount = $stats['📞 total_function_calls'] ?? 0;
        if ($functionCount > 10000) {
            $recommendations[] = [
                '💡 category' => 'architecture',
                '📄 recommendation' => 'High function call count (' . number_format($functionCount) . '). Consider caching frequently called functions.',
                '🎯 priority' => 'low',
            ];
        }

        // Warning-based recommendations
        foreach ($performance['⚠️ performance_warnings'] ?? [] as $warning) {
            if (($warning['📊 severity'] ?? '') === 'critical') {
                $recommendations[] = [
                    '💡 category' => 'critical',
                    '📄 recommendation' => $warning['💡 suggestion'] ?? 'Review critical performance issue',
                    '🎯 priority' => 'critical',
                    '🎯 function_name' => $warning['🎯 function_name'] ?? null,
                ];
            }
        }

        return $recommendations;
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . 'GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . 'KB';
        }
        return $bytes . 'B';
    }
}

// CLI for trace utilities
if (isset($argv) && basename(__FILE__) === basename($argv[0])) {
    if ($argc < 2) {
        echo json_encode([
            'error' => 'Missing command',
            'usage' => 'php trace-utils.php <command> [options]',
            'commands' => [
                'summary <analysis.json>' => 'Generate executive summary',
                'compare <analysis1.json> <analysis2.json>' => 'Compare two analyses',
                'bottlenecks <analysis.json> [limit]' => 'Extract top bottlenecks',
            ],
        ], JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }

    $command = $argv[1];

    try {
        switch ($command) {
            case 'summary':
                if ($argc < 3) {
                    throw new InvalidArgumentException('Analysis file required');
                }
                $analysis = json_decode(file_get_contents($argv[2]), true);
                $summary = TraceUtils::generateSummaryReport($analysis);
                echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
                break;

            case 'compare':
                if ($argc < 4) {
                    throw new InvalidArgumentException('Two analysis files required');
                }
                $analysis1 = json_decode(file_get_contents($argv[2]), true);
                $analysis2 = json_decode(file_get_contents($argv[3]), true);
                $comparison = TraceUtils::compareTwoAnalyses($analysis1, $analysis2);
                echo json_encode($comparison, JSON_PRETTY_PRINT) . "\n";
                break;

            case 'bottlenecks':
                if ($argc < 3) {
                    throw new InvalidArgumentException('Analysis file required');
                }
                $analysis = json_decode(file_get_contents($argv[2]), true);
                $limit = $argc >= 4 ? (int) $argv[3] : 10;
                $bottlenecks = TraceUtils::extractTopBottlenecks($analysis, $limit);
                echo json_encode([
                    'top_bottlenecks' => $bottlenecks,
                    'limit' => $limit,
                ], JSON_PRETTY_PRINT) . "\n";
                break;

            default:
                throw new InvalidArgumentException("Unknown command: $command");
        }
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage(),
            'command' => $command,
        ], JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
}
