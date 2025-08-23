<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🚀 Quick step execution test...\n";

// Start target script
$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes step_demo.php',
    [],
    $pipes
);

usleep(50000); // 50ms

try {
    $client = new XdebugClient();
    echo "🔌 Connecting...\n";
    
    if ($client->connect()) {
        echo "✅ Connected!\n";
        
        echo "🔍 Setting breakpoint...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 18);
        
        echo "▶️ Running to breakpoint...\n";
        $client->continue();
        
        echo "👣 Step into function...\n";
        $client->stepInto();
        
        echo "📚 Getting stack...\n";
        $stack = $client->getStack();
        echo "Stack depth: " . count($stack) . "\n";
        
        echo "✅ Step execution SUCCESS!\n";
        $client->disconnect();
    } else {
        echo "❌ Connection failed\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}