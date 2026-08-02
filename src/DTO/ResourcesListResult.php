<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Result DTO for resources/list (this server exposes no resources)
 *
 * @phpstan-import-type JsonObject from Types
 */
final class ResourcesListResult implements JsonRpcResultInterface
{
    /** @param list<JsonObject> $resources */
    public function __construct(
        public readonly array $resources,
        public readonly int $ttlMs,
        public readonly string $cacheScope,
    ) {
    }

    /** @return array{resources: list<JsonObject>, ttlMs: int, cacheScope: string} */
    public function jsonSerialize(): array
    {
        return [
            'resources' => $this->resources,
            'ttlMs' => $this->ttlMs,
            'cacheScope' => $this->cacheScope,
        ];
    }
}
