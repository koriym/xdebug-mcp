<?php

/**
 * Common autoloader for bin scripts
 * Loads the Composer autoloader from various possible locations
 * Returns the best-guess project root (directory containing vendor/)
 *
 * IIFE(Immediately Invoked Function Expression) to avoid polluting global namespace
 */
(function (){
    // Validate xdebug tool CLI format using closure for clean separation
    $validate = function() {
        if (PHP_SAPI !== 'cli' || !isset($GLOBALS['argv']) || count($GLOBALS['argv']) === 0) {
            return; // Skip validation for non-CLI contexts
        }

        $scriptName = basename($GLOBALS['argv'][0]);

        // Handle xdebug-debug separately (different format)
        if ($scriptName === 'xdebug-debug') {
            if (!isset($GLOBALS['argv'][1])) {
                fwrite(STDERR, "❌ Error: Script file is required\n");
                fwrite(STDERR, "Usage: {$scriptName} <script.php>\n");
                exit(1);
            }
            return; // xdebug-debug uses different format, skip other validation
        }

        // Only validate for specific xdebug tools with -- php format
        if (!preg_match('/^xdebug-(trace|profile|coverage)$/', $scriptName)) {
            return;
        }

        // Check for help flag - let the tool handle help display
        if (isset($GLOBALS['argv'][1]) && ($GLOBALS['argv'][1] === '--help' || $GLOBALS['argv'][1] === '-h')) {
            return;
        }

        // Validate -- php format
        if (!isset($GLOBALS['argv'][1]) || $GLOBALS['argv'][1] !== '--') {
            if (isset($GLOBALS['argv'][1]) && !str_starts_with($GLOBALS['argv'][1], '-')) {
                fwrite(STDERR, "❌ Error: Missing '--'. Did you mean: {$scriptName} -- php {$GLOBALS['argv'][1]}?\n");
            } else {
                fwrite(STDERR, "❌ Error: Use format: {$scriptName} -- php script.php [args...]\n");
            }
            fwrite(STDERR, "Run '{$scriptName} --help' for usage information.\n");
            exit(1);
        }

        if (!isset($GLOBALS['argv'][2]) || $GLOBALS['argv'][2] !== 'php') {
            fwrite(STDERR, "❌ Error: Second argument must be 'php'\n");
            fwrite(STDERR, "Run '{$scriptName} --help' for usage information.\n");
            exit(1);
        }

        if (!isset($GLOBALS['argv'][3])) {
            fwrite(STDERR, "❌ Error: PHP script file is required\n");
            fwrite(STDERR, "Run '{$scriptName} --help' for usage information.\n");
            exit(1);
        }
    };
    $validate();
    // Load autoloader from possible paths
    $autoloadPaths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../autoload.php',
        __DIR__ . '/../../../autoload.php', // When installed via composer
    ];

    foreach ($autoloadPaths as $autoloadPath) {
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;

            // Return the directory containing vendor/ as project root
            $normalized = str_replace('\\', '/', $autoloadPath);
            return str_ends_with($normalized, '/vendor/autoload.php')
                ? dirname($autoloadPath, 2)  // Go up from vendor/autoload.php to project root
                : dirname($autoloadPath);    // For direct autoload.php paths
        }
    }
    // If we reach here, no autoloader was found
    $searchPaths = implode("\n  ", $autoloadPaths);
    fwrite(STDERR, "Error: Composer autoloader not found. Run 'composer install' first.\nSearched paths:\n  $searchPaths\n");
    exit(1);
})();
