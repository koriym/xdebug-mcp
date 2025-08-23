<?php
/**
 * Step execution demonstration
 */
echo "🚀 Step demo starting\n";

function calculate($a, $b) {
    echo "📊 Calculating $a + $b\n";
    $result = $a + $b;
    echo "📊 Result: $result\n";
    return $result;
}

$x = 10;
$y = 20;
echo "📝 Variables set: x=$x, y=$y\n";

$sum = calculate($x, $y);
echo "✅ Final result: $sum\n";