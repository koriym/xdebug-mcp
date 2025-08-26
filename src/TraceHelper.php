<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function date;
use function explode;
use function extension_loaded;
use function file_exists;
use function filesize;
use function fnmatch;
use function function_exists;
use function fwrite;
use function getenv;
use function glob;
use function implode;
use function ini_get;
use function ini_set;
use function preg_replace;
use function rtrim;
use function strpos;
use function uniqid;

use const STDERR;

/**
 * PHPUnit Selective Trace Helper
 * Provides common tracing logic for PHPUnit tests
 */
class TraceHelper
{
    private static bool $traceActive = false;
    private static string|null $traceFile = null;
    private static array $targetTests = [];
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $traceEnv = getenv('TRACE_TEST');
        if (! $traceEnv) {
            self::$initialized = true;

            return;
        }

        if (! extension_loaded('xdebug')) {
            fwrite(STDERR, "WARNING: Xdebug extension not loaded, tracing disabled\n");
            self::$initialized = true;

            return;
        }

        // Parse target tests and filter out empty patterns
        self::$targetTests = array_values(array_filter(array_map('trim', explode(',', $traceEnv)), 'strlen'));

        fwrite(STDERR, 'TRACE: Configured to trace tests matching: ' . implode(', ', self::$targetTests) . "\n");
        self::$initialized = true;
    }

    public static function shouldTrace(string $testName): bool
    {
        if (! self::$initialized) {
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
        if (! function_exists('xdebug_start_trace')) {
            return;
        }

        $uniqueId = uniqid(date('Ymd_His_'), true);
        $safeTestName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $testName);
        // Use Xdebug configured output directory
        $xdebugOutputDir = ini_get('xdebug.output_dir') ?: '/tmp';
        self::$traceFile = $xdebugOutputDir . "/trace_{$safeTestName}_{$uniqueId}.xt";

        // Configure Xdebug tracing (do not modify output_dir - use configured value)
        ini_set('xdebug.trace_format', '1');
        ini_set('xdebug.use_compression', '0');

        self::$traceFile = xdebug_start_trace(rtrim(self::$traceFile, '.xt'));
        self::$traceActive = true;

        fwrite(STDERR, "TRACE: Started tracing {$testName} -> " . self::$traceFile . "\n");
    }

    public static function stopTrace(string $testName): void
    {
        if (! self::$traceActive || ! function_exists('xdebug_stop_trace')) {
            return;
        }

        $traceFile = null;
        if (function_exists('xdebug_get_tracefile_name')) {
            $traceFile = xdebug_get_tracefile_name();
        } elseif (self::$traceFile) {
            // Try to find the file using glob in case xdebug changed the name
            $files = glob(self::$traceFile . '*');
            if ($files && count($files) > 0) {
                $traceFile = $files[0];
            }
        }

        xdebug_stop_trace();
        self::$traceActive = false;

        if ($traceFile && file_exists($traceFile)) {
            $size = filesize($traceFile);
            fwrite(STDERR, "TRACE: Completed tracing {$testName} -> " . $traceFile . " ({$size} bytes)\n");
        }

        self::$traceFile = null;
    }

    private static function matchesPattern(string $testName, string $pattern): bool
    {
        // Exact match
        if ($testName === $pattern) {
            return true;
        }

        // Wildcard and pattern matching using fnmatch for reliability
        if (fnmatch($pattern, $testName)) {
            return true;
        }

        // Contains match (for class names)
        return strpos($testName, $pattern) !== false;
    }
}
