<?php

function countdown($n) {
    if ($n <= 0) {
        return "Done!";
    }
    return countdown($n - 1);
}

function factorial($n) {
    if ($n <= 1) {
        return 1;
    }
    return $n * factorial($n - 1);
}

echo "Starting deep recursion test...\n";
$result1 = countdown(8);        // 9 levels deep (0 to 8)
echo "Countdown result: $result1\n";

$result2 = factorial(5);        // 6 levels deep (1 to 5) 
echo "Factorial 5! = $result2\n";

// 期待される最大深度:
// {main} (level 1)
//   countdown(8) (level 2)
//     countdown(7) (level 3)
//       ... 
//         countdown(0) (level 10)  <- MAX DEPTH should be 10
//   factorial(5) (level 2)  
//     factorial(4) (level 3)
//       ...
//         factorial(1) (level 6)
