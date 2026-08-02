<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

/**
 * JSON-RPC 2.0 Error
 */
final class JsonRpcError implements JsonSerializable
{
    /** @param array{supportedVersions?: list<string>}|null $data */
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly array|null $data = null,
    ) {
    }

    /** @return array{code: int, message: string, data?: array{supportedVersions?: list<string>}} */
    public function jsonSerialize(): array
    {
        $error = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return $error;
    }
}
