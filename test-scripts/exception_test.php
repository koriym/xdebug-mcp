<?php
/**
 * Exception handling test for step debugging
 */

function riskyCalculation($a, $b) {
    echo "🧮 Starting risky calculation: $a / $b\n";
    
    if ($b === 0) {
        throw new Exception("Division by zero!");
    }
    
    $result = $a / $b;
    echo "✅ Result: $result\n";
    return $result;
}

function testExceptions() {
    echo "🚀 Testing exception handling...\n";
    
    try {
        echo "📝 Test 1: Normal case\n";
        $result1 = riskyCalculation(10, 2);
        
        echo "📝 Test 2: Exception case\n"; 
        $result2 = riskyCalculation(10, 0);
        
    } catch (Exception $e) {
        echo "🚨 Caught exception: " . $e->getMessage() . "\n";
        echo "📍 At line: " . $e->getLine() . "\n";
    }
    
    echo "🏁 Exception test complete\n";
}

testExceptions();