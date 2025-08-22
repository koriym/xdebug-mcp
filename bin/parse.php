<?php

/**
 * Common functions for CLI debugging tools
 * Returns parse function
 */

return function (array $argv): array {
    /**
     * Parse CLI arguments supporting dual patterns:
     * Pattern 1: script.php -- args
     * Pattern 2: -- php script.php args
     */
    if ($argv[1] === '--') {
        // Pattern 2: -- php script.php args
        if (count($argv) < 3) {
            fwrite(STDERR, "❌ Error: PHP command and script required after --\n");
            fwrite(STDERR, "Use '{$argv[0]} --help' for usage information\n");
            exit(1);
        }
        $phpBinary = $argv[2];
        $targetFile = $argv[3] ?? '';
        $phpArgs = array_slice($argv, 4);
    } else {
        // Pattern 1: script.php -- args
        $phpBinary = PHP_BINARY;
        $targetFile = $argv[1];
        $phpArgs = [];
        
        // Find -- separator
        $separatorIndex = array_search('--', $argv, true);
        if ($separatorIndex !== false) {
            $phpArgs = array_slice($argv, $separatorIndex + 1);
        }
    }
    
    return [$phpBinary, $targetFile, $phpArgs];
};