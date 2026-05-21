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
require_once __DIR__ . '/src/Utilities/VendorFilter.php';

use Koriym\XdebugMcp\Utilities\PathNormalizer;
use Koriym\XdebugMcp\Utilities\VendorFilter;

$normalisePath = static function (string $path): string {
    return PathNormalizer::normalise($path);
};

// Parse CLI/env arguments for vendor filtering options.
$options = getopt('', ['include-vendor::']); // :: = optional value
$includeVendor = getenv('XDEBUG_MCP_INCLUDE_VENDOR');
if ($includeVendor === false) {
    $includeVendor = getenv('COVERAGE_INCLUDE_VENDOR');
}
if ($includeVendor === false) {
    $cliIncludeVendor = is_array($options) ? ($options['include-vendor'] ?? null) : null;
    $includeVendor = is_string($cliIncludeVendor) ? $cliIncludeVendor : null;
}

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
    $excludePaths = VendorFilter::excludePaths($vendorPath, $includeVendor);
    if ($excludePaths !== []) {
        xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_PATH_EXCLUDE, $excludePaths);
        xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, $excludePaths);
    }
}

xdebug_start_trace();
