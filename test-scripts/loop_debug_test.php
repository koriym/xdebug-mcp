<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔄 Loop iteration step debugging test...\n";

function displayVariableDetails($vars, $iteration = null) {
    $label = $iteration ? "Iteration $iteration" : "Variables";
    echo "🔧 $label:\n";
    
    if (isset($vars['property'])) {
        foreach ($vars['property'] as $prop) {
            $name = $prop['@attributes']['name'] ?? 'unknown';
            $type = $prop['@attributes']['type'] ?? 'unknown';
            $value = $prop['#text'] ?? 'uninitialized';
            
            // Highlight important loop variables
            if (in_array($name, ['$index', '$num', '$total', '$counter'])) {
                echo "  🎯 $name ($type): $value\n";
            } else {
                echo "  📋 $name ($type): $value\n";
            }
        }
    }
    
    echo "\n";
}

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes loop_test.php',
    [],
    $pipes
);

usleep(100000);

try {
    $client = new XdebugClient();
    
    if ($client->connect()) {
        echo "✅ Connected for loop debugging!\n";
        
        // Set breakpoint inside foreach loop
        echo "\n🎯 Setting breakpoint inside foreach loop...\n";
        $bp1 = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/loop_test.php', 11);
        echo "Foreach breakpoint: $bp1\n";
        
        // Set breakpoint inside while loop
        echo "🎯 Setting breakpoint inside while loop...\n";
        $bp2 = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/loop_test.php', 25);
        echo "While breakpoint: $bp2\n";
        
        // Start execution
        echo "\n▶️ Starting loop test...\n";
        $result = $client->continue();
        
        // Track foreach loop iterations
        echo "\n🔄 === FOREACH LOOP DEBUGGING ===\n";
        for ($iteration = 1; $iteration <= 3; $iteration++) {
            echo "--- Foreach Iteration $iteration ---\n";
            
            $vars = $client->getVariables();
            displayVariableDetails($vars, $iteration);
            
            // Check specific values
            if (isset($vars['property'])) {
                foreach ($vars['property'] as $prop) {
                    $name = $prop['@attributes']['name'];
                    $value = $prop['#text'] ?? 'uninitialized';
                    
                    if ($name === '$index') {
                        echo "📍 Array index: $value\n";
                    }
                    if ($name === '$num') {
                        echo "🔢 Current number: $value\n";
                    }
                    if ($name === '$total') {
                        echo "📊 Running total: $value\n";
                    }
                }
            }
            
            echo "\n▶️ Continue to next iteration...\n";
            $client->continue();
        }
        
        // Now test while loop
        echo "\n🔄 === WHILE LOOP DEBUGGING ===\n";
        for ($iteration = 1; $iteration <= 2; $iteration++) {
            echo "--- While Iteration $iteration ---\n";
            
            $vars = $client->getVariables();
            displayVariableDetails($vars, $iteration);
            
            // Check counter value
            if (isset($vars['property'])) {
                foreach ($vars['property'] as $prop) {
                    $name = $prop['@attributes']['name'];
                    $value = $prop['#text'] ?? 'uninitialized';
                    
                    if ($name === '$counter') {
                        echo "🔢 Counter value: $value\n";
                    }
                }
            }
            
            echo "\n▶️ Continue to next iteration...\n";
            $client->continue();
        }
        
        echo "\n✅ Loop debugging complete!\n";
        $client->disconnect();
    }
    
} catch (Exception $e) {
    echo "\n❌ Error during loop debugging: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}