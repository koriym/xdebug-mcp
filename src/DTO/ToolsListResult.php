<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Result DTO for tools/list JSON-RPC response
 */
final class ToolsListResult implements JsonRpcResultInterface
{
    /**
     * @param list<McpTool> $tools
     */
    public function __construct(
        public readonly array $tools,
    ) {}

    /**
     * @return array{tools: list<array{name: string, description: string, inputSchema: array{type: string, properties: array<string, array{type: string, description: string, default?: string|int}>, required: list<string>}}>}
     */
    public function jsonSerialize(): array
    {
        return [
            'tools' => array_map(
                static fn(McpTool $tool): array => $tool->jsonSerialize(),
                $this->tools,
            ),
        ];
    }
}
