<?php
function calculateSum($a, $b) {
    $result = $a + $b;  // Line 3 - Basic breakpoint test
    return $result;
}

function processArray($numbers) {
    $total = 0;
    foreach ($numbers as $num) {
        if ($num > 5) {  // Line 10 - Conditional breakpoint test
            $total += $num;
        }
    }
    return $total;  // Line 14 - Another breakpoint location
}

echo "Starting breakpoint tests...\n";
$sum = calculateSum(10, 20);
echo "Sum: $sum\n";

$numbers = [1, 3, 7, 9, 2, 8];
$result = processArray($numbers);
echo "Processed result: $result\n";

echo "Breakpoint test complete\n";