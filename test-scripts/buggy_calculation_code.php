<?php

function buggy_calculate($x, $y) {
    $result = 0;
    
    // Intentional bug: should be $x + $y
    $result = $x - $y; // Bug: subtracting instead of adding
    
    if ($result > 10) {
        $result = $result * 2;
    }
    
    return $result;
}

function main() {
    $a = 15;
    $b = 5;
    
    echo "Testing buggy calculation...\n";
    $sum = buggy_calculate($a, $b);
    echo "Result: $sum\n";
    
    if ($sum == 20) {
        echo "✅ Correct result!\n";
    } else {
        echo "❌ Bug detected! Expected 20, got $sum\n";
    }
}

main();