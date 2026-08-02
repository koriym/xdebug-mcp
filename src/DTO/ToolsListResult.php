<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use Koriym\XdebugMcp\Constants;

use function array_map;

/**
 * Result DTO for tools/list JSON-RPC response
 */
final class ToolsListResult implements JsonRpcResultInterface
{
    /** @param list<McpTool> $tools */
    public function __construct(
        public readonly array $tools,
    ) {
    }

    /** @return array{tools: list<array{name: string, description: string, inputSchema: array{type: string, properties: array<string, array{type: string, description: string, default?: string|int}>, required: list<string>}}>, ttlMs: int, cacheScope: string} */
    public function jsonSerialize(): array
    {
        return [
            'tools' => array_map(
                static fn (McpTool $tool): array => $tool->jsonSerialize(),
                $this->tools,
            ),
            // MCP 2026-07-28 CacheableResult: the tool list is static
            'ttlMs' => Constants::MCP_LIST_CACHE_TTL_MS,
            'cacheScope' => Constants::MCP_LIST_CACHE_SCOPE,
        ];
    }
}
