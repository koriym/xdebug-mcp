<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Normalized CLI parameters
 */
final class CliParams
{
    /**
     * @param array<string, string>       $stringParams   String parameters
     * @param array<string, int>          $intParams      Integer parameters
     * @param array<string, float>        $floatParams    Float parameters
     * @param array<string, bool>         $boolParams     Boolean parameters
     * @param array<string, list<string>> $jsonParams     JSON array parameters
     * @param list<string>                $positionalArgs Positional arguments after --
     */
    public function __construct(
        public readonly array $stringParams = [],
        public readonly array $intParams = [],
        public readonly array $floatParams = [],
        public readonly array $boolParams = [],
        public readonly array $jsonParams = [],
        public readonly array $positionalArgs = [],
    ) {
    }

    /**
     * Get a string parameter value
     */
    public function getString(string $key, string $default = ''): string
    {
        return $this->stringParams[$key] ?? $default;
    }

    /**
     * Get an integer parameter value
     */
    public function getInt(string $key, int $default = 0): int
    {
        return $this->intParams[$key] ?? $default;
    }

    /**
     * Get a float parameter value
     */
    public function getFloat(string $key, float $default = 0.0): float
    {
        return $this->floatParams[$key] ?? $default;
    }

    /**
     * Get a boolean parameter value
     */
    public function getBool(string $key, bool $default = false): bool
    {
        return $this->boolParams[$key] ?? $default;
    }

    /**
     * Get a JSON array parameter value
     *
     * @return list<string>
     */
    public function getJson(string $key): array
    {
        return $this->jsonParams[$key] ?? [];
    }

    /**
     * Check if a parameter exists in any type
     */
    public function has(string $key): bool
    {
        return isset($this->stringParams[$key])
            || isset($this->intParams[$key])
            || isset($this->floatParams[$key])
            || isset($this->boolParams[$key])
            || isset($this->jsonParams[$key]);
    }

    /**
     * Get all parameters merged as strings (for compatibility)
     *
     * @return array<string, string>
     */
    public function toStringArray(): array
    {
        $result = $this->stringParams;

        foreach ($this->intParams as $key => $value) {
            $result[$key] = (string) $value;
        }

        foreach ($this->floatParams as $key => $value) {
            $result[$key] = (string) $value;
        }

        foreach ($this->boolParams as $key => $value) {
            $result[$key] = $value ? 'true' : 'false';
        }

        foreach ($this->jsonParams as $key => $value) {
            $result[$key] = json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $result;
    }
}
