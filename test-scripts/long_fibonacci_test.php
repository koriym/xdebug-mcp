<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔢 Long fibonacci affordance test...\n";

function displayAffordances($response) {
    if (isset($response['_affordances'])) {
        echo "🎯 Available: " . implode(', ', $response['_affordances']) . "\n";
        return $response['_affordances'];
    }
    return [];
}

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes long_fibonacci.php',
    [],
    $pipes
);

// Wait for script to start
sleep(1);

try {
    $client = new XdebugClient();
    
    if ($client->connect()) {
        echo "✅ Connected!\n";
        
        // Set breakpoint at fibonacci function entry
        echo "\n🎯 Setting breakpoint at fibonacci function (line 6)...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/long_fibonacci.php', 6);
        
        // Continue to hit breakpoint
        echo "▶️ Continuing to breakpoint...\n";
        $result = $client->continue();
        
        echo "📍 Reached breakpoint!\n";
        echo "Status: " . ($result['@attributes']['status'] ?? 'unknown') . "\n";
        $affordances = displayAffordances($result);
        
        // Now we should be at the breakpoint with good affordances
        if (in_array('get_variables', $affordances)) {
            echo "\n🔧 Getting variables:\n";
            $vars = $client->getVariables();
            if (isset($vars['property'])) {
                foreach ($vars['property'] as $prop) {
                    $name = $prop['@attributes']['name'] ?? 'unknown';
                    $value = $prop['#text'] ?? 'uninitialized';
                    echo "  📋 $name: $value\n";
                }
            }
        }
        
        if (in_array('get_stack', $affordances)) {
            echo "\n📚 Getting stack:\n";
            $stack = $client->getStack();
            if (isset($stack['stack'])) {
                echo "  Stack frames available\n";
            }
        }
        
        // Try step into
        if (in_array('step_into', $affordances)) {
            echo "\n👣 Step into...\n";
            $result = $client->stepInto();
            $affordances = displayAffordances($result);
            
            // Check if we can still interact
            if (isset($result['status']) && $result['status'] === 'disconnected') {
                echo "🔌 Session ended: " . $result['message'] . "\n";
            } else {
                echo "✅ Step successful!\n";
                
                // Try one more step
                if (in_array('step_over', $affordances)) {
                    echo "\n↪️ Step over...\n";
                    $result = $client->stepOver();
                    displayAffordances($result);
                }
            }
        }
        
        echo "\n▶️ Continuing execution to completion...\n";
        if (in_array('continue', $affordances)) {
            $client->continue();
        }
        
        $client->disconnect();
        echo "\n✅ Long fibonacci affordance test complete!\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}