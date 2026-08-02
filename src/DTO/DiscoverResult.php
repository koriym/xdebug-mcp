<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Result DTO for MCP 2026-07-28 server/discover (SEP-2575)
 *
 * @phpstan-import-type Capabilities from Types
 */
final class DiscoverResult implements JsonRpcResultInterface
{
    /**
     * @param list<string> $supportedVersions
     * @param Capabilities $capabilities
     */
    public function __construct(
        public readonly array $supportedVersions,
        public readonly array $capabilities,
        public readonly string $instructions,
        public readonly int $ttlMs,
        public readonly string $cacheScope,
    ) {
    }

    /** @return array{supportedVersions: list<string>, capabilities: Capabilities, instructions: string, ttlMs: int, cacheScope: string} */
    public function jsonSerialize(): array
    {
        return [
            'supportedVersions' => $this->supportedVersions,
            'capabilities' => $this->capabilities,
            'instructions' => $this->instructions,
            'ttlMs' => $this->ttlMs,
            'cacheScope' => $this->cacheScope,
        ];
    }
}
