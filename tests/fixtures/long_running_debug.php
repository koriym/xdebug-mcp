<?php

declare(strict_types=1);

function slow_fibonacci($n)
{
    echo "Computing fibonacci($n)\n";
    if ($n <= 1) {
        return $n;
    }

    // Add delay to make it debuggable
    usleep(500000); // 0.5 second delay

    $result = slow_fibonacci($n - 1) + slow_fibonacci($n - 2);
    echo "fibonacci($n) = $result\n";

    return $result;
}

function main()
{
    echo "Starting long running debug test\n";

    $numbers = [1, 2, 3];
    foreach ($numbers as $num) {
        echo "Processing number: $num\n";
        $result = slow_fibonacci($num + 3);
        echo "Result for $num: $result\n";
        sleep(1);
    }

    echo "Long running debug test complete\n";
}

main();
