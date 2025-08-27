<?php
require_once __DIR__ . "/vendor/autoload.php";

echo "Creating simple HTTP debug test...\n";

try {
    $server = new \Koriym\XdebugMcp\DebugServer(
        'test-http-debug.php',
        9004,
        null,
        ["context" => "Simple HTTP test"],
        false
    );
    
    echo "Enabling HTTP mode on port 8081...\n";
    $server->enableHttpMode(8081);
    
    echo "HTTP debug server is starting...\n";
    echo "Access: http://127.0.0.1:8081/debug/status\n";
    echo "Press Ctrl+C to stop\n";
    
    // This will run indefinitely, showing the HTTP server is working
    $server();
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}