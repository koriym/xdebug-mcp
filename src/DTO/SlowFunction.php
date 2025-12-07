<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

/**
 * A slow function identified during performance analysis
 */
final readonly class SlowFunction implements JsonSerializable
{
    public function __construct(
        public string $functionName,
        public float $durationSeconds,
        public string $optimizationPriority,
    ) {
    }

    /**
     * @return array{function_name: string, duration_seconds: float, optimization_priority: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'function_name' => $this->functionName,
            'duration_seconds' => $this->durationSeconds,
            'optimization_priority' => $this->optimizationPriority,
        ];
    }
}
