<?php
// Simple test script for session debugging
echo "Starting session test...\n";

$numbers = [1, 2, 3, 4, 5];
$total = 0;

foreach ($numbers as $num) {
    $total += $num;  // ← ブレークポイントをここに設定
    echo "Added $num, total is now $total\n";
}

echo "Final total: $total\n";
echo "Session test complete.\n";