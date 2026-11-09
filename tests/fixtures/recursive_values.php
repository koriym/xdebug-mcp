<?php

declare(strict_types=1);

final class ClassProperty
{
    public string $__class = 'shadow';
}

$recursive = ['x' => 1];
$recursive['self'] = &$recursive;
$large = range(1, 100000);
$cut = str_repeat('あ', 700);
$empty = new stdClass();
$named = new ClassProperty();
$floats = [INF, -INF, NAN, 1.5];
echo count($large), "\n";
