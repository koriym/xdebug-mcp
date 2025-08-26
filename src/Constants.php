<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

/**
 * Centralized constants for the Xdebug MCP system
 */
final class Constants
{
    // Network Configuration
    public const DEFAULT_HOST = '127.0.0.1';
    public const XDEBUG_DEBUG_PORT = 9004;
    public const PERSISTENT_CONTROL_PORT = 9005;
    public const IDE_PORT = 9003; // Standard IDE port to avoid conflicts

    // Timeout Settings (in seconds)
    public const DEFAULT_TIMEOUT = 5.0;
    public const EXECUTION_TIMEOUT = 30.0;
    public const READ_TIMEOUT = 5.0;
    public const SOCKET_TIMEOUT = 10;

    // File Paths
    public const GLOBAL_STATE_FILE = '/tmp/xdebug_session_global.json';
    public const AMP_DEBUG_SESSION_FILE = '/tmp/amp_persistent_debug_session.json';
    public const TRACE_OUTPUT_DIR = '/tmp';
    public const PROFILE_OUTPUT_DIR = '/tmp';

    // Xdebug Configuration
    public const DEFAULT_XDEBUG_MODE = 'debug,trace';
    public const TRACE_FORMAT = 1; // 0=human readable, 1=computer readable
    public const MAX_CHILDREN = 128;
    public const MAX_DATA = 512;
    public const MAX_DEPTH = 3;

    // Session Management
    public const DEFAULT_SESSION_ID = 'session_001';
    public const SESSION_EXPIRY_SECONDS = 3600; // 1 hour

    // Command Limits
    public const MAX_COMMAND_LENGTH = 4096;
    public const MAX_RESPONSE_SIZE = 65536; // 64KB
    public const MAX_STEPS = 1000; // Safety limit for step debugging

    // File Extensions and Patterns
    public const TRACE_FILE_EXTENSION = '.xt';
    public const PROFILE_FILE_EXTENSION = '.cachegrind';
    public const COVERAGE_HTML_DIR = 'coverage_html';

    // Error Codes
    public const ERROR_CONNECTION_FAILED = 1001;
    public const ERROR_INVALID_RESPONSE = 1002;
    public const ERROR_TIMEOUT = 1003;
    public const ERROR_FILE_OPERATION = 1004;
    public const ERROR_INVALID_ARGUMENT = 1005;
}
