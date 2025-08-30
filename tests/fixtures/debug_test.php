<?php

declare(strict_types=1);

function fibonacci($n)
{
    if ($n <= 1) {
        return $n;
    }

    return fibonacci($n - 1) + fibonacci($n - 2);
}

function calculate_sum($array)
{
    $sum = 0;
    foreach ($array as $value) {
        $sum += $value;
    }

    return $sum;
}

function main()
{
    $numbers = [1, 2, 3, 4, 5];
    $sum = calculate_sum($numbers);

    echo "Sum: $sum\n";

    $fib_result = fibonacci(6);
    echo "Fibonacci(6): $fib_result\n";

    $user_data = [
        'name' => 'John',
        'age' => 30,
        'city' => 'Tokyo',
    ];

    echo 'User: ' . json_encode($user_data) . "\n";
}

main();
