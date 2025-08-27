<?php

declare(strict_types=1);

/**
 * Object State Pattern - オブジェクト状態の変化
 */

class Counter
{
    private int $count = 0;
    private array $history = [];

    public function increment(string $reason = '')
    {
        $this->count++;                  // Line 12 - State change
        $this->history[] = [
            'count' => $this->count,
            'reason' => $reason,
            'timestamp' => microtime(true),
        ];

        return $this;                    // Line 19 - Method chaining
    }

    public function getState(): array
    {
        return [                         // Line 23 - State inspection
            'count' => $this->count,
            'history_length' => count($this->history),
            'last_reason' => end($this->history)['reason'] ?? null,
        ];
    }
}

function testObjectState()
{
    $counter = new Counter();            // Line 32

    $counter->increment('init')          // Line 34 - Method chaining
            ->increment('process')       // Line 35
            ->increment('finalize');     // Line 36

    $state1 = $counter->getState();     // Line 38

    $counter->increment('extra');        // Line 40

    $state2 = $counter->getState();     // Line 42

    return [
        'before_extra' => $state1,
        'after_extra' => $state2,
        'counter' => $counter,
    ];
}

echo "🎯 Object State Test\n";
$result = testObjectState();
print_r($result);
