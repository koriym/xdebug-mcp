<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "👣 Testing different step commands...\n";

function displayCurrentLocation($client) {
    $stack = $client->getStack();
    if (isset($stack['stack']['@attributes'])) {
        $attrs = $stack['stack']['@attributes'];
        $file = basename($attrs['filename'] ?? 'unknown');
        $line = $attrs['lineno'] ?? '?';
        $func = $attrs['where'] ?? 'unknown';
        echo "📍 Current: $func at $file:$line\n";
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
        
        // Set breakpoint at function call
        echo "\n🎯 Setting breakpoint at function call (line 18)...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 18);
        $client->continue();
        
        displayCurrentLocation($client);
        
        // Step INTO function
        echo "\n👣 STEP INTO function...\n";
        $client->stepInto();
        displayCurrentLocation($client);
        
        // Step OVER next line (echo statement)
        echo "\n↪️ STEP OVER (echo statement)...\n";
        $client->stepOver();
        displayCurrentLocation($client);
        
        // Step OVER calculation
        echo "\n↪️ STEP OVER (calculation)...\n";
        $client->stepOver();
        displayCurrentLocation($client);
        
        // Step OVER echo result
        echo "\n↪️ STEP OVER (echo result)...\n";
        $client->stepOver();
        displayCurrentLocation($client);
        
        // Step OUT of function
        echo "\n↩️ STEP OUT of function...\n";
        $client->stepOut();
        displayCurrentLocation($client);
        
        // Check we're back in main
        echo "\n🔧 Variables after stepping out:\n";
        $vars = $client->getVariables();
        if (isset($vars['property'])) {
            foreach ($vars['property'] as $prop) {
                $name = $prop['@attributes']['name'] ?? 'unknown';
                $type = $prop['@attributes']['type'] ?? 'unknown';
                $value = $prop['#text'] ?? 'uninitialized';
                echo "  📋 $name ($type): $value\n";
            }
        }
        
        $client->disconnect();
        echo "\n✅ Step commands test complete!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}