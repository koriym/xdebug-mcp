<?php
function test($n) {
    $result = $n * 2;     // Line 3
    return $result;       // Line 4
}
$value = test(5);         // Line 6
echo "Result: $value\n";  // Line 7
?>