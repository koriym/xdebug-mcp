<?php

require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\XdebugClient;

$client = new XdebugClient();

echo "🔗 Starting XdebugClient test...\n";

try {
    // Connect first
    $result = $client->connect();
    echo "✅ Connected: " . json_encode($result) . "\n";
    
    // Set breakpoint at line 18
    echo "🔴 Setting breakpoint at test/debug_test.php:18\n";
    $breakpointId = $client->setBreakpoint(__DIR__ . '/test/debug_test.php', 18);
    echo "✅ Breakpoint ID: {$breakpointId}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}