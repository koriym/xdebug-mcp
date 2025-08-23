<?php
// Simple step test
echo "Step 1: Starting\n";

function testFunction($x) {
    echo "Step 2: In function with $x\n";
    $result = $x * 2;
    echo "Step 3: Result is $result\n";
    return $result;
}

$value = 5;
echo "Step 4: Calling function\n";
$output = testFunction($value);
echo "Step 5: Final result: $output\n";