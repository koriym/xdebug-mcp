<?php

declare(strict_types=1);

$recursive = ['x' => 1];
$recursive['self'] = &$recursive;
$large = range(1, 100000);
$cut = str_repeat('あ', 700);
echo count($large), "
";
