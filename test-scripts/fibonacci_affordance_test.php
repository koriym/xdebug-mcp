<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔢 Fibonacci with affordance-based step debugging...\n";

function displayAffordances($response) {
    if (isset($response['_affordances'])) {
        echo "🎯 Available: " . implode(', ', $response['_affordances']) . "\n";
        return $response['_affordances'];
    }
    return [];
}

function safeExecute($client, $action, $affordances) {
    if (in_array($action, $affordances)) {
        echo "✅ Executing $action...\n";
        return true;
    } else {
        echo "⚠️ $action not available, skipping\n";
        return false;
    }
}

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes ai_trace_test.php',
    [],
    $pipes
);

usleep(100000);

try {
    $client = new XdebugClient();
    
    // Check if we can connect
    $status = $client->getSessionStatus();
    echo "Initial status: " . $status['message'] . "\n";
    displayAffordances($status);
    
    if ($client->connect()) {
        echo "✅ Connected!\n";
        
        // Set breakpoint at fibonacci function
        echo "\n🎯 Setting breakpoint at fibonacci function (line 6)...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/ai_trace_test.php', 6);
        
        // Continue to breakpoint
        $result = $client->continue();
        echo "Status after continue: " . ($result['@attributes']['status'] ?? 'unknown') . "\n";
        $affordances = displayAffordances($result);
        
        // Track recursive calls safely
        for ($level = 0; $level < 4; $level++) {
            echo "\n--- Fibonacci recursion level $level ---\n";
            
            // Check current session status
            $sessionStatus = $client->getSessionStatus();
            if ($sessionStatus['status'] === 'disconnected') {
                echo "🔌 Session disconnected, stopping\n";
                break;
            }
            
            // Get variables if available
            if (safeExecute($client, 'get_variables', $affordances)) {
                $vars = $client->getVariables();
                if (isset($vars['property'])) {
                    foreach ($vars['property'] as $prop) {
                        $name = $prop['@attributes']['name'] ?? 'unknown';
                        $value = $prop['#text'] ?? 'uninitialized';
                        if ($name === '$n') {
                            echo "📋 Parameter n = $value\n";
                        }
                    }
                }
            }
            
            // Get stack if available
            if (safeExecute($client, 'get_stack', $affordances)) {
                $stack = $client->getStack();
                $stackCount = 0;
                if (isset($stack['stack'])) {
                    $stackCount = is_array($stack['stack']) ? count($stack['stack']) : 1;
                }
                echo "📚 Stack depth: $stackCount\n";
            }
            
            // Try to step into next call
            if (safeExecute($client, 'step_into', $affordances)) {
                $result = $client->stepInto();
                
                // Check if session is still active
                if (isset($result['status']) && $result['status'] === 'disconnected') {
                    echo "🔌 " . $result['message'] . "\n";
                    displayAffordances($result);
                    break;
                }
                
                $affordances = displayAffordances($result);
            } else {
                // If we can't step, try continue
                if (safeExecute($client, 'continue', $affordances)) {
                    $result = $client->continue();
                    
                    if (isset($result['status']) && $result['status'] === 'disconnected') {
                        echo "🔌 " . $result['message'] . "\n";
                        displayAffordances($result);
                        break;
                    }
                    
                    $affordances = displayAffordances($result);
                } else {
                    echo "⚠️ No actions available, ending\n";
                    break;
                }
            }
        }
        
        echo "\n🔍 Final session check:\n";
        $finalStatus = $client->getSessionStatus();
        echo "Final status: " . $finalStatus['message'] . "\n";
        displayAffordances($finalStatus);
        
        $client->disconnect();
        echo "\n✅ Fibonacci affordance debugging complete!\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error handled gracefully: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}