<?php

// Test the exact error scenario

require_once 'src/McpServer.php';

// The error message shows: Received: ""php"
// This suggests the actual value is: "php (missing the rest)

$errorScript = '"php';  // Exactly what the error message shows

echo "Testing exact error scenario:\n";
echo "Input: " . var_export($errorScript, true) . "\n";
echo "Length: " . strlen($errorScript) . "\n"; 
echo "Hex: " . bin2hex($errorScript) . "\n";

// Test our quote stripping logic
$processed = $errorScript;
if (strlen($processed) >= 2 && str_starts_with($processed, '"') && str_ends_with($processed, '"')) {
    $processed = substr($processed, 1, -1);
    echo "After quote stripping: " . var_export($processed, true) . "\n";
} else {
    echo "No quote stripping applied (doesn't match pattern)\n";
    echo "Starts with quote: " . (str_starts_with($processed, '"') ? 'YES' : 'NO') . "\n";
    echo "Ends with quote: " . (str_ends_with($processed, '"') ? 'YES' : 'NO') . "\n";
}

// Test validation
$isValid = preg_match('/^(\S*php)\s+/', $processed);
echo "Validation result: " . ($isValid ? "PASS" : "FAIL") . "\n";

// Let's also test some truncated scenarios
echo "\n\nTesting truncated scenarios:\n";
$truncatedCases = [
    '"php',           // Truncated at start
    'php"',           // Missing opening quote  
    '"php demo.php',  // Missing closing quote
    'php demo.php"',  // Missing opening quote
    '',               // Empty string
];

foreach ($truncatedCases as $case) {
    echo "\nCase: " . var_export($case, true) . "\n";
    $test = $case;
    if (strlen($test) >= 2 && str_starts_with($test, '"') && str_ends_with($test, '"')) {
        $test = substr($test, 1, -1);
        echo "After processing: " . var_export($test, true) . "\n";
    } else {
        echo "No processing: " . var_export($test, true) . "\n";
    }
    $valid = preg_match('/^(\S*php)\s+/', $test);
    echo "Valid: " . ($valid ? "YES" : "NO") . "\n";
}