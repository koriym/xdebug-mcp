<?php
/**
 * AI trace analysis test
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

$numbers = [1, 2, 3, 4];
$doubled = processArray($numbers);

$fib = fibonacci(5);
echo "Fibonacci(5) = $fib\n";
echo "Doubled: " . implode(', ', $doubled) . "\n";