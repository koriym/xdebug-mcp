<?php
/**
 * Error Simulation Pattern - エラーが起きやすいパターン
 */

function testErrorProne() {
    $data = ['valid', null, '', 'another', false, 'final'];
    $processed = [];
    $errors = [];
    $valid_count = 0;
    
    for ($i = 0; $i < count($data); $i++) {
        $item = $data[$i];               // Line 12 - Item access
        
        // Potential error: null/empty handling
        if ($item === null) {            // Line 15 - Null check
            $errors[] = "Null value at index $i";
            $processed[$i] = '[NULL]';
        } elseif ($item === '') {        // Line 18 - Empty check
            $errors[] = "Empty value at index $i";  
            $processed[$i] = '[EMPTY]';
        } elseif ($item === false) {     // Line 21 - False check
            $errors[] = "False value at index $i";
            $processed[$i] = '[FALSE]';
        } else {
            $valid_count++;              // Line 25 - Valid increment
            $processed[$i] = strtoupper($item);
        }
        
        // Potential division by zero
        if ($i > 0) {                    // Line 30 - Division protection
            $ratio = $valid_count / $i;  // Line 31 - Could be risky
            $processed[$i] .= " (ratio: " . round($ratio, 2) . ")";
        }
    }
    
    return [                             // Line 36
        'processed' => $processed,
        'errors' => $errors,
        'valid_count' => $valid_count,
        'error_rate' => count($errors) / count($data)
    ];
}

echo "⚠️ Error Simulation Test\n";
$result = testErrorProne();
print_r($result);
?>