#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Koriym\XdebugMcp\XdebugRunner;

// Simulate xdebug-trace command with Docker
$argv = [
    'xdebug-trace',
    '--context=Docker runner test',
    '--',
    'docker',
    'compose',
    'run',
    '--rm',
    'php',
    'php',
    '/app/test_script.php',
];

try {
    $runner = new XdebugRunner($argv);
    $runner->setMode('trace');
    $runner->setContext('Docker runner test');

    echo "Running Docker command with Xdebug trace mode...\n\n";

    $exitCode = $runner->run();

    echo "\n\nExit code: $exitCode\n";

    // Check if trace file was created
    $traceFiles = glob('/tmp/trace.*.xt');
    if (! empty($traceFiles)) {
        $latestTrace = end($traceFiles);
        echo "Trace file created: $latestTrace\n";
        echo 'File size: ' . filesize($latestTrace) . " bytes\n";
    }
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
