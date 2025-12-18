<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit 10+ Extension for selective Xdebug tracing
 *
 * Enables automatic Xdebug tracing for specific tests based on environment configuration.
 * Set XDEBUG_TRACE_TESTS environment variable to a regex pattern to match test names.
 *
 * Usage in phpunit.xml:
 * <extensions>
 *     <bootstrap class="Koriym\XdebugMcp\TraceExtension"/>
 * </extensions>
 *
 * @codeCoverageIgnore PHPUnit extension runtime
 */
final class TraceExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        TraceHelper::init();

        $facade->registerSubscribers(
            new TraceSubscriber(),
        );
    }
}
