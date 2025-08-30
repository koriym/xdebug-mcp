<?php

declare(strict_types=1);

function fibonacci($n)
{
    echo "Calculating fibonacci({$n})\n";
    if ($n <= 1) {
        return $n;
    }

    $a = fibonacci($n - 1);
    $b = fibonacci($n - 2);
    $result = $a + $b;

    echo "fibonacci({$n}) = {$result}\n";

    return $result;
}

$name = 'World';
echo "Hello, {$name}!\n";

$number = 5;
echo "Computing fibonacci({$number})...\n";
$fib = fibonacci($number);
echo "Result: {$fib}\n";

$name = 'Debug';
echo "Goodbye, {$name}!\n";
