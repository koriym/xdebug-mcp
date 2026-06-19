<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Utilities;

use function array_filter;
use function array_map;
use function array_values;
use function dirname;
use function explode;
use function fnmatch;
use function getenv;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function is_string;
use function rtrim;
use function str_replace;
use function strlen;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;
use const GLOB_ONLYDIR;

final class VendorFilter
{
    /** @return list<string> */
    public static function excludePaths(string $vendorPath, string|null $includeVendor): array
    {
        $vendorPath = self::normaliseDir($vendorPath);
        if ($vendorPath === '' || ! is_dir($vendorPath)) {
            return [];
        }

        $patterns = self::patterns($includeVendor);
        if ($patterns === []) {
            return [$vendorPath];
        }

        if (self::matchesAllVendor($patterns)) {
            return [];
        }

        $excludePaths = [
            $vendorPath . 'autoload.php',
            $vendorPath . 'composer' . DIRECTORY_SEPARATOR,
        ];

        $packageDirs = glob($vendorPath . '*' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if ($packageDirs === false) {
            return $excludePaths;
        }

        foreach ($packageDirs as $packageDir) {
            $packageName = str_replace(DIRECTORY_SEPARATOR, '/', substr($packageDir, strlen($vendorPath)));
            if (self::matchesPackage($packageName, $patterns)) {
                continue;
            }

            $excludePaths[] = self::normaliseDir($packageDir);
        }

        return $excludePaths;
    }

    /**
     * Locate the project's vendor directory relative to this package.
     *
     * Resolves against this class file (not the caller) so every auto-prepend
     * script shares the same lookup. Handles both local development
     * (project root) and Composer installs (vendor/koriym/xdebug-mcp).
     * Returns null when no vendor directory exists.
     */
    public static function locateVendorDir(): string|null
    {
        $candidates = [
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor',  // local dev: <root>/vendor
            dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'vendor',  // composer: vendor/koriym/xdebug-mcp/src/Utilities -> <root>/vendor
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve the include-vendor patterns from the environment.
     *
     * Honours XDEBUG_MCP_INCLUDE_VENDOR (primary) and COVERAGE_INCLUDE_VENDOR
     * (legacy) so trace, profile and coverage tooling behave identically.
     * Returns null when neither is set.
     */
    public static function includeVendorFromEnv(): string|null
    {
        foreach (['XDEBUG_MCP_INCLUDE_VENDOR', 'COVERAGE_INCLUDE_VENDOR'] as $name) {
            $value = getenv($name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @param list<string>|string|null $patterns */
    public static function matchesPackage(string $packageName, array|string|null $patterns): bool
    {
        $patterns = is_array($patterns) ? $patterns : self::patterns($patterns);
        if ($patterns === []) {
            return false;
        }

        if (self::matchesAllVendor($patterns)) {
            return true;
        }

        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $packageName)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function patterns(string|null $includeVendor): array
    {
        if ($includeVendor === null || trim($includeVendor) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $includeVendor)),
            static fn (string $pattern): bool => $pattern !== '',
        ));
    }

    /** @param list<string> $patterns */
    private static function matchesAllVendor(array $patterns): bool
    {
        return in_array('*/*', $patterns, true) || in_array('*', $patterns, true);
    }

    private static function normaliseDir(string $path): string
    {
        $normalised = PathNormalizer::normalise(rtrim($path, '/\\'));

        return $normalised === '' ? '' : $normalised . DIRECTORY_SEPARATOR;
    }
}
