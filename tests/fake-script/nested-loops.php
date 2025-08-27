<?php
/**
 * Nested Loops Pattern - ネストしたループの複雑な処理
 */

function testNestedLoops() {
    $matrix = [                          // Line 8
        [1, 2, 3],
        [4, 5, 6],
        [7, 8, 9]
    ];
    
    $totals = [];
    $grand_total = 0;
    
    for ($row = 0; $row < count($matrix); $row++) {
        $row_total = 0;                  // Line 17 - Row initialization
        
        for ($col = 0; $col < count($matrix[$row]); $col++) {
            $value = $matrix[$row][$col];
            $row_total += $value;        // Line 21 - Inner accumulation
            $grand_total += $value;      // Line 22 - Outer accumulation
            
            // Find patterns
            if ($value % 2 == 0) {       // Line 25 - Pattern detection
                $totals['even'] = ($totals['even'] ?? 0) + 1;
            } else {
                $totals['odd'] = ($totals['odd'] ?? 0) + 1;
            }
        }
        
        $totals['rows'][$row] = $row_total; // Line 32 - Row completion
        
        if ($row_total > 15) {           // Line 34 - Row condition
            $totals['high_rows'][] = $row;
        }
    }
    
    $totals['grand_total'] = $grand_total; // Line 39
    
    return $totals;                      // Line 41
}

echo "🔁 Nested Loops Test\n";
$result = testNestedLoops();
print_r($result);
?>