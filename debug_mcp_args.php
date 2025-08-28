<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\McpServer;

// Capture debug output to file instead of error_log
$debugOutput = '/tmp/mcp_debug.log';
file_put_contents($debugOutput, "=== MCP Debug Session Started ===\n", FILE_APPEND);

// Override error_log to capture our debug messages
ini_set('error_log', $debugOutput);

// Test both direct JSON-RPC and what Claude might be sending
$testInputs = [
    '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"x-trace","arguments":{"script":"php demo.php"}}}',
    '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"x-trace","arguments":{"script":"demo.php"}}}' // What Claude might be sending
];

echo "Testing MCP argument processing...\n";

foreach ($testInputs as $i => $input) {
    echo "\n--- Test " . ($i + 1) . " ---\n";
    echo "Input: $input\n";
    
    // Simulate stdin input
    $tempFile = tempnam('/tmp', 'mcp_test');
    file_put_contents($tempFile, $input);
    
    // Run MCP server with test input
    $cmd = "php bin/xdebug-mcp < $tempFile 2>&1";
    $output = shell_exec($cmd);
    
    echo "Output: $output\n";
    
    unlink($tempFile);
}

echo "\n--- Debug Log Contents ---\n";
if (file_exists($debugOutput)) {
    echo file_get_contents($debugOutput);
} else {
    echo "No debug log created\n";
}