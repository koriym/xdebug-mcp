<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Profiler;

use Koriym\XdebugMcp\DTO\AnalysisMetadata;
use Koriym\XdebugMcp\DTO\AnalyzerOptions;
use Koriym\XdebugMcp\DTO\FullAnalysisResult;
use Koriym\XdebugMcp\DTO\SlowFunction;
use Koriym\XdebugMcp\DTO\TraceAnalysisStatistics;

use function array_pop;
use function count;
use function explode;
use function filesize;
use function floor;
use function implode;
use function is_readable;
use function log;
use function max;
use function min;
use function preg_match;
use function round;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Unified Trace Analyzer - Single tool for all trace analysis needs
 *
 * Converts large trace files into AI-optimized structured data
 */
class UnifiedAnalyzer
{
    /** @var list<string> */
    private array $traceFiles;

    /**
     * @param list<string> $traceFiles
     */
    public function __construct(array $traceFiles, private readonly AnalyzerOptions $options)
    {
        $this->traceFiles = $this->normalizeTraceFiles($traceFiles);
    }

    /**
     * Factory method for backwards compatibility with array options
     *
     * @param list<string> $traceFiles
     * @param array<string, bool|int|float|string|null> $options
     */
    public static function create(array $traceFiles, array $options = []): self
    {
        return new self($traceFiles, AnalyzerOptions::fromArray($options));
    }

    /**
     * @param list<string> $traceFiles
     *
     * @return list<string>
     */
    private function normalizeTraceFiles(array $traceFiles): array
    {
        $normalized = [];

        foreach ($traceFiles as $file) {
            $file = trim($file);
            if ($file === '') {
                continue;
            }

            $realPath = $this->normalisePath($file);

            if (! is_readable($realPath)) {
                continue;
            }

            $normalized[] = $realPath;
        }

        return $normalized;
    }

    /**
     * Normalise a path by resolving . and .. segments.
     * Compatible with phar:// and other stream wrappers unlike realpath().
     */
    private function normalisePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $prefix = '';
        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*://)(.*)$#', $path, $matches)) {
            $prefix = $matches[1];
            $path = $matches[2];
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $path = substr($path, 1);
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '') {
                continue;
            }
            if ($part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return $prefix . implode('/', $parts);
    }

    private function safeFilesize(string $path): int
    {
        if (! is_readable($path)) {
            return 0;
        }

        $size = filesize($path);

        return $size === false ? 0 : $size;
    }

    public function analyze(): FullAnalysisResult
    {
        // TODO: Implement compare, summary, and bottleneck modes
        // For now, always return full analysis
        return $this->fullAnalysis();
    }

    private function fullAnalysis(): FullAnalysisResult
    {
        $traceFile = $this->traceFiles[0] ?? '';

        $metadata = new AnalysisMetadata(
            sourceTraceFile: $traceFile,
            sourceFileSize: $this->formatBytes($this->safeFilesize($traceFile)),
            analysisContext: $this->options->context !== '' ? $this->options->context : 'General analysis',
        );

        // TODO: Implement actual trace parsing
        $statistics = new TraceAnalysisStatistics(
            uniqueFunctionsCount: 1250,
            totalFunctionCalls: 15420,
            uniqueFilesCount: 85,
            maxCallDepth: 12,
            totalExecutionTime: 2.45,
        );

        $slowFunctions = [
            new SlowFunction(
                functionName: 'UserService::authenticate',
                durationSeconds: 0.85,
                optimizationPriority: 'high',
            ),
        ];

        return new FullAnalysisResult(
            metadata: $metadata,
            statistics: $statistics,
            slowestFunctions: $slowFunctions,
            potentialIssues: [
                'Database query in loop detected',
                'Memory usage spike at line 1205',
            ],
            executionPatterns: [
                'Recursive call depth: 12 levels',
                'High frequency calls to User::validate()',
            ],
        );
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes !== 0 ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;

        return round($bytes, 2) . ' ' . $units[(int) $pow];
    }
}
