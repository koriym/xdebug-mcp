<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase
{
    public function testSimple(): void
    {
        $a = 1;
        $b = 2;
        $this->assertEquals(3, $a + $b);
    }
}
