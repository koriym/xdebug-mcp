<?php

declare(strict_types=1);

function fibonacci($n)
{
    echo "Calculating fibonacci($n)
";

    if ($n <= 1) {
        return $n;
    }

    $result = fibonacci($n - 1) + fibonacci($n - 2);
    echo "fibonacci($n) = $result
";

    return $result;
}

echo 'Starting debug demo
';
$result = fibonacci(5);
echo "Final result: $result
";
