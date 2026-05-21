<?php

declare(strict_types=1);

require_once __DIR__ . '/Utilities/PathNormalizer.php';
require_once __DIR__ . '/Utilities/VendorFilter.php';

use Koriym\XdebugMcp\Utilities\VendorFilter;

// Automatically exclude vendor directory from all Xdebug tracing
// This file is prepended to PHP execution via -dauto_prepend_file
// to ensure vendor code is filtered out from the very beginning,
// including Composer autoloader execution.

if (extension_loaded('xdebug')) {
    // Check both possible vendor locations
    $vendorPath = is_dir(dirname(__DIR__) . '/vendor')
        ? dirname(__DIR__) . '/vendor/'           // Local development: src/../vendor/
        : dirname(__DIR__, 3) . '/vendor/';       // Composer install: vendor/koriym/xdebug-mcp/src -> project-root/vendor/

    // Allow disabling via env
    if (getenv('XDEBUG_MCP_DISABLE_VENDOR_FILTER') === '1') {
        return;
    }

    if (function_exists('xdebug_set_filter')) {
        $includeVendor = getenv('XDEBUG_MCP_INCLUDE_VENDOR');
        $excludePaths = VendorFilter::excludePaths($vendorPath, $includeVendor === false ? null : $includeVendor);
        if ($excludePaths !== []) {
            xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_PATH_EXCLUDE, $excludePaths);
            xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, $excludePaths);
        }
    }
}
