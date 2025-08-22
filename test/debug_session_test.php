<?php

declare(strict_types=1);

/**
 * Simple test script for Xdebug debugging session
 * This script will run indefinitely to maintain a debugging session
 */

echo "🔧 Starting Xdebug debugging session...\n";
echo "💡 This script will run for 5 minutes to allow MCP tool testing\n";
echo "🔌 Debug session should be available on port 9004\n";
echo "📝 In another terminal, run: ./bin/test-with-session\n\n";

$startTime = time();
$counter = 0;

while (time() - $startTime < 300) { // 5 minutes instead of 60 seconds
    $counter++;
    $message = "Iteration: $counter | Time: " . date('H:i:s') . " | Memory: " . memory_get_usage(true);
    echo "$message\n";
    
    // Some sample variables for debugging
    $testArray = ['a' => 1, 'b' => 2, 'c' => 3];
    $testString = "Hello Xdebug Session $counter";
    $testNumber = $counter * 2;
    
    // Add some function calls for stack testing
    testFunction($counter);
    
    sleep(2);
}

echo "\n✅ Debug session completed\n";

function testFunction(int $iteration): void
{
    $localVar = "Local variable in iteration $iteration";
    nestedFunction($localVar);
}

function nestedFunction(string $data): void
{
    $result = strlen($data);
    echo "  📊 Processed: $data (length: $result)\n";
}