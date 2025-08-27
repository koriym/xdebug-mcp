<?php
/**
 * Array Manipulation Pattern - 配列操作とデータ変換
 */

function testArrayManipulation() {
    $users = [                           // Line 8
        ['name' => 'Alice', 'age' => 25, 'active' => true],
        ['name' => 'Bob', 'age' => 30, 'active' => false],
        ['name' => 'Charlie', 'age' => 35, 'active' => true]
    ];
    
    $processed = [];                     // Line 14
    $stats = ['total' => 0, 'active' => 0];
    
    foreach ($users as $index => $user) {
        $stats['total']++;               // Line 18 - Stats increment
        
        if ($user['active']) {
            $stats['active']++;          // Line 21 - Conditional increment
            
            $processed[$user['name']] = [ // Line 23 - Array building
                'age' => $user['age'],
                'category' => $user['age'] < 30 ? 'young' : 'senior',
                'index' => $index
            ];
        } else {
            $processed[$user['name']] = null; // Line 29 - Null assignment
        }
    }
    
    return [                             // Line 33 - Complex return
        'processed' => $processed,
        'stats' => $stats
    ];
}

echo "📊 Array Manipulation Test\n";
$result = testArrayManipulation();
print_r($result);
?>