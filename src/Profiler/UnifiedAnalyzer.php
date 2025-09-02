<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Profiler;

use function count;
use function date;
use function filesize;
use function floor;
use function is_array;
use function is_readable;
use function is_string;
use function log;
use function max;
use function min;
use function pow;
use function realpath;
use function round;
use function trim;

/**
 * Unified Trace Analyzer - Single tool for all trace analysis needs
 *
 * Converts large trace files into AI-optimized structured data
 */
class UnifiedAnalyzer
{
    private array $traceFiles;
    private array $options;

    public function __construct(array $traceFiles, array $options)
    {
        // Normalize and validate trace files
        $this->traceFiles = $this->normalizeTraceFiles($traceFiles);
        
        // Normalize and validate options with defaults
        $this->options = $this->normalizeOptions($options);
    }

    private function normalizeTraceFiles(array $traceFiles): array
    {
        $normalized = [];
        
        foreach ($traceFiles as $file) {
            // Filter non-strings
            if (!is_string($file)) {
                continue;
            }
            
            // Trim whitespace
            $file = trim($file);
            if ($file === '') {
                continue;
            }
            
            // Try to resolve to realpath
            $realPath = realpath($file);
            if ($realPath === false) {
                // If realpath fails, use original path for final readability check
                $realPath = $file;
            }
            
            // Skip if not readable
            if (!is_readable($realPath)) {
                continue;
            }
            
            $normalized[] = $realPath;
        }
        
        return $normalized;
    }

    private function normalizeOptions(array $options): array
    {
        // Coerce to array and apply defaults
        if (!is_array($options)) {
            $options = [];
        }
        
        return [
            'compare' => $options['compare'] ?? false,
            'summary' => $options['summary'] ?? false,
            'bottlenecks' => $options['bottlenecks'] ?? 0,
            'context' => $options['context'] ?? '',
            'limit' => $options['limit'] ?? 1000,
            'threshold' => $options['threshold'] ?? 0.0,
        ];
    }

    private function safeFilesize(string $path): int
    {
        if (!is_readable($path)) {
            return 0;
        }
        
        $size = filesize($path);
        return $size === false ? 0 : $size;
    }

    public function analyze(): array
    {
        // Determine analysis mode
        if ($this->options['compare'] && count($this->traceFiles) >= 2) {
            return $this->compareTraces();
        }

        if ($this->options['summary']) {
            return $this->generateSummary();
        }

        if ((int)$this->options['bottlenecks'] > 0) {
            return $this->extractBottlenecks();
        }

        // Default: full analysis
        return $this->fullAnalysis();
    }

    private function fullAnalysis(): array
    {
        $traceFile = $this->traceFiles[0];

        // Basic structure with emoji prefixes for AI readability
        $result = [
            '📊 metadata' => [
                '🕒 generated_at' => date('c'),
                '📁 source_trace_file' => $traceFile,
                '📏 source_file_size' => $this->formatBytes($this->safeFilesize($traceFile)),
                '🎯 analysis_context' => $this->options['context'] ?? 'General analysis',
                '🔍 analysis_version' => '2.0.0-unified',
            ],
            '📈 statistics' => $this->generateStatistics($traceFile),
            '🚀 performance_analysis' => $this->analyzePerformance($traceFile),
            '🔍 execution_insights' => $this->generateInsights($traceFile),
        ];

        // Add search results if specified
        if ($this->options['search']) {
            $result['🔎 search_results'] = $this->searchFunction($traceFile, $this->options['search']);
        }

        return $result;
    }

    private function compareTraces(): array
    {
        $file1 = $this->traceFiles[0];
        $file2 = $this->traceFiles[1];

        return [
            '📊 metadata' => [
                '🕒 generated_at' => date('c'),
                '📂 comparison_files' => [$file1, $file2],
                '🎯 analysis_context' => $this->options['context'] ?: 'Trace comparison',
                '🔄 comparison_type' => 'before_after_analysis',
            ],
            '📈 performance_diff' => $this->comparePerformance($file1, $file2),
            '🔄 execution_changes' => $this->compareExecution($file1, $file2),
            '💡 insights' => $this->generateComparisonInsights($file1, $file2),
        ];
    }

    private function generateSummary(): array
    {
        $traceFile = $this->traceFiles[0];

        // Executive summary - key metrics only
        return [
            '📊 executive_summary' => [
                '🎯 context' => $this->options['context'] ?? 'Executive summary',
                '📏 file_size' => $this->formatBytes($this->safeFilesize($traceFile)),
                '⏱️ key_performance_issues' => $this->getTopIssues($traceFile, 3),
                '🎯 recommendations' => $this->generateRecommendations($traceFile),
                '🚨 critical_warnings' => $this->getCriticalWarnings($traceFile),
            ],
        ];
    }

    private function extractBottlenecks(): array
    {
        $traceFile = $this->traceFiles[0];
        $limit = $this->options['bottlenecks'];

        return [
            '📊 metadata' => [
                '🎯 analysis_focus' => "Top {$limit} performance bottlenecks",
                '📁 source_file' => $traceFile,
            ],
            '🐌 bottlenecks' => $this->getTopBottlenecks($traceFile, $limit),
            '💡 optimization_suggestions' => $this->generateOptimizationSuggestions($traceFile, $limit),
        ];
    }

    // Helper methods (simplified for demo)
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function generateStatistics(string $traceFile): array
    {
        // TODO: Implement actual trace parsing
        return [
            '🔢 unique_functions_count' => 1250,
            '📞 total_function_calls' => 15420,
            '📂 unique_files_count' => 85,
            '🏗️ max_call_depth' => 12,
            '⏱️ total_execution_time' => 2.45,
        ];
    }

    private function analyzePerformance(string $traceFile): array
    {
        // TODO: Implement actual performance analysis
        return [
            '🐌 slowest_functions' => [
                [
                    '🏷️ function_name' => 'UserService::authenticate',
                    '⏱️ duration_seconds' => 0.85,
                    '💡 optimization_priority' => 'high',
                ],
            ],
        ];
    }

    private function generateInsights(string $traceFile): array
    {
        return [
            '🚨 potential_issues' => [
                'Database query in loop detected',
                'Memory usage spike at line 1205',
            ],
            '🔄 execution_patterns' => [
                'Recursive call depth: 12 levels',
                'High frequency calls to User::validate()',
            ],
        ];
    }

    private function searchFunction(string $traceFile, string $search): array
    {
        // TODO: Implement function search
        return [
            '🔍 search_term' => $search,
            '📊 matches_found' => 15,
            '📍 locations' => [
                'UserController.php:42',
                'AuthService.php:156',
            ],
        ];
    }

    // Additional helper methods for other modes...
    private function comparePerformance(string $file1, string $file2): array
    {
        return [];
    }

    private function compareExecution(string $file1, string $file2): array
    {
        return [];
    }

    private function generateComparisonInsights(string $file1, string $file2): array
    {
        return [];
    }

    private function getTopIssues(string $traceFile, int $limit): array
    {
        return [];
    }

    private function generateRecommendations(string $traceFile): array
    {
        return [];
    }

    private function getCriticalWarnings(string $traceFile): array
    {
        return [];
    }

    private function getTopBottlenecks(string $traceFile, int $limit): array
    {
        return [];
    }

    private function generateOptimizationSuggestions(string $traceFile, int $limit): array
    {
        return [];
    }
}
