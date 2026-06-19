<?php

declare(strict_types=1);

require_once __DIR__ . '/Utilities/PathNormalizer.php';
require_once __DIR__ . '/Utilities/VendorFilter.php';

use Koriym\XdebugMcp\Utilities\VendorFilter;

// Automatically exclude the vendor directory from Xdebug tracing and
// coverage. This file is prepended to PHP execution via -dauto_prepend_file
// so vendor code is filtered out from the very beginning, including the
// Composer autoloader.
//
// Override via env:
// - XDEBUG_MCP_DISABLE_VENDOR_FILTER=1  disable filtering entirely
// - XDEBUG_MCP_INCLUDE_VENDOR=pkg/*     keep selected packages

if (! extension_loaded('xdebug') || ! function_exists('xdebug_set_filter')) {
    return;
}

if (getenv('XDEBUG_MCP_DISABLE_VENDOR_FILTER') === '1') {
    return;
}

$vendorPath = VendorFilter::locateVendorDir();
if ($vendorPath === null) {
    return;
}

$excludePaths = VendorFilter::excludePaths($vendorPath, VendorFilter::includeVendorFromEnv());
if ($excludePaths !== []) {
    xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_PATH_EXCLUDE, $excludePaths);
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, $excludePaths);
}
