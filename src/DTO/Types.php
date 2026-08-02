<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Domain types for JSON-RPC / MCP payloads (PHPStan)
 *
 * JSON values are recursive by nature, but PHPStan type aliases cannot be
 * recursive, so the nesting depth is bounded explicitly. The bound matches
 * the previous hand-written inline shapes and covers every tool result.
 *
 * @phpstan-type JsonScalar = bool|float|int|string|null
 * @phpstan-type JsonValue1 = bool|float|int|string|array<array-key, JsonScalar>|null
 * @phpstan-type JsonValue2 = bool|float|int|string|array<array-key, JsonValue1>|null
 * @phpstan-type JsonValue3 = bool|float|int|string|array<array-key, JsonValue2>|null
 * @phpstan-type JsonValue = bool|float|int|string|array<array-key, JsonValue3>|null
 * @phpstan-type JsonObject = array<string, JsonValue>
 * @phpstan-type Capabilities = array{tools: array{listChanged: bool}, resources: array{listChanged: bool}, prompts: array{listChanged: bool}}
 * @phpstan-type TextContent = array{type: string, text: string}
 * @phpstan-type PromptArgumentShape = array{name: string, description: string, required: bool}
 * @phpstan-type PromptShape = array{name: string, description: string, arguments: list<PromptArgumentShape>}
 * @phpstan-type ErrorData = array{supported?: list<string>, requested?: string}
 * @phpstan-type JsonRpcErrorShape = array{code: int, message: string, data?: ErrorData}
 * @phpstan-type JsonRpcResponseShape = array{jsonrpc: string, id: string|int|null, result?: JsonObject, error?: JsonRpcErrorShape}
 */
final class Types
{
}
