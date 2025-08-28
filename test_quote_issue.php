<?php

// Test script to reproduce the quote processing issue

require_once 'src/McpServer.php';

use XdebugMcp\McpServer;

// Test various quote scenarios that Claude CLI might send
$testCases = [
    'simple' => 'php demo.php',
    'single_quotes' => '"php demo.php"',
    'double_quotes' => '""php demo.php""',
    'triple_quotes' => '"""php demo.php"""',
    'mixed_quotes' => '"\'php demo.php\'"',
    'escaped_quotes' => '\"php demo.php\"',
];

echo "Testing quote processing issues:\n";
echo "================================\n";

foreach ($testCases as $name => $script) {
    echo "\nTest: $name\n";
    echo "Input: " . var_export($script, true) . "\n";
    echo "Length: " . strlen($script) . "\n";
    echo "Hex: " . bin2hex($script) . "\n";
    
    // Test the quote stripping logic
    $processed = $script;
    if (strlen($processed) >= 2 && str_starts_with($processed, '"') && str_ends_with($processed, '"')) {
        $processed = substr($processed, 1, -1);
        echo "After quote stripping: " . var_export($processed, true) . "\n";
    } else {
        echo "No quote stripping applied\n";
    }
    
    // Test validation
    $isValid = preg_match('/^(\S*php)\s+/', $processed);
    echo "Validation result: " . ($isValid ? "PASS" : "FAIL") . "\n";
    
    if (!$isValid) {
        echo "ERROR: Would fail validation\n";
    }
    echo "---\n";
}

// Test MCP JSON-RPC request simulation
echo "\n\nTesting MCP JSON-RPC requests:\n";
echo "==============================\n";

$mcpRequests = [
    'normal_quotes' => [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'x-trace',
            'arguments' => [
                'script' => '"php demo.php"'
            ]
        ]
    ],
    'double_quotes' => [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'x-trace',
            'arguments' => [
                'script' => '""php demo.php""'
            ]
        ]
    ]
];

foreach ($mcpRequests as $name => $request) {
    echo "\nMCP Request: $name\n";
    $json = json_encode($request, JSON_PRETTY_PRINT);
    echo "JSON:\n$json\n";
    
    $script = $request['params']['arguments']['script'];
    echo "Script argument: " . var_export($script, true) . "\n";
    
    // Simulate the processing
    $processed = $script;
    if (strlen($processed) >= 2 && str_starts_with($processed, '"') && str_ends_with($processed, '"')) {
        $processed = substr($processed, 1, -1);
        echo "After processing: " . var_export($processed, true) . "\n";
    }
    
    $isValid = preg_match('/^(\S*php)\s+/', $processed);
    echo "Would validate: " . ($isValid ? "YES" : "NO") . "\n";
    echo "---\n";
}