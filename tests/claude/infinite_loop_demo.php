<?php

declare(strict_types=1);

/**
 * Demo script that runs continuously for debugging
 * This keeps the Xdebug session active
 */

echo "🔄 Starting Infinite Loop Demo for Debugging\n";

class LoopDemo
{
    public function process()
    {
        echo "Processing started...\n"; // Line 12 - Breakpoint here

        $counter = 0;
        while (true) {
            $counter++;
            echo "Loop iteration: $counter\n"; // Line 17 - Breakpoint here

            if ($counter % 10 === 0) {
                echo "Checkpoint at $counter iterations\n"; // Line 20 - Breakpoint here
            }

            sleep(2); // Pause for 2 seconds each iteration

            // Exit condition for safety
            if ($counter >= 100) {
                echo "Reached maximum iterations, exiting\n";
                break;
            }
        }

        echo "Processing completed.\n";
    }
}

$demo = new LoopDemo();
$demo->process();

echo "Demo finished.\n";
