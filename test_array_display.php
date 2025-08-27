<?php
function processWithCounters() {
    $items = ['apple', 'banana', 'cherry', 'date', 'elderberry'];  // Line 3
    $results = [];
    $counter = 0;                        // Line 5
    $total = 0;
    
    for ($i = 0; $i < count($items); $i++) {  // Line 8
        $counter++;                      // Line 9
        $item = $items[$i];
        $length = strlen($item);
        $total += $length;               // Line 12
        
        $results[] = [                   // Line 14
            'index' => $i,
            'item' => $item,
            'length' => $length,
            'counter' => $counter,
            'running_total' => $total
        ];
    }
    
    return $results;                     // Line 23
}

function processUsers() {
    $users = [                           // Line 27
        'alice' => ['age' => 25, 'city' => 'Tokyo'],
        'bob' => ['age' => 30, 'city' => 'Osaka'],
        'charlie' => ['age' => 35, 'city' => 'Kyoto']
    ];
    
    $result = [];                        // Line 33
    $processed_count = 0;                // Line 34
    
    foreach ($users as $name => $info) {
        $processed_count++;              // Line 37
        $result[$name] = [
            'age' => $info['age'],
            'city' => $info['city'],
            'process_order' => $processed_count  // Line 41
        ];
    }
    
    return $result;                      // Line 45
}

echo "🔄 Testing loop counters and progression...\n";
$counters_data = processWithCounters();  // Line 49
$users_data = processUsers();           // Line 50
echo "✅ Processing completed\n";
?>