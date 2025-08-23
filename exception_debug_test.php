<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🚨 Exception handling step debugging test...\n";

function displayVariableDetails($vars) {
    echo "🔧 Variables:\n";
    $values = [];
    
    if (isset($vars['property'])) {
        foreach ($vars['property'] as $prop) {
            $name = $prop['@attributes']['name'] ?? 'unknown';
            $type = $prop['@attributes']['type'] ?? 'unknown';
            $value = $prop['#text'] ?? 'uninitialized';
            
            echo "  📋 $name ($type): $value\n";
            $values[$name] = $value;
        }
    }
    
    return $values;
}

$script_process = proc_open(
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes exception_test.php',
    [],
    $pipes
);

usleep(100000);

try {
    $client = new XdebugClient();
    
    if ($client->connect()) {
        echo "✅ Connected for exception debugging!\n";
        
        // Set breakpoint at start of risky function
        echo "\n🎯 Setting breakpoint in riskyCalculation function...\n";
        $bp1 = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/exception_test.php', 6);
        echo "Breakpoint: $bp1\n";
        
        // Set breakpoint at exception throw
        echo "🎯 Setting breakpoint at exception throw...\n";
        $bp2 = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/exception_test.php', 9);
        echo "Exception breakpoint: $bp2\n";
        
        // Start execution
        echo "\n▶️ Starting exception test...\n";
        $result = $client->continue();
        
        // Should hit first breakpoint (first call with valid params)
        echo "\n📍 First call - riskyCalculation(10, 2):\n";
        $vars1 = $client->getVariables();
        $values1 = displayVariableDetails($vars1);
        
        // Continue to see normal execution
        echo "\n▶️ Continue through normal case...\n";
        $client->continue();
        
        // Should hit breakpoint again for second call
        echo "\n📍 Second call - riskyCalculation(10, 0):\n";
        $vars2 = $client->getVariables();
        $values2 = displayVariableDetails($vars2);
        
        // Step to the exception condition
        echo "\n👣 Step to exception condition check...\n";
        $client->stepOver(); // Skip echo
        $client->stepOver(); // Hit if condition
        
        echo "\n🔧 Variables at exception point:\n";
        $vars3 = $client->getVariables();
        displayVariableDetails($vars3);
        
        // Step into exception throwing
        echo "\n👣 Step into exception throw...\n";
        $client->stepOver();
        
        echo "\n🚨 Exception should be thrown now\n";
        echo "▶️ Continue to catch block...\n";
        $client->continue();
        
        // Check if we're in catch block or main
        echo "\n📍 After exception handling:\n";
        $vars4 = $client->getVariables();
        displayVariableDetails($vars4);
        
        echo "\n✅ Exception debugging complete!\n";
        $client->disconnect();
    }
    
} catch (Exception $e) {
    echo "\n❌ Error during exception debugging: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}