<?php

declare(strict_types=1);

function add($a, $b)
{
    return $a + $b;
}

echo "Starting calculation...\n";
$x = 5;
$y = 10;
$sum = add($x, $y);
echo "Result: $sum\n";
echo "Done!\n";
