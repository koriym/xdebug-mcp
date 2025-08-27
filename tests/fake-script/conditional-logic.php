<?php
/**
 * Conditional Logic Pattern - 条件分岐とフラグの変化
 */

function testConditionalLogic() {
    $data = [1, -2, 3, 0, -5, 7];
    $flags = [                           // Line 8
        'has_negative' => false,
        'has_positive' => false,
        'has_zero' => false
    ];
    
    $results = [];
    $sum = 0;
    
    foreach ($data as $index => $value) {
        $sum += $value;                  // Line 18 - Running sum
        
        if ($value > 0) {
            $flags['has_positive'] = true; // Line 21 - Flag setting
            $results[] = ['positive', $value];
        } elseif ($value < 0) {
            $flags['has_negative'] = true; // Line 24 - Flag setting
            $results[] = ['negative', $value];
        } else {
            $flags['has_zero'] = true;   // Line 27 - Flag setting
            $results[] = ['zero', $value];
        }
        
        // Complex condition
        if ($sum > 0 && $flags['has_negative']) { // Line 32 - Complex condition
            $results[count($results)-1]['balanced'] = true;
        }
    }
    
    return [                             // Line 37
        'sum' => $sum,
        'flags' => $flags,
        'results' => $results
    ];
}

echo "🔀 Conditional Logic Test\n";
$result = testConditionalLogic();
print_r($result);
?>