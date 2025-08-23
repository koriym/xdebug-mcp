<?php
/**
 * Loop iteration test for step debugging
 */

function testLoops() {
    echo "🔄 Testing loop iterations...\n";
    
    $numbers = [1, 2, 3, 4, 5];
    $total = 0;
    
    echo "📝 Starting foreach loop\n";
    foreach ($numbers as $index => $num) {
        echo "🔢 Processing index $index, value $num\n";
        $total += $num;
        echo "📊 Running total: $total\n";
    }
    
    echo "✅ Final total: $total\n";
    
    // Test while loop
    echo "\n🔄 Testing while loop\n";
    $counter = 0;
    while ($counter < 3) {
        echo "🔢 Counter: $counter\n";
        $counter++;
        echo "📊 After increment: $counter\n";
    }
    
    echo "🏁 Loop test complete\n";
}

testLoops();