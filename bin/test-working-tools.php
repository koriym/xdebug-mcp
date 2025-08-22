#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Simplified test script for 24 working MCP tools using the refactored test runner
 * Replaces the monolithic test-all.sh with a clean, maintainable approach
 */

require_once __DIR__ . '/lib/McpTestRunner.php';

use XdebugMcp\Testing\McpTestRunner;

// Colors for output
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

echo BLUE . "🚀 Working MCP Tools Test\n" . RESET;
echo "=" . str_repeat("=", 50) . "\n\n";

// Initialize test runner
$sessionMode = isset($argv[1]) && $argv[1] === '--with-session';
$testRunner = new McpTestRunner($sessionMode);

// Check prerequisites
if (!$testRunner->checkPrerequisites()) {
    exit(1);
}

if (!$sessionMode) {
    echo "\n" . BLUE . "📖 Testing Mode Selection:\n" . RESET;
    echo "  " . YELLOW . "Current: Basic testing (24 working tools)\n" . RESET;
    echo "  " . GREEN . "Complete: Run with --with-session to attempt session tools\n" . RESET;
    echo "\n" . BLUE . "💡 For session testing (may fail):\n" . RESET;
    echo "  1. " . YELLOW . "Terminal 1:" . RESET . " ./bin/test-working-tools.php --with-session\n";
    echo "  2. " . YELLOW . "Terminal 2:" . RESET . " php -dzend_extension=xdebug.so -dxdebug.mode=debug -dxdebug.client_port=9004 -dxdebug.start_with_request=yes test/debug_session_test.php\n";
    echo "\n" . BLUE . "📝 Running 24 working tools test now...\n" . RESET;
}

// Test session connectivity if in session mode
if ($sessionMode) {
    echo BLUE . "🔌 Waiting for debug session connection...\n" . RESET;
    echo YELLOW . "Make sure Terminal 2 is running the debug session!\n" . RESET;
    sleep(2);
    $testRunner->testSessionConnectivity();
}

// Run the working tools test
$testRunner->runWorkingToolsTest();

// Print results
$testRunner->printResults();

// Provide usage instructions
echo "\n💡 Individual tool testing:\n";
echo "   echo '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"TOOL_NAME\",\"arguments\":{}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp\n";

// Exit with appropriate code
$results = $testRunner->getResults();
exit(($sessionMode && $results['failed'] > 0) ? 1 : 0);