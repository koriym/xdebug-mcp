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

    /** MCP server identity reported in serverInfo */
    public const MCP_SERVER_NAME = 'xdebug-mcp-server';
    public const MCP_SERVER_VERSION = '2.0.0';

    /** Cache hints (CacheableResult) for static list endpoints such as tools/list */
    public const MCP_LIST_CACHE_TTL_MS = 3600000;
    public const MCP_LIST_CACHE_SCOPE = 'public';
}
