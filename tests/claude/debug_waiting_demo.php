<?php

declare(strict_types=1);

/**
 * Demo script that waits for debugging commands
 * This script will pause and wait for debugger interaction
 */

echo "🚀 Starting Debug Waiting Demo\n";

class DebugDemo
{
    public function process()
    {
        echo "Processing started...\n"; // Line 12 - First breakpoint

        $data = ['a', 'b', 'c'];
        foreach ($data as $index => $value) {
            echo "Processing item $index: $value\n"; // Line 17 - Loop breakpoint
        }

        $this->calculate(10, 20);

        echo "Processing completed.\n";
    }

    private function calculate($x, $y)
    {
        echo "Calculating $x + $y\n"; // Line 26 - Method breakpoint
        $result = $x + $y;
        echo "Result: $result\n";

        return $result;
    }
}

$demo = new DebugDemo();
$demo->process();

// Keep script alive for debugging
echo "Demo completed. Script will continue running for debugging...\n";
sleep(30); // Wait 30 seconds to allow debugging
echo "Script ending.\n";
