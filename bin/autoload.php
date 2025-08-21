<?php

/**
 * Common autoloader for bin scripts
 * Returns the path to the actual autoload.php file
 */

// Load autoloader and return its path
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../autoload.php',
    __DIR__ . '/../../../autoload.php', // When installed via composer
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        return $autoloadPath;
    }
}

fwrite(STDERR, "Error: Composer autoloader not found. Run 'composer install' first.\n");
exit(1);