<?php
// Simple test for coverage analysis
function testFunction($value) {
    if ($value > 0) {
        echo "Positive: $value\n";
        return $value * 2;
    } else if ($value < 0) {
        echo "Negative: $value\n";
        return $value * -1;
    } else {
        echo "Zero: $value\n";
        return 0;
    }
}

// Test calls
testFunction(5);
testFunction(-3);
testFunction(0);