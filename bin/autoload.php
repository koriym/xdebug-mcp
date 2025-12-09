<?php

/**
 * Common autoloader for bin scripts
 * Loads the Composer autoloader from various possible locations
 * Returns the best-guess project root (directory containing vendor/)
 *
 * IIFE(Immediately Invoked Function Expression) to avoid polluting global namespace
 */
return (function () {
    // Validate xdebug tool CLI format using closure for clean separation
    $validate = function () {
        if (PHP_SAPI !== 'cli' || !isset($GLOBALS['argv']) || count($GLOBALS['argv']) === 0) {
            return; // Skip validation for non-CLI contexts
        }

        $scriptName = basename($GLOBALS['argv'][0]);

        // Handle xdebug-debug and xdebug-coverage separately (different formats)
        if ($scriptName === 'xdebug-debug' || $scriptName === 'xdebug-coverage') {
            // Let these tools handle their own argument validation and help display
            return; // These tools use different formats, skip other validation
        }

        // Only validate for specific xdebug tools with -- php format
        if (!preg_match('/^xdebug-(trace|profile|coverage)$/', $scriptName)) {
            return;
        }

        // Check for help flag - let the tool handle help display
        if (isset($GLOBALS['argv'][1]) && ($GLOBALS['argv'][1] === '--help' || $GLOBALS['argv'][1] === '-h')) {
            return;
        }

        // Find the position of '--' delimiter, accounting for optional flags
        $dashDashPos = array_search('--', $GLOBALS['argv']);
        if ($dashDashPos === false) {
            // Try to provide helpful error message
            $nonFlagArg = null;
            for ($i = 1; $i < count($GLOBALS['argv']); $i++) {
                if (!str_starts_with($GLOBALS['argv'][$i], '-')) {
                    $nonFlagArg = $GLOBALS['argv'][$i];
                    break;
                }
            }

            if ($nonFlagArg !== null) {
                fwrite(STDERR, "❌ Error: Missing '--'. Did you mean: {$scriptName} -- php {$nonFlagArg}?\n");
            } else {
                fwrite(STDERR, "❌ Error: Use format: {$scriptName} -- php script.php [args...]\n");
            }
            fwrite(STDERR, "Run '{$scriptName} --help' for usage information.\n");
            exit(1);
        }

        // Allow 'php', 'docker', 'podman', 'kubectl' as valid commands after '--'
        $validCommands = ['php', 'docker', 'podman', 'kubectl'];
        $firstArg = $GLOBALS['argv'][$dashDashPos + 1] ?? '';
        if (!in_array($firstArg, $validCommands, true)) {
            fwrite(STDERR, "❌ Error: Argument after '--' must be 'php' or a container command (docker, podman, kubectl)\n");
            fwrite(STDERR, "Run '{$scriptName} --help' for usage information.\n");
            exit(1);
        }

        // For local PHP, require script file. For containers, skip this check.
        if ($firstArg === 'php' && !isset($GLOBALS['argv'][$dashDashPos + 2])) {
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
