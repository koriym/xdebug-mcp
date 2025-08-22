#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Wrapper script to maintain compatibility with existing test-all.sh usage
 * Delegates to the refactored test-working-tools.php
 */

// Forward all arguments to the new refactored script
$args = implode(' ', array_slice($argv, 1));
$command = __DIR__ . '/test-working-tools.php ' . $args;

// Execute and preserve exit code
passthru($command, $exitCode);
exit($exitCode);