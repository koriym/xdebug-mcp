<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Statistics from parsing an Xdebug trace file
 */
final class TraceStatistics
{
    /** @var array<string, true> */
    public array $uniqueFunctions = [];

    public function __construct(
        public string $filePath,
        public int $fileSize,
        public int $totalLines = 0,
        public int $functionCalls = 0,
        public int $userFunctionCalls = 0,
        public int $internalFunctionCalls = 0,
        public int $fileIoOperations = 0,
        public int $dbOperations = 0,
        public int $maxDepth = 0,
        public int $maxCallDepth = 0,
        public int $peakMemory = 0,
        public ?float $startTime = null,
        public float $endTime = 0,
        public float $executionTime = 0,
    ) {}

    public function incrementTotalLines(): void
    {
        $this->totalLines++;
    }

    public function incrementFunctionCalls(): void
    {
        $this->functionCalls++;
    }

    public function incrementUserFunctionCalls(): void
    {
        $this->userFunctionCalls++;
    }

    public function incrementInternalFunctionCalls(): void
    {
        $this->internalFunctionCalls++;
    }

    public function incrementFileIoOperations(): void
    {
        $this->fileIoOperations++;
    }

    public function incrementDbOperations(): void
    {
        $this->dbOperations++;
    }

    public function trackFunction(string $function): void
    {
        $this->uniqueFunctions[$function] = true;
    }

    public function updateMaxDepth(int $depth): void
    {
        if ($depth > $this->maxDepth) {
            $this->maxDepth = $depth;
        }
    }

    public function updatePeakMemory(int $memory): void
    {
        if ($memory > $this->peakMemory) {
            $this->peakMemory = $memory;
        }
    }

    public function setStartTime(float $time): void
    {
        if ($this->startTime === null) {
            $this->startTime = $time;
        }
    }

    public function setEndTime(float $time): void
    {
        $this->endTime = $time;
    }

    public function calculateExecutionTime(): void
    {
        if ($this->startTime !== null) {
            $this->executionTime = $this->endTime - $this->startTime;
        }
    }

    public function getUniqueFunctionCount(): int
    {
        return count($this->uniqueFunctions);
    }
}
