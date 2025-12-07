<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * PHPUnit 10+ Event Subscriber for test tracing
 */
final class TraceSubscriber implements PreparedSubscriber, FinishedSubscriber
{
    public function notify(Prepared|Finished $event): void
    {
        $testName = $event->test()->name();

        if ($event instanceof Prepared) {
            $this->handleTestStart($testName);
        } else {
            $this->handleTestEnd($testName);
        }
    }

    private function handleTestStart(string $testName): void
    {
        if (TraceHelper::shouldTrace($testName)) {
            TraceHelper::startTrace($testName);
        }
    }

    private function handleTestEnd(string $testName): void
    {
        if (TraceHelper::shouldTrace($testName)) {
            TraceHelper::stopTrace($testName);
        }
    }
}
