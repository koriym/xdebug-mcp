<?php

function fibonacci($n) {
    if ($n <= 1) {
        return $n;
    }
    return fibonacci($n - 1) + fibonacci($n - 2);
}

function processFiles() {
    $files = glob('*.php');
    foreach ($files as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            // Simulate some processing
            strlen($content);
        }
    }
}

function databaseSimulation() {
    // Simulate database operations
    $data = array_fill(0, 100, 'data');
    array_map('strlen', $data);
    return count($data);
}

echo "Starting complex test...\n";

// Recursive function test
$fibResult = fibonacci(8);
echo "Fibonacci(8): $fibResult\n";

// File I/O test
processFiles();
echo "Files processed\n";

// Database simulation
$dbResult = databaseSimulation();
echo "Database operations: $dbResult\n";

echo "Complex test completed.\n";