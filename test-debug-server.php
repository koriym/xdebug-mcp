<?php
require_once __DIR__ . "/vendor/autoload.php";

echo "Creating DebugServer instance...\n";

try {
    $server = new \Koriym\XdebugMcp\DebugServer(
        'test-http-debug.php',
        9004,
        null,
        ["context" => "Test HTTP debug", "http-port" => 8080],
        false
    );
    
    echo "DebugServer created successfully\n";
    echo "Enabling HTTP mode...\n";
    
    $server->enableHttpMode(8080);
    echo "HTTP mode enabled\n";
    
    echo "Starting server...\n";
    $server(); // Use __invoke()
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}