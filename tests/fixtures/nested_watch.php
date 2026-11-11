<?php

declare(strict_types=1);

final class NestedHolder
{
    /** @var array<string, array<string, int>> */
    public array $v = ['x' => ['y' => 0]];

    /** @return array<string, array<string, int>> */
    public function get(): array
    {
        return $this->v;
    }
}

$o = new NestedHolder();
$a = ['x' => ['y' => 0]];
for ($i = 0; $i < 2; $i++) {
    $a['x']['y'] = $i + 1;
    $o->v['x']['y'] = $i + 1;
}
