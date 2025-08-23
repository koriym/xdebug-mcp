<?php

declare(strict_types=1);

/**
 * Debug session script that waits for Xdebug connection
 * This script will run indefinitely to maintain a debugging session
 */

echo "🔧 Starting Xdebug debugging session...\n";
echo "🔌 Debug session should be available on port 9004\n";
echo "📝 In another terminal, run: php tests/claude/run_session_test.php\n\n";

$counter = 0;

while (true) {
    $counter++;
    $testString = "Hello Xdebug Session $counter";
    $numbers = [1, 2, 3, $counter];
    $result = array_sum($numbers);

    echo "Iteration $counter: $testString (sum: $result)\n";

    // Sleep for 2 seconds to make debugging easier
    sleep(2);

    // Break after 100 iterations to prevent infinite execution
    if ($counter >= 100) {
        break;
    }
}

echo "\n✅ Debug session completed\n";
