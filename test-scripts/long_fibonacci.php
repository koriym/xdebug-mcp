<?php
/**
 * Longer fibonacci test for step debugging
 */

function fibonacci($n) {
    echo "🔢 fibonacci($n) called\n";
    sleep(1); // Add delay to allow debugging
    
    if ($n <= 1) {
        echo "🔄 Base case: returning $n\n";
        return $n;
    }
    
    echo "🔄 Recursive case: calculating fibonacci(" . ($n-1) . ") + fibonacci(" . ($n-2) . ")\n";
    $result = fibonacci($n - 1) + fibonacci($n - 2);
    echo "📊 fibonacci($n) = $result\n";
    return $result;
}

echo "🚀 Starting long fibonacci test...\n";
sleep(2); // Initial delay for debugger setup

echo "🔢 Computing fibonacci(3)...\n";
$result = fibonacci(3);

echo "✅ Final result: fibonacci(3) = $result\n";
echo "⏳ Finishing...\n";
sleep(2);
echo "🏁 Complete!\n";