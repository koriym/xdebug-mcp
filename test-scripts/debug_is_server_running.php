<?php
/**
 * Debug script to test PersistentDebugClient->isServerRunning()
 */

require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\PersistentDebugClient;

echo "🔍 Testing PersistentDebugClient->isServerRunning()\n";

$client = new PersistentDebugClient();

echo "Calling isServerRunning()...\n";
$result = $client->isServerRunning();

echo "Result: " . ($result ? "true" : "false") . "\n";

echo "Calling getStatus() directly...\n";
$status = $client->getStatus();
echo "Status response: " . $status . "\n";

echo "✅ Debug test completed\n";