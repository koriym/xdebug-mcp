<?php

function simple_function($value) {
    return $value * 2;
}

echo "Starting test...\n";
$result = simple_function(5);
echo "Result: $result\n";
echo "Test completed.\n";