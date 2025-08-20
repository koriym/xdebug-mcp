<?php

namespace Koriym\XdebugMcp;

/**
 * PHPUnit Selective Trace Helper
 * Provides common tracing logic for PHPUnit tests
 */
class TraceHelper
{
    private static bool $traceActive = false;
    private static ?string $traceFile = null;
    private static array $targetTests = [];
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $traceEnv = getenv('TRACE_TEST');
        if (!$traceEnv) {
            self::$initialized = true;
            return;
        }

        if (!extension_loaded('xdebug')) {
            echo "WARNING: Xdebug extension not loaded, tracing disabled\n";
            self::$initialized = true;
            return;
        }

        // Parse target tests
        self::$targetTests = array_map('trim', explode(',', $traceEnv));
        
        echo "TRACE: Configured to trace tests matching: " . implode(', ', self::$targetTests) . "\n";
        self::$initialized = true;
    }

    public static function shouldTrace(string $testName): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        if (empty(self::$targetTests)) {
            return false;
        }

        foreach (self::$targetTests as $pattern) {
            if (self::matchesPattern($testName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function startTrace(string $testName): void
    {
        if (!function_exists('xdebug_start_trace')) {
            return;
        }

        $timestamp = date('Y-m-d_H-i-s-') . substr(microtime(), 2, 6);
        $safeTestName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $testName);
        self::$traceFile = "/tmp/trace_{$safeTestName}_{$timestamp}.xt";

        // Configure Xdebug tracing
        ini_set('xdebug.trace_output_dir', '/tmp');
        ini_set('xdebug.trace_format', '1');
        ini_set('xdebug.use_compression', '0');

        xdebug_start_trace(rtrim(self::$traceFile, '.xt'));
        self::$traceActive = true;

        echo "TRACE: Started tracing {$testName} -> " . self::$traceFile . "\n";
    }

    public static function stopTrace(string $testName): void
    {
        if (!self::$traceActive || !function_exists('xdebug_stop_trace')) {
            return;
        }

        xdebug_stop_trace();
        self::$traceActive = false;

        if (self::$traceFile && file_exists(self::$traceFile)) {
            $size = filesize(self::$traceFile);
            echo "TRACE: Completed tracing {$testName} -> " . self::$traceFile . " ({$size} bytes)\n";
        }

        self::$traceFile = null;
    }

    private static function matchesPattern(string $testName, string $pattern): bool
    {
        // Exact match
        if ($testName === $pattern) {
            return true;
        }

        // Simple wildcard support
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace(['*', '\\'], ['.*', '\\\\'], preg_quote($pattern, '/')) . '$/';
            return preg_match($regex, $testName) === 1;
        }

        // Contains match (for class names)
        if (strpos($testName, $pattern) !== false) {
            return true;
        }

        return false;
    }
}