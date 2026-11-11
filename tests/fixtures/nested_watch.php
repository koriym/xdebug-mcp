<?php

declare(strict_types=1);

$a = ['x' => ['y' => 0]];
for ($i = 0; $i < 2; $i++) {
    $a['x']['y'] = $i + 1;
}
