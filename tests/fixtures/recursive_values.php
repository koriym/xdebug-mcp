<?php

declare(strict_types=1);

final class ClassProperty
{
    public string $__class = 'shadow';
}

class ShadowBase
{
    private string $name = 'base';
}

final class Shadow extends ShadowBase
{
    private string $name = 'child';
}

$recursive = ['x' => 1];
$recursive['self'] = &$recursive;
$large = range(1, 100000);
$cut = str_repeat('あ', 700);
$empty = new stdClass();
$named = new ClassProperty();
$floats = [INF, -INF, NAN, 1.5];
$names = ['日本' => 1];
$日本 = 'var';
$invalid = "\xff\xfeabc";
$names['*Base*name'] = new stdClass();
$names['*Base*name']->{'*Base*name'} = 2;
$shadow = new Shadow();
${"v\xff"} = 1;
echo count($large), "\n";
