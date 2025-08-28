<?php

// Simulate Claude CLI sending various problematic inputs to MCP server

echo "Simulating Claude CLI problematic inputs...\n";
echo "==========================================\n";

// Possible problematic inputs Claude CLI might send
$problematicInputs = [
    'truncated_json' => '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"x-trace","arguments":{"script":"',
    'malformed_quotes' => '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"x-trace","arguments":{"script":"php demo.php}}}',
    'extra_escaping' => '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"x-trace","arguments":{"script":"\\"php demo.php\\""}}}',
    'triple_quotes' => '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"x-trace","arguments":{"script":"\\"\\"php demo.php\\"\\""}}}',
];

foreach ($problematicInputs as $name => $jsonInput) {
    echo "\nTesting: $name\n";
    echo "Input: $jsonInput\n";
    echo "Length: " . strlen($jsonInput) . "\n";
    
    // Try to parse
    $decoded = json_decode($jsonInput, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "JSON: Valid\n";
        
        if (isset($decoded['params']['arguments']['script'])) {
            $script = $decoded['params']['arguments']['script'];
            echo "Script arg: " . var_export($script, true) . "\n";
            echo "Script hex: " . bin2hex($script) . "\n";
            
            // Simulate our processing
            $processed = $script;
            if (strlen($processed) >= 2 && str_starts_with($processed, '"') && str_ends_with($processed, '"')) {
                $processed = substr($processed, 1, -1);
                echo "After quote stripping: " . var_export($processed, true) . "\n";
            } else {
                echo "No quote stripping applied\n";
            }
            
            // Test validation
            $isValid = preg_match('/^(\S*php)\s+/', $processed);
            echo "Validation: " . ($isValid ? "PASS" : "FAIL") . "\n";
            
            if (!$isValid) {
                echo "ERROR MESSAGE WOULD BE: Script must start with PHP binary. Received: \"$processed\"\n";
            }
        }
    } else {
        echo "JSON: Invalid - " . json_last_error_msg() . "\n";
    }
    echo "---\n";
}

// Test the exact error case
echo "\n\nTesting exact error reproduction:\n";
echo "=================================\n";

// The error shows: Received: ""php"
// This could be from a script value of: "php (missing end quote and rest)

$exactErrorScript = '"php';
echo "Testing script value that would produce exact error:\n";
echo "Script: " . var_export($exactErrorScript, true) . "\n";
echo "This would produce error: Script must start with PHP binary. Received: \"$exactErrorScript\"\n";

// Create valid JSON with this problematic script value
$testJson = json_encode([
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'x-trace',
        'arguments' => [
            'script' => $exactErrorScript
        ]
    ]
]);

echo "\nThis would be the JSON that causes the error:\n";
echo "$testJson\n";

// Test it with the actual MCP server
echo "\nTesting with actual MCP server...\n";
$output = shell_exec("echo '$testJson' | php bin/xdebug-mcp 2>&1");
echo "Server response:\n$output\n";