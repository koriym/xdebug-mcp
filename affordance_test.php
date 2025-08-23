<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔗 Testing affordance-based step execution...\n";

function displayAffordances($response) {
    if (isset($response['_affordances'])) {
        echo "🎯 Available actions: " . implode(', ', $response['_affordances']) . "\n";
        return $response['_affordances'];
    }
    return [];
}

function executeIfAfforded($client, $action, $affordances) {
    if (in_array($action, $affordances)) {
        echo "✅ $action is available, executing...\n";
        return true;
    } else {
        echo "❌ $action not available in current state\n";
        return false;
    }
}

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes step_demo.php',
    [],
    $pipes
);

usleep(100000);

try {
    $client = new XdebugClient();
    
    // Check initial session status
    echo "\n🔍 Initial session status:\n";
    $status = $client->getSessionStatus();
    echo "Status: " . $status['status'] . "\n";
    echo "Message: " . $status['message'] . "\n";
    displayAffordances($status);
    
    if ($client->connect()) {
        echo "\n✅ Connected!\n";
        
        // Check session status after connection
        $status = $client->getSessionStatus();
        $affordances = displayAffordances($status);
        
        // Set breakpoint if available
        if (executeIfAfforded($client, 'continue', $affordances)) {
            $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 18);
            $result = $client->continue();
            
            echo "\n📍 After continue:\n";
            $affordances = displayAffordances($result);
            
            // Try step_into if available
            if (executeIfAfforded($client, 'step_into', $affordances)) {
                $result = $client->stepInto();
                
                echo "\n👣 After step_into:\n";
                echo "Status: " . ($result['@attributes']['status'] ?? 'unknown') . "\n";
                $affordances = displayAffordances($result);
                
                // Try get_variables if available
                if (executeIfAfforded($client, 'get_variables', $affordances)) {
                    $vars = $client->getVariables();
                    echo "Variables retrieved successfully\n";
                }
                
                // Try step_over if available  
                if (executeIfAfforded($client, 'step_over', $affordances)) {
                    $result = $client->stepOver();
                    
                    echo "\n↪️ After step_over:\n";
                    $affordances = displayAffordances($result);
                }
            }
        }
        
        echo "\n🔍 Final session status:\n";
        $finalStatus = $client->getSessionStatus();
        displayAffordances($finalStatus);
        
        $client->disconnect();
        echo "\n✅ Affordance-based debugging complete!\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "But affordances help us understand what went wrong!\n";
} finally {
    proc_terminate($script_process);
}