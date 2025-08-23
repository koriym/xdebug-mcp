<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔢 Synchronized fibonacci affordance test...\n";

function displayAffordances($response) {
    if (isset($response['_affordances'])) {
        echo "🎯 Available: " . implode(', ', $response['_affordances']) . "\n";
        return $response['_affordances'];
    }
    return [];
}

// Start client first and wait for connection
$client = new XdebugClient();
echo "🔌 Starting debug client...\n";

// Start the script after client is listening
$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes long_fibonacci.php',
    [],
    $pipes
);

try {
    echo "⏳ Waiting for Xdebug connection...\n";
    
    if ($client->connect()) {
        echo "✅ Connected successfully!\n";
        
        // Set breakpoint before continuing
        echo "\n🎯 Setting breakpoint at fibonacci entry (line 6)...\n";
        $breakpointId = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/long_fibonacci.php', 6);
        echo "Breakpoint ID: $breakpointId\n";
        
        // Continue to first breakpoint hit
        echo "▶️ Continuing to first breakpoint...\n";
        $result = $client->continue();
        
        $status = $result['@attributes']['status'] ?? 'unknown';
        echo "📍 Status: $status\n";
        
        if ($status === 'break') {
            echo "🎉 Successfully stopped at breakpoint!\n";
            $affordances = displayAffordances($result);
            
            // Get variables at breakpoint
            if (in_array('get_variables', $affordances)) {
                echo "\n🔧 Variables at fibonacci entry:\n";
                $vars = $client->getVariables();
                if (isset($vars['property'])) {
                    foreach ($vars['property'] as $prop) {
                        $name = $prop['@attributes']['name'] ?? 'unknown';
                        $value = $prop['#text'] ?? 'uninitialized';
                        echo "  📋 $name: $value\n";
                    }
                }
            }
            
            // Step through a few instructions
            for ($step = 1; $step <= 3; $step++) {
                echo "\n--- Step $step ---\n";
                
                if (in_array('step_over', $affordances)) {
                    echo "↪️ Step over...\n";
                    $result = $client->stepOver();
                    
                    if (isset($result['status']) && $result['status'] === 'disconnected') {
                        echo "🔌 " . $result['message'] . "\n";
                        displayAffordances($result);
                        break;
                    }
                    
                    $affordances = displayAffordances($result);
                    
                    // Show variables after each step
                    if (in_array('get_variables', $affordances)) {
                        $vars = $client->getVariables();
                        echo "Variables updated\n";
                    }
                } else {
                    echo "⚠️ Step over not available\n";
                    break;
                }
            }
            
            // Continue execution
            echo "\n▶️ Continuing to completion...\n";
            if (in_array('continue', $affordances)) {
                $client->continue();
            }
        } else {
            echo "⚠️ Did not reach breakpoint, status: $status\n";
            displayAffordances($result);
        }
        
        echo "\n✅ Synchronized fibonacci test complete!\n";
        $client->disconnect();
    } else {
        echo "❌ Failed to connect\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}