<?php

/**
 * Common autoloader loader for bin scripts
 * Loads the Composer autoloader from various possible locations
 * Returns the best-guess project root (directory containing vendor/)
 */

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

$searchPaths = implode("\n  ", $autoloadPaths);
fwrite(STDERR, "Error: Composer autoloader not found. Run 'composer install' first.\nSearched paths:\n  $searchPaths\n");
exit(1);
