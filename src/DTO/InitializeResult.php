<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\DTO;

/**
 * Result DTO for the legacy initialize handshake (2025-11-25 and earlier)
 *
 * @phpstan-import-type Capabilities from Types
 */
final class InitializeResult implements JsonRpcResultInterface
{
    /**
     * @param Capabilities                         $capabilities
     * @param array{name: string, version: string} $serverInfo
     */
    public function __construct(
        public readonly string $protocolVersion,
        public readonly array $capabilities,
        public readonly array $serverInfo,
        public readonly string $instructions,
    ) {
    }

    /** @return array{protocolVersion: string, capabilities: Capabilities, serverInfo: array{name: string, version: string}, instructions: string} */
    public function jsonSerialize(): array
    {
        return [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities,
            'serverInfo' => $this->serverInfo,
            'instructions' => $this->instructions,
        ];
    }
}
