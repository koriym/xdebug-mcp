<?php

/**
 * Test script for code coverage analysis in Docker
 *
 * Contains various code paths to test coverage:
 * - Conditional branches
 * - Loops
 * - Exception handling
 * - Early returns
 */

declare(strict_types=1);

/**
 * Validate user input with multiple conditions
 */
function validateInput(mixed $input): array
{
    $errors = [];

    if ($input === null) {
        $errors[] = 'Input is null';

        return $errors;
    }

    if (! is_array($input)) {
        $errors[] = 'Input must be an array';

        return $errors;
    }

    if (empty($input)) {
        $errors[] = 'Input array is empty';

        return $errors;
    }

    // Check required fields
    $requiredFields = ['name', 'email', 'age'];
    foreach ($requiredFields as $field) {
        if (! isset($input[$field])) {
            $errors[] = "Missing required field: $field";
        }
    }

    // Validate email format
    if (isset($input['email']) && ! filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // Validate age
    if (isset($input['age'])) {
        if (! is_int($input['age'])) {
            $errors[] = 'Age must be an integer';
        } elseif ($input['age'] < 0) {
            $errors[] = 'Age cannot be negative';
        } elseif ($input['age'] > 150) {
            $errors[] = 'Age seems unrealistic';
        }
    }

    return $errors;
}

/**
 * Calculate discount based on various rules
 */
function calculateDiscount(float $amount, string $userType, bool $isHoliday = false): float
{
    $discount = 0.0;

    // Base discount by user type
    $discount = match ($userType) {
        'premium' => 0.20,
        'gold' => 0.15,
        'silver' => 0.10,
        'regular' => 0.05,
        default => 0.0,
    };

    // Holiday bonus
    if ($isHoliday) {
        $discount += 0.05;
    }

    // Volume discount
    if ($amount >= 1000) {
        $discount += 0.10;
    } elseif ($amount >= 500) {
        $discount += 0.05;
    } elseif ($amount >= 100) {
        $discount += 0.02;
    }

    // Cap at 40%
    return min($discount, 0.40);
}

/**
 * Process data with exception handling
 */
function processData(array $data): array
{
    $results = [];

    foreach ($data as $index => $item) {
        try {
            if (! is_numeric($item)) {
                throw new InvalidArgumentException("Item at index $index is not numeric");
            }

            $value = (float) $item;

            if ($value < 0) {
                throw new RangeException("Negative value at index $index");
            }

            $results[] = [
                'original' => $value,
                'sqrt' => sqrt($value),
                'log' => $value > 0 ? log($value) : null,
            ];
        } catch (InvalidArgumentException $e) {
            $results[] = ['error' => $e->getMessage(), 'type' => 'invalid'];
        } catch (RangeException $e) {
            $results[] = ['error' => $e->getMessage(), 'type' => 'range'];
        }
    }

    return $results;
}

// Run tests
echo "=== Docker Coverage Test Script ===\n\n";

// Test validateInput
echo "1. Testing validateInput:\n";
$testCases = [
    null,
    'not an array',
    [],
    ['name' => 'Test'],
    ['name' => 'Test', 'email' => 'invalid', 'age' => 25],
    ['name' => 'Test', 'email' => 'test@example.com', 'age' => -5],
    ['name' => 'Test', 'email' => 'test@example.com', 'age' => 200],
    ['name' => 'Test', 'email' => 'test@example.com', 'age' => 25],
];

foreach ($testCases as $i => $case) {
    $errors = validateInput($case);
    $status = empty($errors) ? 'PASS' : 'FAIL';
    echo "   Case $i: $status (" . count($errors) . " errors)\n";
}

// Test calculateDiscount
echo "\n2. Testing calculateDiscount:\n";
$discountTests = [
    [50, 'regular', false],
    [100, 'silver', false],
    [500, 'gold', true],
    [1000, 'premium', true],
    [2000, 'unknown', false],
];

foreach ($discountTests as [$amount, $type, $holiday]) {
    $discount = calculateDiscount($amount, $type, $holiday);
    echo "   \$$amount, $type, holiday=" . ($holiday ? 'yes' : 'no') . ' => ' . ($discount * 100) . "% off\n";
}

// Test processData
echo "\n3. Testing processData:\n";
$dataToProcess = [1, 4, 9, 'invalid', -1, 16, 25];
$processed = processData($dataToProcess);
$successCount = count(array_filter($processed, static fn ($r) => ! isset($r['error'])));
echo '   Processed ' . count($processed) . " items, $successCount successful\n";

echo "\n=== Coverage Test Complete ===\n";
