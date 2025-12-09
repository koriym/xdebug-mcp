<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

/**
 * JSON-RPC 2.0 Error
 */
final class JsonRpcError implements JsonSerializable
{
    public function __construct(
        public readonly int $code,
        public readonly string $message,
    ) {
    }

    /** @return array{code: int, message: string} */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
