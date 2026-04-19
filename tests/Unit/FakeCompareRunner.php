<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\CompareRunner;
use RuntimeException;

/**
 * Testable subclass that overrides executeXstep to avoid external process calls
 */
class FakeCompareRunner extends CompareRunner
{
    /** @var array<string, array<string, mixed>> */
    private array $fakeResults = [];

    /** @param array<string, array<string, mixed>> $fakeResults Map of command => xstep result */
    public function setFakeResults(array $fakeResults): void
    {
        $this->fakeResults = $fakeResults;
    }

    /** @return array<string, mixed> */
    protected function executeXstep(string $command): array
    {
        if (! isset($this->fakeResults[$command])) {
            throw new RuntimeException("xstep returned no output for command: {$command}");
        }

        return $this->fakeResults[$command];
    }
}
