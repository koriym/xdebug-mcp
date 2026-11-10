<?php

declare(strict_types=1);

class PrivatePropertiesBase
{
    private string $name = 'base';
}

final class PrivatePropertiesHolder extends PrivatePropertiesBase
{
    /** @var array<string, int> */
    private array $items = ['k' => 1];
    protected string $prot = 'p';
    public string $name = 'child';
    public self|null $self = null;

    public function run(): int
    {
        $other = new PrivatePropertiesOther();
        $this->self = $this;

        return count($this->items) + $other->value();
    }
}

final class PrivatePropertiesOther
{
    private int $secret = 42;

    public function value(): int
    {
        return $this->secret;
    }
}

(new PrivatePropertiesHolder())->run();
