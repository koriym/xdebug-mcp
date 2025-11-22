#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Simple test script for Docker integration testing
 */

echo "=== Docker Integration Test Script ===\n";
echo 'PHP Version: ' . PHP_VERSION . "\n";
echo 'Xdebug loaded: ' . (extension_loaded('xdebug') ? 'YES' : 'NO') . "\n";

if (extension_loaded('xdebug')) {
    echo 'Xdebug version: ' . phpversion('xdebug') . "\n";
    echo 'Xdebug mode: ' . ini_get('xdebug.mode') . "\n";
}

echo "\n=== Running Test Functions ===\n";

function fibonacci(int $n): int
{
    if ($n <= 1) {
        return $n;
    }

    return fibonacci($n - 1) + fibonacci($n - 2);
}

function processData(array $data): array
{
    $result = [];
    foreach ($data as $item) {
        $result[] = strtoupper((string) $item);
    }

    return $result;
}

function calculateSum(int ...$numbers): int
{
    return array_sum($numbers);
}

// Test 1: Recursive function
echo '1. Fibonacci(10) = ' . fibonacci(10) . "\n";

// Test 2: Array processing
$testData = ['docker', 'xdebug', 'mcp', 'test'];
$processed = processData($testData);
echo '2. Processed data: ' . implode(', ', $processed) . "\n";

// Test 3: Variadic function
$sum = calculateSum(1, 2, 3, 4, 5);
echo '3. Sum(1,2,3,4,5) = ' . $sum . "\n";

// Test 4: Memory allocation
$largeArray = range(1, 1000);
$memoryUsed = memory_get_usage(true);
echo '4. Memory usage: ' . number_format($memoryUsed / 1024 / 1024, 2) . " MB\n";

echo "\n=== Test Complete ===\n";
echo "This script executed successfully in Docker!\n";
