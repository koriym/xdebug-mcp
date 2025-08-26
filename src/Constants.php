<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

/**
 * Centralized constants for the Xdebug MCP system
 */
final class Constants
{
    // Network Configuration
    public const string DEFAULT_HOST = '127.0.0.1';
    public const int XDEBUG_DEBUG_PORT = 9004;
    public const int PERSISTENT_CONTROL_PORT = 9005;
    public const int IDE_PORT = 9003; // Standard IDE port to avoid conflicts

    // Timeout Settings (in seconds)
    public const float DEFAULT_TIMEOUT = 5.0;
    public const float EXECUTION_TIMEOUT = 30.0;
    public const float READ_TIMEOUT = 5.0;
    public const int SOCKET_TIMEOUT = 10;

    // File Paths
    public const string GLOBAL_STATE_FILE = '/tmp/xdebug_session_global.json';
    public const string AMP_DEBUG_SESSION_FILE = '/tmp/amp_persistent_debug_session.json';
    public const string TRACE_OUTPUT_DIR = '/tmp';
    public const string PROFILE_OUTPUT_DIR = '/tmp';

    // Xdebug Configuration
    public const string DEFAULT_XDEBUG_MODE = 'debug,trace';
    public const int TRACE_FORMAT = 1; // 0=human readable, 1=computer readable
    public const int MAX_CHILDREN = 128;
    public const int MAX_DATA = 512;
    public const int MAX_DEPTH = 3;

    // Session Management
    public const string DEFAULT_SESSION_ID = 'session_001';
    public const int SESSION_EXPIRY_SECONDS = 3600; // 1 hour

    // Command Limits
    public const int MAX_COMMAND_LENGTH = 4096;
    public const int MAX_RESPONSE_SIZE = 65536; // 64KB
    public const int MAX_STEPS = 1000; // Safety limit for step debugging

    // File Extensions and Patterns
    public const string TRACE_FILE_EXTENSION = '.xt';
    public const string PROFILE_FILE_EXTENSION = '.cachegrind';
    public const string COVERAGE_HTML_DIR = 'coverage_html';

    // Error Codes
    public const int ERROR_CONNECTION_FAILED = 1001;
    public const int ERROR_INVALID_RESPONSE = 1002;
    public const int ERROR_TIMEOUT = 1003;
    public const int ERROR_FILE_OPERATION = 1004;
    public const int ERROR_INVALID_ARGUMENT = 1005;
}
