<?php

namespace Koriym\XdebugMcp;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use Koriym\XdebugMcp\TraceHelper;

/**
 * PHPUnit 9 Listener for selective tracing
 */
class TraceListener implements TestListener
{
    use TestListenerDefaultImplementation;

    public function __construct()
    {
        TraceHelper::init();
    }

    public function startTest(Test $test): void
    {
        if ($test instanceof TestCase) {
            $testName = get_class($test) . '::' . $test->getName();
            
            if (TraceHelper::shouldTrace($testName)) {
                TraceHelper::startTrace($testName);
            }
        }
    }

    public function endTest(Test $test, float $time): void
    {
        if ($test instanceof TestCase) {
            $testName = get_class($test) . '::' . $test->getName();
            
            if (TraceHelper::shouldTrace($testName)) {
                TraceHelper::stopTrace($testName);
            }
        }
    }
}