<?php
/**
 * Slower test script for step debugging
 */

function fibonacci($n) {
    if ($n <= 1) {
        return $n;
    }
    return fibonacci($n - 1) + fibonacci($n - 2);
}

function processArray($data) {
    $result = [];
    foreach ($data as $key => $value) {
        $result[$key] = $value * 2;
    }
    return $result;
}

echo "🚀 Starting slow test...\n";

$numbers = [1, 2, 3, 4];
echo "📝 Processing array...\n";
$doubled = processArray($numbers);

echo "🔢 Calculating fibonacci...\n";
$fib = fibonacci(3); // Smaller number to avoid too much recursion

echo "✅ Results:\n";
echo "Fibonacci(3) = $fib\n";
echo "Doubled: " . implode(', ', $doubled) . "\n";

// Add some sleep to keep the script running longer
echo "⏳ Waiting...\n";
sleep(5);

echo "🏁 Test complete!\n";