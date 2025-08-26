<?php

require_once __DIR__ . '/vendor/autoload.php';

use Koriym\XdebugMcp\DebugServer;
use Koriym\XdebugMcp\Constants;

echo "🎯 Testing 18-line breakpoint with debug_test.php\n";

// Create DebugServer with explicit 18-line breakpoint
$server = new DebugServer(
    targetScript: __DIR__ . '/test/debug_test.php',
    debugPort: Constants::XDEBUG_DEBUG_PORT,
    initialBreakpointLine: 18,
    options: [
        'maxSteps' => 3,  // Limit steps for cleaner output
        'executionTimeout' => 30.0,
        'readTimeout' => 5.0
    ]
);

try {
    $server();
    echo "✅ Debug session completed successfully\n";
} catch (Exception $e) {
    echo "❌ Debug session failed: " . $e->getMessage() . "\n";
}