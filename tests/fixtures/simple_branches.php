<?php

declare(strict_types=1);

function processValue($value)
{
    if ($value > 0) {
        echo "Positive: $value\n";

        return 'positive';
    }

    echo "Non-positive: $value\n";

    return 'non_positive';
}

// Test one branch only
$result1 = processValue(5);
echo "Result: $result1\n";

// Note: We intentionally don't test the negative case to show uncovered branch in red
