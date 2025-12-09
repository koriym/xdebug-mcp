<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

use function date;

/**
 * Metadata for trace analysis
 */
final class AnalysisMetadata implements JsonSerializable
{
    public readonly string $generatedAt;

    public function __construct(
        public readonly string $sourceTraceFile,
        public readonly string $sourceFileSize,
        public readonly string $analysisContext,
        public readonly string $analysisVersion = '2.0.0-unified',
    ) {
        $this->generatedAt = date('c');
    }

    /** @return array{generated_at: string, source_trace_file: string, source_file_size: string, analysis_context: string, analysis_version: string} */
    public function jsonSerialize(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'source_trace_file' => $this->sourceTraceFile,
            'source_file_size' => $this->sourceFileSize,
            'analysis_context' => $this->analysisContext,
            'analysis_version' => $this->analysisVersion,
        ];
    }
}
