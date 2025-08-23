<?php
require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

echo "🔍 Debug variable format...\n";

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
        
        $client->setBreakpoint('/Users/akihito/git/xdebug-mcp/step_demo.php', 18);
        $client->continue();
        
        echo "🔧 Raw variables output:\n";
        $vars = $client->getVariables();
        
        echo "Type: " . gettype($vars) . "\n";
        echo "Is array: " . (is_array($vars) ? "YES" : "NO") . "\n";
        echo "Count: " . (is_countable($vars) ? count($vars) : "N/A") . "\n";
        
        echo "\nFull dump:\n";
        var_dump($vars);
        
        echo "\nJSON representation:\n";
        echo json_encode($vars, JSON_PRETTY_PRINT) . "\n";
        
        $client->disconnect();
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    proc_terminate($script_process);
}