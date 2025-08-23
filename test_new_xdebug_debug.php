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
        $bp1 = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/test-scripts/buggy_calculation_code.php', 17);
        echo "Breakpoint ID: $bp1\n";
        
        // Continue to first breakpoint
        echo "\n▶️ Continuing to breakpoint...\n";
        $result = $client->continue();
        
        echo "📍 Status: " . ($result['@attributes']['status'] ?? 'unknown') . "\n";
        
        // Get variables at breakpoint
        echo "\n🔧 Variables at breakpoint:\n";
        $vars = $client->getVariables();
        if (isset($vars['property'])) {
            foreach ($vars['property'] as $prop) {
                $name = $prop['@attributes']['name'] ?? 'unknown';
                $type = $prop['@attributes']['type'] ?? 'unknown';
                $value = $prop['#text'] ?? 'uninitialized';
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