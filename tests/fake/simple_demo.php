<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== Xdebug MCP Server Simple Demo ===\n\n";

// Simple PHP calculation
$numbers = [1, 2, 3, 4, 5];
$sum = 0;

echo "Processing numbers: " . implode(', ', $numbers) . "\n";

foreach ($numbers as $number) {
    $sum += $number;
    echo "Added $number, current sum: $sum\n";
}

echo "\nFinal result: $sum\n";
echo "Average: " . ($sum / count($numbers)) . "\n\n";

echo "=== Demo completed successfully ===\n";