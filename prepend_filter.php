<?php

declare(strict_types=1);

/**
 * Xdebug Vendor Filter - Excludes vendor dependencies from traces
 *
 * Usage:
 * - Default: Excludes entire vendor/ directory
 * - --include-vendor=bear/resource,ray/di (specific packages)
 * - --include-vendor=bear/star,ray/star (pattern matching)
 * - --include-vendor=star/star (include all vendor)
 */

if (!extension_loaded('xdebug')) {
    return;
}

require_once __DIR__ . '/src/Utilities/PathNormalizer.php';

use Koriym\XdebugMcp\Utilities\PathNormalizer;

$normalisePath = static function (string $path): string {
    return PathNormalizer::normalise($path);
};

// Parse CLI arguments for vendor filtering options
$options = getopt('', ['include-vendor::']); // :: = optional value
$includeVendor = $options['include-vendor'] ?? null;

// Find vendor directory
$vendorPath = null;
foreach ([__DIR__ . '/../../../vendor', __DIR__ . '/vendor'] as $path) {
    if (is_dir($path)) {
        $vendorPath = $normalisePath($path);
        break;
    }
}

// Apply vendor filtering if vendor exists
if ($vendorPath) {
    $excludePaths = [$vendorPath];  // Default: exclude entire vendor

    if ($includeVendor) {
        // Pattern-based selective filtering
        $patterns = array_map('trim', explode(',', $includeVendor));
        $excludePaths = [$vendorPath . '/autoload.php', $vendorPath . '/composer'];

        foreach (glob($vendorPath . '/*/*', GLOB_ONLYDIR) as $packageDir) {
            $packageName = substr($packageDir, strlen($vendorPath) + 1);
            $matches = false;
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $packageName)) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) {
                $excludePaths[] = $normalisePath($packageDir);
            }
        }
    }

    xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_PATH_EXCLUDE, $excludePaths);
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, $excludePaths);
}

xdebug_start_trace();
