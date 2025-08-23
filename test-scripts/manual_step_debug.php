<?php
/**
 * Manual step debugging test
 */

// Create socket to listen for Xdebug connections
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, '127.0.0.1', 9003);
socket_listen($socket, 1);

echo "🔌 Listening for Xdebug connection on 127.0.0.1:9003...\n";

// Start target script in background
$script_pid = exec('php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9003 -dxdebug.start_with_request=yes step_demo.php > /dev/null 2>&1 & echo $!');
echo "📝 Started target script (PID: $script_pid)\n";

// Accept connection
$client = socket_accept($socket);
if ($client) {
    echo "✅ Xdebug connected!\n";
    
    // Read init message
    $init = socket_read($client, 4096);
    echo "📨 Init: " . trim($init) . "\n";
    
    // Set breakpoint at line 7 (function definition)
    $breakpoint_cmd = "breakpoint_set -i 1 -t line -f file:///Users/akihito/git/xdebug-mcp/step_demo.php -n 7\0";
    socket_write($client, $breakpoint_cmd);
    $response = socket_read($client, 4096);
    echo "🔍 Breakpoint response: " . trim($response) . "\n";
    
    // Continue execution
    socket_write($client, "run -i 2\0");
    $response = socket_read($client, 4096);
    echo "▶️ Run response: " . trim($response) . "\n";
    
    // Step into
    socket_write($client, "step_into -i 3\0");
    $response = socket_read($client, 4096);
    echo "👣 Step response: " . trim($response) . "\n";
    
    // Get stack trace
    socket_write($client, "stack_get -i 4\0");
    $response = socket_read($client, 4096);
    echo "📚 Stack: " . trim($response) . "\n";
    
    // Get variables
    socket_write($client, "context_get -i 5 -c 0\0");
    $response = socket_read($client, 4096);
    echo "🔧 Variables: " . trim($response) . "\n";
    
    // Continue to end
    socket_write($client, "run -i 6\0");
    $response = socket_read($client, 4096);
    echo "🏁 Final response: " . trim($response) . "\n";
    
    socket_close($client);
} else {
    echo "❌ Failed to accept connection\n";
}

socket_close($socket);
echo "✅ Step debugging complete!\n";