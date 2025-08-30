<?php

declare(strict_types=1);

echo "Script started with arguments:\n";
foreach ($argv as $i => $arg) {
    echo "  argv[$i] = $arg\n";
}

$name = $argv[1] ?? 'Anonymous';
$mode = $argv[2] ?? 'normal';

echo "Hello, $name!\n";
echo "Mode: $mode\n";

if ($mode === 'verbose') {
    echo "Extra verbose output enabled\n";
    $details = ['version' => '1.0', 'debug' => true];
    print_r($details);
}

echo "Script completed\n";
