<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔍 Proper variable inspection test...\n";

function displayVariables($vars) {
    if (isset($vars['property'])) {
        echo "Variables found:\n";
        foreach ($vars['property'] as $prop) {
            $name = $prop['@attributes']['name'] ?? 'unknown';
            $type = $prop['@attributes']['type'] ?? 'unknown';
            $value = $prop['#text'] ?? 'uninitialized';
            
            echo "  📋 $name ($type): $value\n";
        }
    } else {
        echo "No variables found\n";
    }
}

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes step_demo.php',
    [],
    $pipes
);

usleep(50000);

try {
    $client = new XdebugClient();
    if ($client->connect()) {
        echo "✅ Connected!\n";
        
        // Breakpoint at variable initialization
        echo "\n🎯 Breakpoint at line 14 (before \$x assignment)...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 14);
        $client->continue();
        
        $vars1 = $client->getVariables();
        displayVariables($vars1);
        
        // Step over
        echo "\n👣 Step over to line 15...\n";
        $client->stepOver();
        $vars2 = $client->getVariables();
        displayVariables($vars2);
        
        // Step over again
        echo "\n👣 Step over to line 16...\n";
        $client->stepOver();
        $vars3 = $client->getVariables();
        displayVariables($vars3);
        
        // Step to function call
        echo "\n👣 Step over to function call (line 18)...\n";
        $client->stepOver();
        
        // Step INTO function
        echo "\n👣 Step INTO calculate function...\n";
        $client->stepInto();
        
        echo "\n🏛️ Stack trace in function:\n";
        $stack = $client->getStack();
        foreach ($stack as $level => $frame) {
            echo "  Level $level: {$frame['function']} at {$frame['filename']}:{$frame['lineno']}\n";
        }
        
        echo "\n🔧 Variables inside function:\n";
        $vars4 = $client->getVariables();
        displayVariables($vars4);
        
        $client->disconnect();
        echo "\n✅ Comprehensive variable inspection complete!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}