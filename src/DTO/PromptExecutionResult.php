<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Result DTO for prompts/get tool executions
 *
 * Carries the assistant message text plus tool-specific debug data
 * (keys vary per tool: command, exit_code, output, breakpoints, ...).
 *
 * @phpstan-import-type JsonObject from Types
 * @phpstan-import-type TextContent from Types
 */
final class PromptExecutionResult implements JsonRpcResultInterface
{
    /** @param JsonObject $debugData */
    public function __construct(
        public readonly string $text,
        public readonly array $debugData,
    ) {
    }

    /** @return array{messages: list<array{role: string, content: TextContent}>, debug_data: JsonObject} */
    public function jsonSerialize(): array
    {
        return [
            'messages' => [
                [
                    'role' => 'assistant',
                    'content' => [
                        'type' => 'text',
                        'text' => $this->text,
                    ],
                ],
            ],
            'debug_data' => $this->debugData,
        ];
    }
}
