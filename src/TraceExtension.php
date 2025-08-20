<?php

namespace Koriym\XdebugMcp;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\Event\Test\BeforeTestMethodCalled;
use PHPUnit\Event\Test\AfterTestMethodCalled;
use PHPUnit\Event\Test\BeforeTestMethodCalledSubscriber;
use PHPUnit\Event\Test\AfterTestMethodCalledSubscriber;
use Koriym\XdebugMcp\TraceHelper;

/**
 * PHPUnit 10+ Extension for selective tracing
 */
class TraceExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        TraceHelper::init();
        
        $facade->registerSubscriber(new class implements BeforeTestMethodCalledSubscriber {
            public function notify(BeforeTestMethodCalled $event): void
            {
                $test = $event->testMethod();
                $testName = $test->className() . '::' . $test->methodName();
                
                if (TraceHelper::shouldTrace($testName)) {
                    TraceHelper::startTrace($testName);
                }
            }
        });

        $facade->registerSubscriber(new class implements AfterTestMethodCalledSubscriber {
            public function notify(AfterTestMethodCalled $event): void
            {
                $test = $event->testMethod();
                $testName = $test->className() . '::' . $test->methodName();
                
                if (TraceHelper::shouldTrace($testName)) {
                    TraceHelper::stopTrace($testName);
                }
            }
        });
    }
}