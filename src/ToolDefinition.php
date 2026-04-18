<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\DTO\McpTool;

use function array_keys;
use function in_array;

/**
 * Single source of truth for MCP tool and prompt metadata.
 *
 * @phpstan-type InputProperty array{type: string, description: string, default?: string|int}
 * @phpstan-type InputSchema array{
 *     type: string,
 *     properties: array<string, InputProperty>,
 *     required: list<string>
 * }
 * @phpstan-type PromptArgument array{name: string, description: string, required: bool}
 * @phpstan-type PromptDefinition array{name: string, description: string, arguments: list<PromptArgument>}
 */
final class ToolDefinition
{
    /** @param InputSchema $inputSchema */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly string $handlerMethod,
        public readonly bool $supportsLast = true,
    ) {
    }

    public function toMcpTool(): McpTool
    {
        return new McpTool(
            $this->name,
            $this->description,
            $this->inputSchema,
        );
    }

    /** @return PromptDefinition */
    public function toPromptDefinition(): array
    {
        $arguments = [];
        $required = $this->inputSchema['required'];

        foreach ($this->inputSchema['properties'] as $name => $property) {
            $arguments[] = [
                'name' => $name,
                'description' => $property['description'],
                'required' => in_array($name, $required, true),
            ];
        }

        if ($this->supportsLast) {
            $arguments[] = [
                'name' => 'last',
                'description' => 'Use settings from last execution (true/false)',
                'required' => false,
            ];
        }

        return [
            'name' => $this->name,
            'description' => $this->description,
            'arguments' => $arguments,
        ];
    }

    /**
     * @param array<string, string> $args
     * @param list<string>          $positionalArgs
     *
     * @return array<string, string>
     */
    public function mapPositionalArgs(array $args, array $positionalArgs): array
    {
        if ($positionalArgs === []) {
            return $args;
        }

        $mapping = array_keys($this->inputSchema['properties']);
        foreach ($positionalArgs as $index => $value) {
            if (! isset($mapping[$index])) {
                continue;
            }

            $args[$mapping[$index]] = $value;
        }

        return $args;
    }
}
