<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔍 Variable inspection test...\n";

// Start target script
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
        
        // Set breakpoint at variable assignment
        echo "🎯 Setting breakpoint at line 14 (variable assignment)...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 14);
        
        echo "▶️ Running to breakpoint...\n";
        $client->continue();
        
        echo "🔧 Getting variables at line 14...\n";
        $vars1 = $client->getVariables();
        echo "Variables count: " . count($vars1) . "\n";
        foreach ($vars1 as $var) {
            echo "- {$var['name']}: {$var['value']}\n";
        }
        
        // Step to next line
        echo "\n👣 Step over to next line...\n";
        $client->stepOver();
        
        echo "🔧 Getting variables after step...\n";
        $vars2 = $client->getVariables();
        foreach ($vars2 as $var) {
            echo "- {$var['name']}: {$var['value']}\n";
        }
        
        // Set breakpoint in function
        echo "\n🎯 Setting breakpoint in calculate function (line 8)...\n";
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 8);
        
        echo "▶️ Continue to function...\n";
        $client->continue();
        
        echo "🔧 Variables inside function...\n";
        $vars3 = $client->getVariables();
        foreach ($vars3 as $var) {
            echo "- {$var['name']}: {$var['value']}\n";
        }
        
        // Test expression evaluation
        echo "\n🧮 Evaluating expressions...\n";
        try {
            $result1 = $client->eval('$a + $b');
            echo "Expression \$a + \$b = $result1\n";
            
            $result2 = $client->eval('$a * 2');
            echo "Expression \$a * 2 = $result2\n";
        } catch (Exception $e) {
            echo "Expression eval failed: " . $e->getMessage() . "\n";
        }
        
        $client->disconnect();
        echo "\n✅ Variable inspection complete!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}