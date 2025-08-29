<?php
// MCP Port 9004 Test Script
echo "Testing MCP connection on port 9004...\n";

function test_function($value) {
    $result = $value * 2;
    echo "Processing value: $value -> $result\n";
    return $result;
}

$data = [1, 2, 3, 4, 5];
$results = [];

foreach ($data as $item) {
    $results[] = test_function($item);
}

echo "Results: " . implode(', ', $results) . "\n";
echo "MCP port test completed.\n";