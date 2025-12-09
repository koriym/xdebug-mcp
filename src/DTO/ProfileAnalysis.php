<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Detailed analysis results from a profile file
 */
final class ProfileAnalysis
{
    /** @param list<string> $bottleneckFunctions */
    public function __construct(
        public readonly int $totalLines,
        public readonly int $functionsCount,
        public readonly int $userFunctions,
        public readonly int $internalFunctions,
        public readonly int $totalCalls,
        public readonly float $executionTimeMs,
        public readonly float $peakMemoryMb,
        public readonly int $fileIoOperations,
        public readonly int $databaseOperations,
        public readonly array $bottleneckFunctions,
    ) {
    }
}
