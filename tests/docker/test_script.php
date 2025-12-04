<?php

/**
 * Basic test script for Docker integration testing
 *
 * Tests:
 * - Basic function calls
 * - Recursion
 * - Variable state changes
 * - Array operations
 */

declare(strict_types=1);

echo "=== Docker Integration Test Script ===\n\n";

/**
 * Calculate Fibonacci number recursively
 */
function fibonacci(int $n): int
{
    if ($n <= 1) {
        return $n;
    }

    return fibonacci($n - 1) + fibonacci($n - 2);
}

/**
 * Calculate factorial iteratively
 */
function factorial(int $n): int
{
    $result = 1;
    for ($i = 2; $i <= $n; $i++) {
        $result *= $i;
    }

    return $result;
}

/**
 * Process array data
 */
function processArray(array $data): array
{
    $result = [];

    foreach ($data as $key => $value) {
        $result[$key] = [
            'original' => $value,
            'squared' => $value * $value,
            'isEven' => $value % 2 === 0,
        ];
    }

    return $result;
}

/**
 * Simulate user data processing
 */
function processUser(array $user): array
{
    $user['processed'] = true;
    $user['timestamp'] = time();
    $user['name_upper'] = strtoupper($user['name'] ?? 'unknown');

    return $user;
}

// Test execution
echo "1. Testing Fibonacci:\n";
$fibResult = fibonacci(10);
echo "   fibonacci(10) = $fibResult\n\n";

echo "2. Testing Factorial:\n";
$factResult = factorial(5);
echo "   factorial(5) = $factResult\n\n";

echo "3. Testing Array Processing:\n";
$numbers = [1, 2, 3, 4, 5];
$processed = processArray($numbers);
echo "   Processed " . count($processed) . " items\n\n";

echo "4. Testing User Processing:\n";
$user = ['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com'];
$processedUser = processUser($user);
echo "   User processed: " . ($processedUser['processed'] ? 'yes' : 'no') . "\n\n";

echo "=== Test Complete ===\n";
echo "Memory used: " . number_format(memory_get_peak_usage(true) / 1024) . " KB\n";
