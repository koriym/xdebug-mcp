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
// The body runs inside a closure because this file shares the target's global
// scope: plain locals would show up in every variable dump and could overwrite
// a target variable of the same name. Same idiom as prepend_trace.php.
//
// Override via env:
// - XDEBUG_MCP_DISABLE_VENDOR_FILTER=1  disable filtering entirely
// - XDEBUG_MCP_INCLUDE_VENDOR=pkg/*     keep selected packages

(static function (): void {
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
    if ($excludePaths === []) {
        return;
    }

    xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_PATH_EXCLUDE, $excludePaths);
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, $excludePaths);
})();
