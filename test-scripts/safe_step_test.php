<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🛡️ Safe step debugging test...\n";

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes slow_test.php',
    [],
    $pipes
);

usleep(100000); // Give more time for connection

try {
    $client = new XdebugClient();
    if ($client->connect()) {
        echo "✅ Connected!\n";
        
        // Set breakpoint at fibonacci call
        echo "\n🎯 Setting breakpoint at fibonacci call...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/slow_test.php', 22);
        $client->continue();
        
        echo "👣 Step into fibonacci function...\n";
        $client->stepInto();
        
        // Get variables in fibonacci
        echo "🔧 Variables in fibonacci:\n";
        $vars = $client->getVariables();
        if (isset($vars['property'])) {
            foreach ($vars['property'] as $prop) {
                $name = $prop['@attributes']['name'] ?? 'unknown';
                $value = $prop['#text'] ?? 'uninitialized';
                echo "  📋 $name: $value\n";
            }
        }
        
        // Step out
        echo "\n↩️ Step out of fibonacci...\n";
        $client->stepOut();
        
        echo "▶️ Continue execution...\n";
        $client->continue();
        
        echo "✅ Safe step debugging complete!\n";
        
        // Test safe disconnection (even if script ended)
        $client->disconnect();
        echo "✅ Safe disconnect complete!\n";
        
    } else {
        echo "❌ Failed to connect\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "But this error is now handled gracefully!\n";
} finally {
    proc_terminate($script_process);
}