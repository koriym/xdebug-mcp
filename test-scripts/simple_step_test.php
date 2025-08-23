<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🚀 Starting simple step execution test...\n";

// Start the target script in background
$script_cmd = 'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes step_demo.php';
$script_process = proc_open($script_cmd, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
], $pipes);

if (!$script_process) {
    die("❌ Failed to start target script\n");
}

// Give script time to start
usleep(100000); // 100ms

try {
    $client = new XdebugClient();
    
    echo "🔌 Connecting to Xdebug session...\n";
    $connected = $client->connect('127.0.0.1', 9004);
    
    if (!$connected) {
        echo "❌ Failed to connect to Xdebug\n";
        proc_terminate($script_process);
        exit(1);
    }
    
    echo "✅ Connected to Xdebug!\n";
    
    // Set breakpoint at function definition
    echo "🔍 Setting breakpoint at line 7...\n";
    $result = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 7);
    echo "Breakpoint result: " . ($result ? "✅ Success" : "❌ Failed") . "\n";
    
    // Continue execution
    echo "▶️ Continuing execution...\n";
    $client->continue();
    
    // Step into function
    echo "👣 Stepping into function...\n";
    $client->stepInto();
    
    // Get current stack
    echo "📚 Getting stack trace...\n";
    $stack = $client->getStack();
    print_r($stack);
    
    // Get variables
    echo "🔧 Getting variables...\n";
    $vars = $client->getVariables();
    print_r($vars);
    
    // Step over next instruction
    echo "↪️ Stepping over...\n";
    $client->stepOver();
    
    // Continue to end
    echo "🏁 Continuing to end...\n";
    $client->continue();
    
    $client->disconnect();
    echo "✅ Step debugging completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
    proc_close($script_process);
}