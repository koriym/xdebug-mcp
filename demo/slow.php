<?php

declare(strict_types=1);

/**
 * Sample Slow Algorithm - For Performance Profiling Demo
 *
 * This script contains intentionally inefficient code for demonstrating xprofile.
 *
 * Performance issues:
 * 1. O(n^3) bubble sort with unnecessary inner loop
 * 2. Inefficient string processing with repeated operations
 *
 * Usage:
 *   ./bin/xprofile -- php demo/slow.php
 */

/**
 * Inefficient bubble sort - O(n^3) complexity
 */
function inefficientSort(array $array): array
{
    $n = count($array);

    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n - 1; $j++) {
            // Unnecessary inner loop - performance bottleneck
            for ($k = 0; $k < 5; $k++) {
                $temp = $array[$j] * 2;
            }

            if ($array[$j] > $array[$j + 1]) {
                $temp = $array[$j];
                $array[$j] = $array[$j + 1];
                $array[$j + 1] = $temp;
            }
        }
    }

    return $array;
}

/**
 * Inefficient string processing
 */
function slowStringProcess(string $text): string
{
    $result = '';

    // Inefficient: strlen() called on every iteration
    for ($i = 0; $i < strlen($text); $i++) {
        // Unnecessary repeated operations
        for ($j = 0; $j < 50; $j++) {
            $char = substr($text, $i, 1);
            $upper = strtoupper($char);
        }
        $result .= strtolower(substr($text, $i, 1));
    }

    return $result;
}

/**
 * Recursive Fibonacci - exponential complexity
 */
function fibonacci(int $n): int
{
    if ($n <= 1) {
        return $n;
    }
    return fibonacci($n - 1) + fibonacci($n - 2);
}

function main(): void
{
    echo "=== Performance Bottleneck Demo ===\n\n";

    // Test 1: Inefficient sort
    $numbers = [64, 34, 25, 12, 22, 11, 90, 5, 77, 30];
    echo "Sorting array with inefficient algorithm...\n";
    $start = microtime(true);
    $sorted = inefficientSort($numbers);
    $time1 = (microtime(true) - $start) * 1000;
    echo "  Time: " . round($time1, 2) . "ms\n";
    echo "  Result: " . implode(', ', $sorted) . "\n\n";

    // Test 2: Slow string processing
    $text = "Hello World from PHP Xdebug Demo";
    echo "Processing string inefficiently...\n";
    $start = microtime(true);
    $processed = slowStringProcess($text);
    $time2 = (microtime(true) - $start) * 1000;
    echo "  Time: " . round($time2, 2) . "ms\n";
    echo "  Result: $processed\n\n";

    // Test 3: Recursive Fibonacci
    echo "Calculating Fibonacci(20) recursively...\n";
    $start = microtime(true);
    $fib = fibonacci(20);
    $time3 = (microtime(true) - $start) * 1000;
    echo "  Time: " . round($time3, 2) . "ms\n";
    echo "  Result: $fib\n\n";

    echo "Total bottleneck time: " . round($time1 + $time2 + $time3, 2) . "ms\n";
}

main();
