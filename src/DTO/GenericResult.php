<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Generic result wrapper for JSON-RPC responses
 *
 * Use this when a specific result DTO doesn't exist yet.
 * Prefer creating specific DTOs for type safety where practical.
 *
 * @phpstan-import-type JsonObject from Types
 */
final class GenericResult implements JsonRpcResultInterface
{
    /** @param JsonObject $data */
    public function __construct(
        private readonly array $data,
    ) {
    }

    /** @return JsonObject */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
