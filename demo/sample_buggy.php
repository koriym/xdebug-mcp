<?php

declare(strict_types=1);

/**
 * Sample Buggy Code - For Debugging Demo
 *
 * This script contains intentional bugs for demonstrating xstep and xtrace debugging.
 *
 * Bugs included:
 * 1. Wrong arithmetic operator (subtraction instead of addition)
 * 2. Off-by-one error in array processing
 *
 * Usage:
 *   ./bin/xstep demo/sample_buggy.php
 *   ./bin/xtrace demo/sample_buggy.php
 */

function calculateSum(int $a, int $b): int
{
    // BUG: Should be $a + $b
    return $a - $b;
}

function calculateAverage(array $numbers): float
{
    $sum = 0;

    // BUG: Off-by-one error - should be count($numbers)
    for ($i = 0; $i < count($numbers) - 1; $i++) {
        $sum += $numbers[$i];
    }

    return $sum / count($numbers);
}

function main(): void
{
    echo "=== Buggy Calculation Demo ===\n\n";

    // Test 1: Sum calculation
    $a = 10;
    $b = 5;
    $sum = calculateSum($a, $b);
    echo "Sum of $a + $b = $sum\n";

    if ($sum === 15) {
        echo "  [OK] Sum is correct\n";
    } else {
        echo "  [BUG] Expected 15, got $sum\n";
    }

    echo "\n";

    // Test 2: Average calculation
    $numbers = [10, 20, 30, 40, 50];
    $average = calculateAverage($numbers);
    echo "Average of [10, 20, 30, 40, 50] = $average\n";

    $expected = 30.0;
    if (abs($average - $expected) < 0.001) {
        echo "  [OK] Average is correct\n";
    } else {
        echo "  [BUG] Expected $expected, got $average\n";
    }
}

main();
