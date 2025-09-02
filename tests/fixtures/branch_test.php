<?php

declare(strict_types=1);

function testBranches($input)
{
    if ($input > 10) {
        echo "Large number: $input\n";
        if ($input > 50) {
            echo "Very large: $input\n";

            return 'very_large';
        }

        return 'large';
    }

    if ($input > 0) {
        echo "Small positive: $input\n";

        return 'small_positive';
    }

    echo "Zero or negative: $input\n";

    return 'non_positive';
}

// Test different paths
echo "=== Branch Coverage Test ===\n";

$testValues = [5, 15, 75, -3];
foreach ($testValues as $value) {
    $result = testBranches($value);
    echo "Input: $value, Result: $result\n";
}

echo "=== Test Complete ===\n";
