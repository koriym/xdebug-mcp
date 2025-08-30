<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use function basename;
use function file_exists;
use function file_get_contents;
use function fwrite;
use function rtrim;
use function unlink;

use const STDERR;

class PhpunitConfigManager
{
    private string $projectRoot;

    public function __construct(string $projectRoot, private bool $verbose = false)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    /**
     * Find the PHPUnit configuration file
     */
    public function findConfigFile(): string|null
    {
        $candidates = [
            $this->projectRoot . '/phpunit.xml',
            $this->projectRoot . '/phpunit.xml.dist',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $this->log('Found PHPUnit config: ' . basename($candidate));

                return $candidate;
            }
        }

        return null;
    }

    /**
     * Get the effective configuration content as string
     */
    public function getEffectiveConfig(string|null $sourceConfig = null): string
    {
        if ($sourceConfig && file_exists($sourceConfig)) {
            $content = file_get_contents($sourceConfig);

            return $content ?: '';
        }

        // Create minimal config content
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;
    }

    /**
     * Clean up temporary files
     *
     * @param array<string> $tempFiles
     */
    public function cleanup(array $tempFiles): void
    {
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
                $this->log("Cleaned up: $file");
            }
        }
    }

    /**
     * Log message if verbose mode is enabled
     */
    private function log(string $message): void
    {
        if ($this->verbose) {
            fwrite(STDERR, "  $message\n");
        }
    }
}
