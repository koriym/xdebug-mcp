<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

/**
 * Constants for the Xdebug MCP system
 */
final class Constants
{
    public const DEFAULT_HOST = '127.0.0.1';
    public const XDEBUG_DEBUG_PORT = 9004;
    public const GLOBAL_STATE_FILE = '/tmp/xdebug-mcp-global-state.json';
}
