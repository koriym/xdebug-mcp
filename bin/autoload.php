<?php

/**
 * Common autoloader and project root detection for bin scripts
 * Returns an array with 'autoload' and 'project_root' paths
 */

// Load autoloader and determine project root
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../autoload.php',
    __DIR__ . '/../../../autoload.php', // When installed via composer
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        
        // Determine project root from autoloader path
        $normalized = str_replace('\\', '/', $autoloadPath);
        $projectRoot = str_ends_with($normalized, '/vendor/autoload.php')
            ? dirname($autoloadPath, 2)
            : dirname($autoloadPath);
        
        if (!file_exists($projectRoot . '/composer.json')) {
            fwrite(STDERR, "Error: Could not determine project root from autoloader path: $autoloadPath\n");
            exit(1);
        }
        
        return [
            'autoload' => $autoloadPath,
            'project_root' => $projectRoot
        ];
    }
}

$searchPaths = implode("\n  ", $autoloadPaths);
fwrite(STDERR, "Error: Composer autoloader not found. Run 'composer install' first.\nSearched paths:\n  $searchPaths\n");
exit(1);
