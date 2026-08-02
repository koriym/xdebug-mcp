<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

use JsonSerializable;
use Koriym\XdebugMcp\Constants;

use function is_array;

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

    /** @param array{supportedVersions?: list<string>}|null $data */
    public static function error(string|int|null $id, int $code, string $message, array|null $data = null): self
    {
        return new self(id: $id, error: new JsonRpcError($code, $message, $data));
    }

    /** @return array{jsonrpc: string, id: string|int|null, result?: array<string, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|null>|null>|null>|null>|null>, error?: array{code: int, message: string, data?: array{supportedVersions?: list<string>}}} */
    public function jsonSerialize(): array
    {
        $response = [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
        ];

        if ($this->result instanceof JsonRpcResultInterface) {
            $response['result'] = $this->serializeResult($this->result);
        }

        if ($this->error instanceof JsonRpcError) {
            $response['error'] = $this->error->jsonSerialize();
        }

        return $response;
    }

    /**
     * Serialize a result and attach the MCP 2026-07-28 protocol fields:
     * every result carries resultType ("complete" for ordinary results) and
     * identifies the server in _meta (io.modelcontextprotocol/serverInfo).
     * Both are ignored by legacy clients, so they are added unconditionally.
     *
     * @return array<string, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|null>|null>|null>|null>|null>
     */
    private function serializeResult(JsonRpcResultInterface $result): array
    {
        $data = $result->jsonSerialize();
        if (! isset($data['resultType'])) {
            $data['resultType'] = 'complete';
        }

        $meta = $data['_meta'] ?? [];
        if (! is_array($meta)) {
            $meta = [];
        }

        $meta['io.modelcontextprotocol/serverInfo'] ??= [
            'name' => Constants::MCP_SERVER_NAME,
            'version' => Constants::MCP_SERVER_VERSION,
        ];
        $data['_meta'] = $meta;

        return $data;
    }

    /** @return array{jsonrpc: string, id: string|int|null, result?: array<string, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|array<array-key, bool|float|int|string|null>|null>|null>|null>|null>, error?: array{code: int, message: string, data?: array{supportedVersions?: list<string>}}} */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
