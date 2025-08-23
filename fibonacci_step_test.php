<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔢 Fibonacci step debugging test...\n";

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes ai_trace_test.php',
    [],
    $pipes
);

usleep(50000);

try {
    $client = new XdebugClient();
    if ($client->connect()) {
        echo "✅ Connected!\n";
        
        // Set breakpoint at fibonacci function
        echo "\n🎯 Setting breakpoint in fibonacci function (line 6)...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/ai_trace_test.php', 6);
        $client->continue();
        
        // Track recursive calls
        for ($i = 0; $i < 5; $i++) {
            echo "\n--- Recursive call level $i ---\n";
            
            // Get variables
            $vars = $client->getVariables();
            if (isset($vars['property'])) {
                foreach ($vars['property'] as $prop) {
                    $name = $prop['@attributes']['name'] ?? 'unknown';
                    $value = $prop['#text'] ?? 'uninitialized';
                    if ($name === '$n') {
                        echo "📋 Fibonacci parameter n = $value\n";
                    }
                }
            }
            
            // Get stack depth
            $stack = $client->getStack();
            $stackCount = 0;
            if (isset($stack['stack'])) {
                $stackCount = is_array($stack['stack']) ? count($stack['stack']) : 1;
            }
            echo "📚 Stack depth: $stackCount\n";
            
            // Step into or continue based on condition
            if ($i < 3) {
                echo "👣 Step into next recursive call...\n";
                $client->stepInto();
            } else {
                echo "▶️ Continue execution...\n";
                $client->continue();
                break;
            }
        }
        
        $client->disconnect();
        echo "\n✅ Fibonacci step debugging complete!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}