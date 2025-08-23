<?php
/**
 * Test MCP tools integration with step debugging
 */
echo "🔗 MCP step debugging integration test...\n";

// Create a test script to debug
$testScript = <<<'PHP'
<?php
function mcpTest($a, $b) {
    echo "MCP test: $a + $b\n";
    $result = $a + $b;
    echo "Result: $result\n";
    return $result;
}

echo "Starting MCP test script\n";
$x = 5;
$y = 3;
$sum = mcpTest($x, $y);
echo "Final: $sum\n";
PHP;

file_put_contents('/tmp/mcp_test_script.php', $testScript);

// Start the script with Xdebug
$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9003 -dxdebug.start_with_request=yes /tmp/mcp_test_script.php',
    [],
    $pipes
);

// Start MCP server in background
$mcp_process = proc_open(
    'php bin/xdebug-mcp',
    [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w']  // stderr
    ],
    $mcp_pipes
);

sleep(1); // Let MCP server start

// Test MCP commands
$commands = [
    // Connect to debug session
    '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_connect","arguments":{"host":"127.0.0.1","port":9003}}}',
    
    // Set breakpoint
    '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_set_breakpoint","arguments":{"filename":"/tmp/mcp_test_script.php","line":3}}}',
    
    // Continue to breakpoint
    '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"xdebug_continue","arguments":{}}}',
    
    // Get variables
    '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"xdebug_get_variables","arguments":{}}}',
    
    // Step over
    '{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"xdebug_step_over","arguments":{}}}',
    
    // Get stack
    '{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"xdebug_get_stack","arguments":{}}}',
    
    // Continue to end
    '{"jsonrpc":"2.0","id":7,"method":"tools/call","params":{"name":"xdebug_continue","arguments":{}}}',
    
    // Disconnect
    '{"jsonrpc":"2.0","id":8,"method":"tools/call","params":{"name":"xdebug_disconnect","arguments":{}}}'
];

echo "🚀 Executing MCP commands...\n";

foreach ($commands as $i => $command) {
    echo "\n--- Command " . ($i + 1) . " ---\n";
    echo "📤 Sending: " . substr($command, 0, 80) . "...\n";
    
    fwrite($mcp_pipes[0], $command . "\n");
    fflush($mcp_pipes[0]);
    
    // Read response
    $response = fgets($mcp_pipes[1], 4096);
    if ($response) {
        $decoded = json_decode($response, true);
        if (isset($decoded['result'])) {
            echo "✅ Success: " . substr(json_encode($decoded['result']), 0, 100) . "...\n";
        } elseif (isset($decoded['error'])) {
            echo "❌ Error: " . $decoded['error']['message'] . "\n";
        }
    } else {
        echo "⚠️ No response received\n";
    }
    
    usleep(100000); // 100ms delay
}

echo "\n✅ MCP integration test complete!\n";

// Cleanup
proc_terminate($script_process);
proc_terminate($mcp_process);
unlink('/tmp/mcp_test_script.php');