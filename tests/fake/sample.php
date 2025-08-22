<?php
// Sample PHP file for demo purposes
$numbers = [1, 2, 3, 4, 5];
$sum = calculate_sum($numbers);
echo "Sum: $sum\n";
$fib_result = fibonacci(6);
echo "Fibonacci(6): $fib_result\n";
$user_data = ['name' => 'John', 'age' => 30, 'city' => 'Tokyo'];
echo "User: " . json_encode($user_data) . "\n";

function calculate_sum($array) {
    $sum = 0;
    foreach ($array as $value) {
        $sum += $value;
    }
    return $sum;
}

function fibonacci($n) {
    if ($n <= 1) return $n;
    return fibonacci($n - 1) + fibonacci($n - 2);
}