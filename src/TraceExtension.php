<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use PHPUnit\Event\Test\AfterTestMethodCalled;
use PHPUnit\Event\Test\AfterTestMethodCalledSubscriber;
use PHPUnit\Event\Test\BeforeTestMethodCalled;
use PHPUnit\Event\Test\BeforeTestMethodCalledSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

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
                $method = $event->calledMethod();
                $testName = $method->className() . '::' . $method->methodName();

                if (TraceHelper::shouldTrace($testName)) {
                    TraceHelper::startTrace($testName);
                }
            }
        });

        $facade->registerSubscriber(new class implements AfterTestMethodCalledSubscriber {
            public function notify(AfterTestMethodCalled $event): void
            {
                $method = $event->calledMethod();
                $testName = $method->className() . '::' . $method->methodName();

                if (TraceHelper::shouldTrace($testName)) {
                    TraceHelper::stopTrace($testName);
                }
            }
        });
    }
}
