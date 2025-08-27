<?php

function calculateSum($numbers) {
    $sum = 0;                           // Line 4 - First breakpoint
    foreach ($numbers as $num) {
        $sum += $num;                   // Line 6
    }
    return $sum;                        // Line 8 - Second breakpoint
}

function processData() {
    $data = [1, 2, 3, 4, 5];          // Line 12 - Third breakpoint
    $result = calculateSum($data);
    
    $multiplier = 2;                   // Line 15 - Fourth breakpoint
    $final = $result * $multiplier;
    
    return $final;                     // Line 18 - Fifth breakpoint
}

function main() {
    echo "Starting calculation...\n";
    $output = processData();           // Line 23 - Sixth breakpoint
    echo "Final result: $output\n";
    return $output;
}

// Execute main function
main();                               // Line 28 - Seventh breakpoint

?>