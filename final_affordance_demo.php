<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🎯 Final affordance-based step debugging demo...\n";

function showAffordances($response, $label = '') {
    if ($label) echo "\n🏷️ $label:\n";
    
    if (isset($response['_affordances'])) {
        echo "🎯 Available actions: " . implode(', ', $response['_affordances']) . "\n";
        return $response['_affordances'];
    }
    
    // Check session status if no affordances in response
    if (isset($response['status'])) {
        echo "📊 Status: " . $response['status'] . "\n";
        echo "💬 Message: " . ($response['message'] ?? 'Unknown') . "\n";
        if (isset($response['_affordances'])) {
            echo "🎯 Available actions: " . implode(', ', $response['_affordances']) . "\n";
            return $response['_affordances'];
        }
    }
    
    return [];
}

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes step_demo.php',
    [],
    $pipes
);

usleep(100000);

try {
    $client = new XdebugClient();
    
    // Show initial session status
    $initialStatus = $client->getSessionStatus();
    showAffordances($initialStatus, 'Initial Session');
    
    if ($client->connect()) {
        echo "\n✅ Connected successfully!\n";
        
        // Show session status after connection
        $connectedStatus = $client->getSessionStatus();
        $affordances = showAffordances($connectedStatus, 'After Connection');
        
        // Set breakpoint using affordances
        if (in_array('continue', $affordances) || in_array('step_over', $affordances)) {
            echo "\n🎯 Setting breakpoint at line 18...\n";
            $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 18);
            
            echo "▶️ Continuing to breakpoint...\n";
            $result = $client->continue();
            $affordances = showAffordances($result, 'At Breakpoint');
            
            // Step into function with affordance check
            if (in_array('step_into', $affordances)) {
                echo "\n👣 Step into function...\n";
                $result = $client->stepInto();
                
                // Check if session is still active
                if (isset($result['status']) && $result['status'] === 'disconnected') {
                    showAffordances($result, 'Session Disconnected');
                } else {
                    $affordances = showAffordances($result, 'Inside Function');
                    
                    // Get variables if available
                    if (in_array('get_variables', $affordances)) {
                        echo "\n🔧 Getting variables inside function...\n";
                        $vars = $client->getVariables();
                        if (isset($vars['property'])) {
                            foreach ($vars['property'] as $prop) {
                                $name = $prop['@attributes']['name'] ?? 'unknown';
                                $value = $prop['#text'] ?? 'uninitialized';
                                echo "  📋 $name ($value)\n";
                            }
                        }
                    }
                    
                    // Step out if available
                    if (in_array('step_out', $affordances)) {
                        echo "\n↩️ Step out of function...\n";
                        $result = $client->stepOut();
                        showAffordances($result, 'After Step Out');
                    }
                }
            }
        }
        
        // Final session check
        $finalStatus = $client->getSessionStatus();
        showAffordances($finalStatus, 'Final Session Status');
        
        $client->disconnect();
        echo "\n✅ Affordance-based debugging demo complete! 🎉\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error handled gracefully: " . $e->getMessage() . "\n";
    echo "This demonstrates robust error handling with affordances!\n";
} finally {
    proc_terminate($script_process);
}