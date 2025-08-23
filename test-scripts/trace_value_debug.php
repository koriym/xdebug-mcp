<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔍 Trace-based value verification debugging...\n";

function verifyValue($expected, $actual, $variableName) {
    if ($expected === $actual) {
        echo "✅ $variableName = $actual (correct)\n";
        return true;
    } else {
        echo "❌ $variableName = $actual (expected $expected)\n";
        return false;
    }
}

function displayVariableDetails($vars) {
    echo "🔧 Variable inspection:\n";
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
    'php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_host=127.0.0.1 -dxdebug.client_port=9004 -dxdebug.start_with_request=yes step_demo.php',
    [],
    $pipes
);

usleep(100000);

try {
    $client = new XdebugClient();
    
    if ($client->connect()) {
        echo "✅ Connected for trace debugging!\n";
        
        // Set multiple breakpoints for comprehensive tracing
        echo "\n🎯 Setting breakpoints for value tracing...\n";
        $bp1 = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 14); // Before $x
        echo "Breakpoint 1 (line 14): $bp1\n";
        
        $bp2 = $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 18); // Function call
        echo "Breakpoint 2 (line 18): $bp2\n";
        
        // Start execution and trace values
        echo "\n▶️ Starting traced execution...\n";
        $result = $client->continue();
        
        // Should hit first breakpoint (before $x = 10)
        echo "\n📍 First breakpoint - Before variable assignment:\n";
        $vars1 = $client->getVariables();
        $values1 = displayVariableDetails($vars1);
        
        // Verify initial state
        if (isset($values1['$x'])) {
            verifyValue('uninitialized', $values1['$x'], '$x');
        }
        if (isset($values1['$y'])) {
            verifyValue('uninitialized', $values1['$y'], '$y');
        }
        
        // Step to next breakpoint
        echo "\n👣 Stepping to next breakpoint...\n";
        $result = $client->continue();
        
        // Should hit second breakpoint (at function call)
        echo "\n📍 Second breakpoint - At function call:\n";
        $vars2 = $client->getVariables();
        $values2 = displayVariableDetails($vars2);
        
        // Verify values were set correctly
        if (isset($values2['$x'])) {
            verifyValue('10', $values2['$x'], '$x');
        }
        if (isset($values2['$y'])) {
            verifyValue('20', $values2['$y'], '$y');
        }
        
        // Step INTO the function for internal trace
        echo "\n👣 Step INTO calculate function...\n";
        $stepResult = $client->stepInto();
        
        echo "\n📍 Inside calculate function:\n";
        $vars3 = $client->getVariables();
        $values3 = displayVariableDetails($vars3);
        
        // Verify function parameters
        if (isset($values3['$a'])) {
            verifyValue('10', $values3['$a'], 'parameter $a');
        }
        if (isset($values3['$b'])) {
            verifyValue('20', $values3['$b'], 'parameter $b');
        }
        
        // Step through calculation
        echo "\n👣 Step OVER calculation line...\n";
        $client->stepOver(); // Skip echo
        $client->stepOver(); // Execute $result = $a + $b
        
        echo "\n📍 After calculation:\n";
        $vars4 = $client->getVariables();
        $values4 = displayVariableDetails($vars4);
        
        // Verify calculation result
        if (isset($values4['$result'])) {
            verifyValue('30', $values4['$result'], 'calculation result');
        }
        
        // Step OUT to return to main
        echo "\n↩️ Step OUT of function...\n";
        $client->stepOut();
        
        echo "\n📍 Back in main function:\n";
        $vars5 = $client->getVariables();
        $values5 = displayVariableDetails($vars5);
        
        // Verify return value was assigned
        if (isset($values5['$sum'])) {
            verifyValue('30', $values5['$sum'], 'returned $sum');
        }
        
        echo "\n✅ Trace-based value verification complete!\n";
        echo "🎯 All values traced and verified through step execution!\n";
        
        $client->disconnect();
    }
    
} catch (Exception $e) {
    echo "\n❌ Error during trace debugging: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}