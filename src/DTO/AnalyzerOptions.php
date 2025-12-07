<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Options for the UnifiedAnalyzer
 */
final readonly class AnalyzerOptions
{
    public function __construct(
        public bool $compare = false,
        public bool $summary = false,
        public int $bottlenecks = 0,
        public string $context = '',
        public int $limit = 1000,
        public float $threshold = 0.0,
        public ?string $search = null,
    ) {
    }

    /**
     * Create from an associative array (for backwards compatibility)
     *
     * @param array<string, bool|int|float|string|null> $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            compare: (bool) ($options['compare'] ?? false),
            summary: (bool) ($options['summary'] ?? false),
            bottlenecks: (int) ($options['bottlenecks'] ?? 0),
            context: (string) ($options['context'] ?? ''),
            limit: (int) ($options['limit'] ?? 1000),
            threshold: (float) ($options['threshold'] ?? 0.0),
            search: isset($options['search']) ? (string) $options['search'] : null,
        );
    }
}
