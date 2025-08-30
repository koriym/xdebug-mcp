<?php

declare(strict_types=1);

/**
 * Demo script for README examples
 * Contains intentional bugs to demonstrate debugging capabilities
 */
function calculateTotal(array $items): float
{
    $total = 0;  // Fixed: Initialize as number
    foreach ($items as $item) {
        // Bug: String prices will still cause issues in calculation
        if (is_string($item['price'])) {
            echo "⚠️ Warning: Found string price '{$item['price']}' - converting to float\n";
            $total += (float) $item['price'];
        } else {
            $total += $item['price'];
        }
    }

    return $total;
}

function processUser($id)
{
    // Bug: $id can be 0 or null, causing issues
    if ($id <= 0) {
        echo "Processing user with invalid ID: $id\n";

        return null;
    }

    return "User $id processed successfully";
}

function fibonacci($n): int
{
    // Intentionally inefficient for profiling demo
    if ($n <= 1) {
        return $n;
    }

    return fibonacci($n - 1) + fibonacci($n - 2);
}

// Demo scenarios
echo "=== Demo Script ===\n";

// Scenario 1: Type confusion bug
$cart = [
    ['name' => 'Item 1', 'price' => 10.50],   // float
    ['name' => 'Item 2', 'price' => '5.25'],  // string - will cause concatenation!
    ['name' => 'Item 3', 'price' => 15.00],   // float
];

echo 'Cart total: ' . calculateTotal($cart) . "\n";

// Scenario 2: Invalid ID processing
$userIds = [1, 0, 5, null];
foreach ($userIds as $id) {
    $result = processUser($id);
    if ($result === null) {
        echo "Failed to process user: $id\n";
    }
}

// Scenario 3: Performance issue
echo 'Fibonacci(8): ' . fibonacci(8) . "\n";

echo "=== Demo Complete ===\n";
