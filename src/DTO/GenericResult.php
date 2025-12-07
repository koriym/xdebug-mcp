<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Generic result wrapper for JSON-RPC responses
 *
 * Use this when a specific result DTO doesn't exist yet.
 * Prefer creating specific DTOs for type safety where practical.
 */
final class GenericResult implements JsonRpcResultInterface
{
    /**
     * @param array<string, bool|float|int|string|null|array<array-key, bool|float|int|string|null|array<array-key, bool|float|int|string|null|array<array-key, bool|float|int|string|null|array<array-key, bool|float|int|string|null>>>>> $data
     */
    public function __construct(
        private readonly array $data,
    ) {}

    /**
     * @return array<string, bool|float|int|string|null|array<array-key, bool|float|int|string|null|array<array-key, bool|float|int|string|null|array<array-key, bool|float|int|string|null|array<array-key, bool|float|int|string|null>>>>>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
