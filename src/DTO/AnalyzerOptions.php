<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Options for the UnifiedAnalyzer
 */
final class AnalyzerOptions
{
    public function __construct(
        public readonly bool $compare = false,
        public readonly bool $summary = false,
        public readonly int $bottlenecks = 0,
        public readonly string $context = '',
        public readonly int $limit = 1000,
        public readonly float $threshold = 0.0,
        public readonly ?string $search = null,
    ) {}

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
