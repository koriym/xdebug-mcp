<?php
// Simple test script for HTTP debugging
echo "Starting HTTP debug test...\n";

$numbers = [1, 2, 3, 4, 5];
$total = 0;

foreach ($numbers as $num) {
    $total += $num;  // ← This line can be used for breakpoint testing
    echo "Added $num, total is now $total\n";
}

$result = $total * 2;
echo "Final result: $result\n";
echo "HTTP debug test complete.\n";