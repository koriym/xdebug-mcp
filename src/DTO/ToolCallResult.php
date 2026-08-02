<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Result DTO for tools/call — a single text content block
 *
 * @phpstan-import-type TextContent from Types
 */
final class ToolCallResult implements JsonRpcResultInterface
{
    public function __construct(
        public readonly string $text,
    ) {
    }

    /** @return array{content: list<TextContent>} */
    public function jsonSerialize(): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $this->text,
                ],
            ],
        ];
    }
}
