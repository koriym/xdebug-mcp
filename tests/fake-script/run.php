<?php

declare(strict_types=1);

/**
 * Forward Trace Test Runner - Simple Version
 */

$testCases = [
    'loop-counter.php' => ['8', '13', '17', '24', '29'],
    'array-manipulation.php' => ['8', '14', '18', '21', '23', '29', '33'],
    'object-state.php' => ['12', '19', '23', '32', '34', '38', '40', '42'],
    'conditional-logic.php' => ['8', '18', '21', '24', '27', '32', '37'],
    'nested-loops.php' => ['8', '17', '21', '22', '25', '32', '34', '39', '41'],
    'error-simulation.php' => ['12', '15', '18', '21', '25', '30', '31', '36'],
];

// echo "🧪 Running All Forward Trace Tests\n\n";

foreach ($testCases as $file => $breakpoints) {
    $scriptPath = __DIR__ . '/' . $file;

    if (! file_exists($scriptPath)) {
        // echo "❌ {$file} not found\n";
        continue;
    }

    // echo "🚀 Testing {$file}\n";

    $breakpointList = array_map(static function ($line) use ($scriptPath) {
        return $scriptPath . ':' . $line;
    }, $breakpoints);

    $breakpointSpec = implode(',', $breakpointList);

    // Change to project root
    $originalDir = getcwd();
    chdir(__DIR__ . '/../../');

    $command = sprintf(
        './bin/xdebug-debug --break=%s --exit-on-break -- php %s',
        $breakpointSpec,
        $scriptPath,
    );

    system($command);

    chdir($originalDir);

    // echo "\n" . str_repeat('-', 60) . "\n\n";
}

// echo "✅ All tests completed\n";
