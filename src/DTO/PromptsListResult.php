<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Result DTO for prompts/list
 *
 * @phpstan-import-type PromptShape from Types
 */
final class PromptsListResult implements JsonRpcResultInterface
{
    /** @param list<PromptShape> $prompts */
    public function __construct(
        public readonly array $prompts,
        public readonly int $ttlMs,
        public readonly string $cacheScope,
    ) {
    }

    /** @return array{prompts: list<PromptShape>, ttlMs: int, cacheScope: string} */
    public function jsonSerialize(): array
    {
        return [
            'prompts' => $this->prompts,
            'ttlMs' => $this->ttlMs,
            'cacheScope' => $this->cacheScope,
        ];
    }
}
