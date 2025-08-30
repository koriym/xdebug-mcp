<?php

declare(strict_types=1);

/**
 * Intentionally Slow Algorithm for Performance Testing
 */
function slowBubbleSort($array) {
    $n = count($array);
    
    // Inefficient bubble sort with extra nested loops for demonstration
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n - 1; $j++) {
            // Add unnecessary inner loop to make it even slower
            for ($k = 0; $k < 10; $k++) {
                // Simulate some "work"
                $temp = $array[$j] * 2;
            }
            
            if ($array[$j] > $array[$j + 1]) {
                // Swap
                $temp = $array[$j];
                $array[$j] = $array[$j + 1];
                $array[$j + 1] = $temp;
            }
        }
    }
    
    return $array;
}

function expensiveStringProcessing($text) {
    $result = '';
    
    // Inefficient string processing
    for ($i = 0; $i < strlen($text); $i++) {
        for ($j = 0; $j < 100; $j++) {
            // Unnecessary repeated operations
            $char = substr($text, $i, 1);
            $result .= strtoupper($char);
            $result = rtrim($result, strtoupper($char));
        }
        $result .= strtolower(substr($text, $i, 1));
    }
    
    return $result;
}

function main() {
    echo "🐌 Performance bottleneck simulation\n";
    
    // Create test data
    $numbers = [64, 34, 25, 12, 22, 11, 90, 5, 77, 30];
    $text = "This is a test string for performance analysis";
    
    echo "Testing slow bubble sort...\n";
    $startTime = microtime(true);
    $sorted = slowBubbleSort($numbers);
    $sortTime = microtime(true) - $startTime;
    echo "Sort time: " . round($sortTime * 1000, 2) . "ms\n";
    
    echo "Testing expensive string processing...\n";
    $startTime = microtime(true);
    $processed = expensiveStringProcessing($text);
    $stringTime = microtime(true) - $startTime;
    echo "String processing time: " . round($stringTime * 1000, 2) . "ms\n";
    
    echo "Results:\n";
    echo "Sorted: " . implode(', ', $sorted) . "\n";
    echo "Processed: " . substr($processed, 0, 50) . "...\n";
}

main();