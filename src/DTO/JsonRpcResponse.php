<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;

/**
 * JSON-RPC 2.0 Response
 */
final class JsonRpcResponse implements JsonSerializable
{
    public function __construct(
        public readonly string|int|null $id,
        public readonly JsonRpcResultInterface|null $result = null,
        public readonly JsonRpcError|null $error = null,
        public readonly string $jsonrpc = '2.0',
    ) {
    }

    public static function success(string|int|null $id, JsonRpcResultInterface $result): self
    {
        return new self(id: $id, result: $result);
    }

    public static function error(string|int|null $id, int $code, string $message): self
    {
        return new self(id: $id, error: new JsonRpcError($code, $message));
    }

    /** @return array{jsonrpc: string, id: string|int|null, result?: array<string, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|null>|null>|null>|null>|null>, error?: array{code: int, message: string}} */
    public function jsonSerialize(): array
    {
        $response = [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
        ];

        if ($this->result instanceof JsonRpcResultInterface) {
            $response['result'] = $this->result->jsonSerialize();
        }

        if ($this->error instanceof JsonRpcError) {
            $response['error'] = $this->error->jsonSerialize();
        }

        return $response;
    }

    /** @return array{jsonrpc: string, id: string|int|null, result?: array<string, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|null>|null>|null>|null>|null>, error?: array{code: int, message: string}} */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
