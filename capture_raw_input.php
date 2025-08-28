<?php

// Capture and log all raw input to MCP server for debugging

echo "Starting raw input capture for MCP debugging...\n";
echo "This will log all stdin input to /tmp/mcp_raw_input.log\n";
echo "Press Ctrl+C to stop.\n\n";

$logFile = '/tmp/mcp_raw_input.log';
file_put_contents($logFile, "=== MCP Raw Input Capture Started: " . date('Y-m-d H:i:s') . " ===\n");

while (true) {
    $input = stream_get_contents(STDIN);
    
    if ($input === false || feof(STDIN)) {
        break;
    }
    
    if (!empty(trim($input))) {
        $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
        
        file_put_contents($logFile, "\n--- Input at $timestamp ---\n", FILE_APPEND);
        file_put_contents($logFile, "Raw length: " . strlen($input) . "\n", FILE_APPEND);
        file_put_contents($logFile, "Hex dump: " . bin2hex($input) . "\n", FILE_APPEND);
        file_put_contents($logFile, "Raw content:\n" . $input . "\n", FILE_APPEND);
        
        // Try to parse as JSON
        $decoded = json_decode(trim($input), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            file_put_contents($logFile, "Parsed JSON:\n" . json_encode($decoded, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            
            // Extract script argument if present
            if (isset($decoded['params']['arguments']['script'])) {
                $script = $decoded['params']['arguments']['script'];
                file_put_contents($logFile, "Script argument: " . var_export($script, true) . "\n", FILE_APPEND);
                file_put_contents($logFile, "Script hex: " . bin2hex($script) . "\n", FILE_APPEND);
            }
        } else {
            file_put_contents($logFile, "JSON Parse Error: " . json_last_error_msg() . "\n", FILE_APPEND);
        }
        
        file_put_contents($logFile, "--- End Input ---\n", FILE_APPEND);
        
        // Echo back for debugging
        echo "Captured input (length: " . strlen($input) . ")\n";
        echo "Check /tmp/mcp_raw_input.log for details\n\n";
    }
}

echo "Input capture finished.\n";