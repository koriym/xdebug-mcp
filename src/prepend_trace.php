<?php

declare(strict_types=1);

require_once __DIR__ . '/Utilities/PathNormalizer.php';
require_once __DIR__ . '/Utilities/VendorFilter.php';

use Koriym\XdebugMcp\Utilities\VendorFilter;

/**
 * Forward-trace auto-prepend script.
 *
 * Prepended via -dauto_prepend_file for the trace tooling: it excludes
 * vendor/ noise (sharing the exact filter logic used for coverage/profile,
 * see prepend_filter.php) and then starts tracing from the first user line
 * so the resulting .xt file reflects application code only.
 *
 * Override via env:
 * - XDEBUG_MCP_DISABLE_VENDOR_FILTER=1  disable filtering entirely
 * - XDEBUG_MCP_INCLUDE_VENDOR=pkg/*     keep selected packages
 */

if (! extension_loaded('xdebug')) {
    return;
}

if (function_exists('xdebug_set_filter') && getenv('XDEBUG_MCP_DISABLE_VENDOR_FILTER') !== '1') {
    $vendorPath = VendorFilter::locateVendorDir();
    if ($vendorPath !== null) {
        $excludePaths = VendorFilter::excludePaths($vendorPath, VendorFilter::includeVendorFromEnv());
        if ($excludePaths !== []) {
            xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_PATH_EXCLUDE, $excludePaths);
            xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, $excludePaths);
        }
    }
}

xdebug_start_trace();
