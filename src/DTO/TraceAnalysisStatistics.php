<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

/**
 * Statistics from trace analysis
 */
final class TraceAnalysisStatistics implements JsonSerializable
{
    public function __construct(
        public readonly int $uniqueFunctionsCount,
        public readonly int $totalFunctionCalls,
        public readonly int $uniqueFilesCount,
        public readonly int $maxCallDepth,
        public readonly float $totalExecutionTime,
    ) {
    }

    /**
     * @return array{unique_functions_count: int, total_function_calls: int, unique_files_count: int, max_call_depth: int, total_execution_time: float}
     */
    public function jsonSerialize(): array
    {
        return [
            'unique_functions_count' => $this->uniqueFunctionsCount,
            'total_function_calls' => $this->totalFunctionCalls,
            'unique_files_count' => $this->uniqueFilesCount,
            'max_call_depth' => $this->maxCallDepth,
            'total_execution_time' => $this->totalExecutionTime,
        ];
    }
}
