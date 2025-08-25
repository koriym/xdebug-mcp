<?php

declare(strict_types=1);

/**
 * Common autoloader for bin scripts
 * Loads the Composer autoloader from various possible locations
 * Returns the best-guess project root (directory containing vendor/)
 *
 * IIFE(Immediately Invoked Function Expression) to avoid polluting global namespace
 */

(static function () {
    // Validate xdebug tool CLI format using closure for clean separation
    $validate = static function () {
        if (PHP_SAPI !== 'cli' || ! isset($GLOBALS['argv']) || count($GLOBALS['argv']) === 0) {
            return; // Skip validation for non-CLI contexts
        }

        $scriptName = basename($GLOBALS['argv'][0]);

        // Handle xdebug-debug separately (different format)
        if ($scriptName === 'xdebug-debug') {
            if (! isset($GLOBALS['argv'][1])) {
                fwrite(STDERR, "❌ Error: Script file is required\n");
                fwrite(STDERR, "Usage: {$scriptName} <script.php>\n");
                exit(1);
            }

            return; // xdebug-debug uses different format, skip other validation
        }

        // Only validate for specific xdebug tools with -- php format
        if (! preg_match('/^xdebug-(trace|profile|coverage)$/', $scriptName)) {
            return;
        }

        // Check for help flag - let the tool handle help display
        if (isset($GLOBALS['argv'][1]) && ($GLOBALS['argv'][1] === '--help' || $GLOBALS['argv'][1] === '-h')) {
            return;
        }

        // Special handling for xdebug tools with --json flag
        if (preg_match('/^xdebug-(trace|profile|coverage)$/', $scriptName) && isset($GLOBALS['argv'][1]) && $GLOBALS['argv'][1] === '--json') {
            // Validate --json -- php format
            if (! isset($GLOBALS['argv'][2]) || $GLOBALS['argv'][2] !== '--') {
                fwrite(STDERR, "❌ Error: Use format: {$scriptName} --json -- php script.php [args...]\n");
                exit(1);
            }
            if (! isset($GLOBALS['argv'][3]) || $GLOBALS['argv'][3] !== 'php') {
                fwrite(STDERR, "❌ Error: Third argument must be 'php'\n");
                exit(1);
            }
            if (! isset($GLOBALS['argv'][4])) {
                fwrite(STDERR, "❌ Error: PHP script file is required\n");
                exit(1);
            }
            return; // Valid --json -- php format
        }

        // Special handling for xdebug tools with --claude flag  
        if (preg_match('/^xdebug-(trace|profile|coverage)$/', $scriptName) && isset($GLOBALS['argv'][1]) && $GLOBALS['argv'][1] === '--claude') {
            // Validate --claude -- php format
            if (! isset($GLOBALS['argv'][2]) || $GLOBALS['argv'][2] !== '--') {
                fwrite(STDERR, "❌ Error: Use format: {$scriptName} --claude -- php script.php [args...]\n");
                exit(1);
            }
            if (! isset($GLOBALS['argv'][3]) || $GLOBALS['argv'][3] !== 'php') {
                fwrite(STDERR, "❌ Error: Third argument must be 'php'\n");
                exit(1);
            }
            if (! isset($GLOBALS['argv'][4])) {
                fwrite(STDERR, "❌ Error: PHP script file is required\n");
                exit(1);
            }
            return; // Valid --claude -- php format
        }

        // Validate standard -- php format
        if (! isset($GLOBALS['argv'][1]) || $GLOBALS['argv'][1] !== '--') {
            if (isset($GLOBALS['argv'][1]) && ! str_starts_with($GLOBALS['argv'][1], '-')) {
                fwrite(STDERR, "❌ Error: Missing '--'. Did you mean: {$scriptName} -- php {$GLOBALS['argv'][1]}?\n");
            } else {
                fwrite(STDERR, "❌ Error: Use format: {$scriptName} -- php script.php [args...]\n");
            }

            fwrite(STDERR, "Run '{$scriptName} --help' for usage information.\n");
            exit(1);
        }

        if (! isset($GLOBALS['argv'][2]) || $GLOBALS['argv'][2] !== 'php') {
            fwrite(STDERR, "❌ Error: Second argument must be 'php'\n");
            fwrite(STDERR, "Run '{$scriptName} --help' for usage information.\n");
            exit(1);
        }

        if (! isset($GLOBALS['argv'][3])) {
            fwrite(STDERR, "❌ Error: PHP script file is required\n");
            fwrite(STDERR, "Run '{$scriptName} --help' for usage information.\n");
            exit(1);
        }
    };
    $validate();
    // Load autoloader from possible paths
    $autoloadPaths = [
        // From package source (bin/ → ../vendor/autoload.php)
        __DIR__ . '/../vendor/autoload.php',
        // From Composer shim (vendor/bin/ → ../autoload.php)  
        dirname(__DIR__) . '/../autoload.php',
        // When installed as dependency (deeper nesting)
        dirname(__DIR__, 2) . '/vendor/autoload.php',
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
