<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use function extension_loaded;
use function function_exists;
use function getenv;
use function ini_get;
use function preg_match;
use function preg_replace;
use function str_contains;
use function xdebug_start_trace;
use function xdebug_stop_trace;

/**
 * Helper class for Xdebug trace management in PHPUnit tests
 *
 * Enables selective tracing of specific tests based on environment configuration.
 * Set XDEBUG_TRACE_TESTS environment variable to a regex pattern to match test names.
 */
final class TraceHelper
{
    private static bool $initialised = false;
    private static string $tracePattern = '';
    private static bool $xdebugAvailable = false;
    private static string $outputDir = '/tmp';

    /**
     * Initialise the trace helper
     */
    public static function init(): void
    {
        if (self::$initialised) {
            return;
        }

        self::$xdebugAvailable = extension_loaded('xdebug')
            && function_exists('xdebug_start_trace')
            && str_contains((string) ini_get('xdebug.mode'), 'trace');

        self::$tracePattern = (string) getenv('XDEBUG_TRACE_TESTS');
        self::$outputDir = ini_get('xdebug.output_dir') ?: '/tmp';
        self::$initialised = true;
    }

    /**
     * Check if a test should be traced
     */
    public static function shouldTrace(string $testName): bool
    {
        if (! self::$initialised) {
            self::init();
        }

        if (! self::$xdebugAvailable || self::$tracePattern === '') {
            return false;
        }

        return (bool) preg_match(self::$tracePattern, $testName);
    }

    /**
     * Start tracing for a test
     */
    public static function startTrace(string $testName): void
    {
        if (! self::$initialised) {
            self::init();
        }

        if (! self::$xdebugAvailable) {
            return;
        }

        $safeTestName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $testName) ?? $testName;
        $traceFile = self::$outputDir . '/trace_' . $safeTestName;

        xdebug_start_trace($traceFile);
    }

    /**
     * Stop tracing for a test
     *
     * @param string $testName Test name (unused, kept for API consistency)
     */
    public static function stopTrace(string $testName): void
    {
        unset($testName); // Unused, kept for API consistency

        if (! self::$xdebugAvailable) {
            return;
        }

        xdebug_stop_trace();
    }

    /**
     * Check if Xdebug tracing is available
     */
    public static function isAvailable(): bool
    {
        if (! self::$initialised) {
            self::init();
        }

        return self::$xdebugAvailable;
    }

    /**
     * Reset state (for testing)
     */
    public static function reset(): void
    {
        self::$initialised = false;
        self::$tracePattern = '';
        self::$xdebugAvailable = false;
        self::$outputDir = '/tmp';
    }
}
