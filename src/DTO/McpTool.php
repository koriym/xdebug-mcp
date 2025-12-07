<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

/**
 * MCP Tool Definition
 */
final class McpTool implements JsonSerializable
{
    /**
     * @param array{type: string, properties: array<string, array{type: string, description: string, default?: string|int}>, required: list<string>} $inputSchema
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
    ) {}

    /**
     * @return array{name: string, description: string, inputSchema: array{type: string, properties: array<string, array{type: string, description: string, default?: string|int}>, required: list<string>}}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
