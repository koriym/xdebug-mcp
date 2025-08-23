<?php
/**
 * ステップ実行テスト用スクリプト
 */

echo "🔧 Starting step execution test...\n";

function calculateSum(int $a, int $b): int
{
    echo "📊 Calculating sum of $a + $b\n";
    $result = $a + $b;
    echo "📊 Result: $result\n";
    return $result;
}

function processData(array $numbers): array
{
    echo "📋 Processing data array with " . count($numbers) . " items\n";
    $results = [];
    
    foreach ($numbers as $index => $number) {
        echo "🔄 Processing item $index: $number\n";
        $squared = $number * $number;
        $results[] = $squared;
        echo "✨ Result: $number² = $squared\n";
    }
    
    return $results;
}

// Main execution
echo "🚀 Starting main execution\n";

$num1 = 10;
$num2 = 20;
$sum = calculateSum($num1, $num2);

$testData = [1, 2, 3, 4, 5];
$processedData = processData($testData);

echo "📈 Final sum: $sum\n";
echo "📊 Processed data: " . implode(', ', $processedData) . "\n";
echo "✅ Step execution test completed\n";