<?php

declare(strict_types=1);

/**
 * Division by Zero Error Simulation
 */
function calculateAverage($numbers) {
    $sum = array_sum($numbers);
    $count = count($numbers);
    
    echo "Calculating average...\n";
    echo "Sum: $sum\n";
    echo "Count: $count\n";
    
    // Intentional division by zero when $numbers is empty
    $average = $sum / $count;  // This will cause division by zero error
    
    return $average;
}

function main() {
    echo "Testing division by zero scenario...\n";
    
    // First test with valid data
    $validData = [10, 20, 30];
    echo "Valid data test:\n";
    $result1 = calculateAverage($validData);
    echo "Result: $result1\n\n";
    
    // Second test with empty array - will cause division by zero
    $emptyData = [];
    echo "Empty data test (will cause error):\n";
    $result2 = calculateAverage($emptyData);
    echo "Result: $result2\n";
}

main();