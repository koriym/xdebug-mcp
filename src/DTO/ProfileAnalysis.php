<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Detailed analysis results from a profile file
 */
final readonly class ProfileAnalysis
{
    /**
     * @param list<string> $bottleneckFunctions
     */
    public function __construct(
        public int $totalLines,
        public int $functionsCount,
        public int $userFunctions,
        public int $internalFunctions,
        public int $totalCalls,
        public float $executionTimeMs,
        public float $peakMemoryMb,
        public int $fileIoOperations,
        public int $databaseOperations,
        public array $bottleneckFunctions,
    ) {
    }
}
