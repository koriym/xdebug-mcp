#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Code coverage test script for Docker integration
 */

echo "=== Code Coverage Test Script ===\n";

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    public function divide(int $a, int $b): int
    {
        if ($b === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        return intdiv($a, $b);
    }

    public function isPrime(int $n): bool
    {
        if ($n <= 1) {
            return false;
        }

        for ($i = 2; $i * $i <= $n; $i++) {
            if ($n % $i === 0) {
                return false;
            }
        }

        return true;
    }
}

// Test the Calculator class
$calculator = new Calculator();

echo "Testing Calculator class:\n";
echo "10 + 5 = " . $calculator->add(10, 5) . "\n";
echo "10 - 5 = " . $calculator->subtract(10, 5) . "\n";
echo "10 * 5 = " . $calculator->multiply(10, 5) . "\n";
echo "10 / 5 = " . $calculator->divide(10, 5) . "\n";

echo "\nPrime number tests:\n";
for ($i = 1; $i <= 10; $i++) {
    $isPrime = $calculator->isPrime($i) ? 'prime' : 'not prime';
    echo "$i is $isPrime\n";
}

echo "\n=== Coverage Test Complete ===\n";
echo "Run with xdebug-coverage to see which lines were executed.\n";
