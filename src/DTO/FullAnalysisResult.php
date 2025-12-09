<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

use function array_map;

/**
 * Full trace analysis result
 */
final class FullAnalysisResult implements JsonSerializable
{
    /**
     * @param list<SlowFunction> $slowestFunctions
     * @param list<string>       $potentialIssues
     * @param list<string>       $executionPatterns
     */
    public function __construct(
        public readonly AnalysisMetadata $metadata,
        public readonly TraceAnalysisStatistics $statistics,
        public readonly array $slowestFunctions,
        public readonly array $potentialIssues,
        public readonly array $executionPatterns,
    ) {
    }

    /** @return array{metadata: array<string, string>, statistics: array<string, int|float>, performance_analysis: array{slowest_functions: list<array<string, string|float>>}, execution_insights: array{potential_issues: list<string>, execution_patterns: list<string>}} */
    public function jsonSerialize(): array
    {
        return [
            'metadata' => $this->metadata->jsonSerialize(),
            'statistics' => $this->statistics->jsonSerialize(),
            'performance_analysis' => [
                'slowest_functions' => array_map(
                    static fn (SlowFunction $f): array => $f->jsonSerialize(),
                    $this->slowestFunctions,
                ),
            ],
            'execution_insights' => [
                'potential_issues' => $this->potentialIssues,
                'execution_patterns' => $this->executionPatterns,
            ],
        ];
    }
}
