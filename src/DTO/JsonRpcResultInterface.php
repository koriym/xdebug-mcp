<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

/**
 * Interface for JSON-RPC result objects
 *
 * All JSON-RPC result DTOs must implement this interface to ensure
 * they can be properly serialized in responses.
 *
 * @phpstan-import-type JsonObject from Types
 */
interface JsonRpcResultInterface extends JsonSerializable
{
    /** @return JsonObject */
    public function jsonSerialize(): array;
}
