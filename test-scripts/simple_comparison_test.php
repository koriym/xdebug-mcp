<?php

function add($a, $b) {
    return $a + $b;
}

function multiply($a, $b) {
    return $a * $b;
}

function calculate($x, $y) {
    $sum = add($x, $y);        // call 1: add()
    $product = multiply($x, $y); // call 2: multiply()
    return add($sum, $product);  // call 3: add()
}

echo "Starting calculation...\n";
$result = calculate(3, 4);     // call 4: calculate() -> calls add(), multiply(), add()
echo "Result: $result\n";      // Should be: 3+4 + 3*4 + (3+4) = 7 + 12 + 7 = 26

// 期待される関数呼び出し数:
// 1. calculate(3, 4)
// 2. add(3, 4) inside calculate
// 3. multiply(3, 4) inside calculate  
// 4. add(7, 12) inside calculate
// 合計: 4 function calls (not counting {main})