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
 * The body runs inside a closure: an auto_prepend file shares the target's
 * global scope, so plain locals would show up in every variable dump (and
 * could overwrite a target variable of the same name).
 *
 * Override via env:
 * - XDEBUG_MCP_DISABLE_VENDOR_FILTER=1  disable filtering entirely
 * - XDEBUG_MCP_INCLUDE_VENDOR=pkg/*     keep selected packages
 */

if (! extension_loaded('xdebug')) {
    return;
}

// Only the filter setup needs locals; xdebug_start_trace() stays at global
// scope so the closure's own frame never lands in the trace.
(static function (): void {
    if (! function_exists('xdebug_set_filter') || getenv('XDEBUG_MCP_DISABLE_VENDOR_FILTER') === '1') {
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

xdebug_start_trace();
