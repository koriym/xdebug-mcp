<?php

declare(strict_types=1);

/**
 * Demo script for breakpoint testing
 * This script is designed to run with breakpoints
 */

echo "🚀 Starting Breakpoint Demo\n";

class SimpleCalculator
{
    public function add($a, $b)
    {
        echo "Adding $a + $b\n"; // Line 12 - Breakpoint here
        $result = $a + $b;
        echo "Result: $result\n";

        return $result;
    }

    public function multiply($a, $b)
    {
        echo "Multiplying $a * $b\n"; // Line 19 - Breakpoint here
        $result = $a * $b;
        echo "Result: $result\n";

        return $result;
    }
}

$calc = new SimpleCalculator();

echo "=== Test 1: Addition ===\n";
$sum = $calc->add(10, 20); // Line 28 - Breakpoint here

echo "=== Test 2: Multiplication ===\n";
$product = $calc->multiply(5, 6); // Line 31 - Breakpoint here

echo "Final results: sum=$sum, product=$product\n";
echo "✅ Demo completed\n";
