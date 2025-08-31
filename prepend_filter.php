<?php

declare(strict_types=1);

// Allow disabling via env
if (getenv('XDEBUG_MCP_DISABLE_VENDOR_FILTER') === '1' || ! extension_loaded('xdebug')) {
    return;
}

// Automatically exclude vendor directory from all Xdebug tracing
// This file is prepended to PHP execution via -dauto_prepend_file
// to ensure vendor code is filtered out from the very beginning,
// including Composer autoloader execution.

$vendorPaths = [
    __DIR__ . '/../../../vendor', // When installed via composer
    __DIR__ . '/vendor',
];
foreach ($vendorPaths as $vendorPath) {
    if (is_dir($vendorPath)) {
        $realPath = realpath($vendorPath);;
        xdebug_set_filter(XDEBUG_FILTER_TRACING, XDEBUG_PATH_EXCLUDE, [$realPath]);
        xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_EXCLUDE, [$realPath]);
        xdebug_start_trace();
        break;
    }
}
