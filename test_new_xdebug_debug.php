<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔗 Testing connection to xdebug-debug session...\n";

try {
    $client = new XdebugClient();
    
    if ($client->connect()) {
        echo "✅ Connected to debugging session!\n\n";
        
        // Set a strategic breakpoint at the bug location
        echo "🎯 Setting breakpoint at line 17 (zero exclusion bug)...\n";
        $buggyScript = realpath(__DIR__ . '/test-scripts/buggy_calculation_code.php');
        if ($buggyScript === false) {
            throw new RuntimeException('buggy_calculation_code.php not found relative to repo root.');
        }
        $bp1 = $client->setBreakpoint($buggyScript, 17);
        echo "Breakpoint ID: $bp1\n";
        
        // Continue to first breakpoint
        echo "\n▶️ Continuing to breakpoint...\n";
        $result = $client->continue();
        
        echo "📍 Status: " . ($result['@attributes']['status'] ?? 'unknown') . "\n";
        
        // Get variables at breakpoint
        echo "\n🔧 Variables at breakpoint:\n";
        $vars = $client->getVariables();
        if (isset($vars['property'])) {
            // Normalize single property into array
            $props = isset($vars['property'][0]) ? $vars['property'] : [$vars['property']];
            
            foreach ($props as $prop) {
                $name = $prop['@attributes']['name'] ?? 'unknown';
                $type = $prop['@attributes']['type'] ?? 'unknown';
                $raw = $prop['#text'] ?? null;
                
                // Handle base64 encoded values
                if ($raw !== null && ($prop['@attributes']['encoding'] ?? '') === 'base64') {
                    $decoded = base64_decode($raw);
                    $value = $decoded !== false ? $decoded : 'uninitialized';
                } else {
                    $value = $raw ?? 'uninitialized';
                }
                
                echo "  📋 $name ($type): $value\n";
            }
        }
        
        // Step over the condition to see what happens
        echo "\n👣 Step over the condition...\n";
        $client->stepOver();
        
        // Continue execution
        echo "\n▶️ Continuing execution...\n";
        $client->continue();
        
        $client->disconnect();
        echo "\n✅ Step debugging test complete!\n";
        
    } else {
        echo "❌ Could not connect to debug session\n";
        echo "Make sure ./bin/xdebug-debug is running\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}