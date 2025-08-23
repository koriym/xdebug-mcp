<?php
/**
 * Interactive step debugging demonstration
 * This script demonstrates step-by-step execution with various debugging scenarios
 */

echo "🚀 Starting interactive step debugging demo\n";

// Demo 1: Simple variable assignments and arithmetic
echo "\n📝 Demo 1: Variable assignments\n";
$x = 10;  // Breakpoint line 11
$y = 20;  // Breakpoint line 12
$sum = $x + $y; // Step into here
echo "Sum: $x + $y = $sum\n";

// Demo 2: Function calls with parameters
echo "\n🔧 Demo 2: Function calls\n";

function multiply($a, $b) {
    echo "  📊 Multiplying $a × $b\n";
    $result = $a * $b; // Step into this calculation
    echo "  📊 Result: $result\n";
    return $result;
}

$result1 = multiply(5, 3); // Step into function
echo "Multiplication result: $result1\n";

// Demo 3: Loop execution
echo "\n🔄 Demo 3: Loop execution\n";
$numbers = [1, 2, 3];
$total = 0;

foreach ($numbers as $index => $number) { // Step through loop
    echo "  🔢 Processing item $index: $number\n";
    $total += $number; // Step through each iteration
    echo "  📊 Running total: $total\n";
}

echo "Final total: $total\n";

// Demo 4: Conditional logic
echo "\n🎯 Demo 4: Conditional logic\n";
$testValue = 15;

if ($testValue > 10) { // Step into condition
    echo "  ✅ Value $testValue is greater than 10\n";
    $category = "high";
} else {
    echo "  ❌ Value $testValue is not greater than 10\n";
    $category = "low";
}

echo "Category: $category\n";

// Demo 5: Nested function calls
echo "\n🏗️ Demo 5: Nested function calls\n";

function processValue($value) {
    echo "  🔄 Processing value: $value\n";
    return calculateSquare($value); // Step into nested call
}

function calculateSquare($num) {
    echo "    🧮 Calculating square of $num\n";
    $square = $num * $num; // Step into calculation
    echo "    📊 Square result: $square\n";
    return $square;
}

$finalResult = processValue(4); // Step through nested calls
echo "Final result: $finalResult\n";

echo "\n✅ Interactive step debugging demo completed\n";