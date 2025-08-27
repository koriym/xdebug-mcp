<?php

declare(strict_types=1);

/**
 * Loop Counter Pattern - ループカウンタの進行テスト
 */
function testLoopCounter()
{
    $counter = 0;                        // Line 8
    $items = ['apple', 'banana', 'cherry'];
    $results = [];

    for ($i = 0; $i < count($items); $i++) {
        $counter++;                      // Line 13 - Counter increment
        $item = $items[$i];
        $length = strlen($item);

        $results[] = [                   // Line 17 - Array growth
            'index' => $i,
            'item' => $item,
            'length' => $length,
            'counter' => $counter,
        ];

        if ($length > 5) {               // Line 24 - Condition check
            $results[count($results) - 1]['is_long'] = true;
        }
    }

    return $results;                     // Line 29 - Final result
}

echo "🔄 Loop Counter Test\n";
$result = testLoopCounter();
print_r($result);
