<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use DOMDocument;
use DOMXPath;
use RuntimeException;

use function basename;
use function file_exists;
use function file_get_contents;
use function fwrite;
use function rtrim;
use function sys_get_temp_dir;
use function tempnam;
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
     * Check if TraceExtension is already configured in the config file
     */
    public function hasTraceExtension(string $configFile): bool
    {
        $dom = new DOMDocument();

        if (! @$dom->load($configFile)) {
            return false;
        }

        $xpath = new DOMXPath($dom);
        $extensions = $xpath->query('//extensions/bootstrap[@class="Koriym\\XdebugMcp\\TraceExtension"]');

        return $extensions->length > 0;
    }

    /**
     * Create a temporary PHPUnit config with TraceExtension injected
     */
    public function createConfigWithExtension(string|null $sourceConfig = null, string|null $outputPath = null): string
    {
        if ($sourceConfig && ! file_exists($sourceConfig)) {
            throw new RuntimeException("Source config file not found: $sourceConfig");
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if ($sourceConfig) {
            if (! @$dom->load($sourceConfig)) {
                throw new RuntimeException("Failed to parse PHPUnit config: $sourceConfig");
            }

            $this->log('Loaded existing config: ' . basename($sourceConfig));
        } else {
            $this->createMinimalConfig($dom);
            $this->log('Created minimal PHPUnit config');
        }

        // Inject TraceExtension if not already present
        $injected = $this->injectTraceExtension($dom);

        // Determine output path
        if (! $outputPath) {
            $outputPath = tempnam('/tmp', 'phpunit_xdebug_mcp_') . '.xml';
        }

        if (! $dom->save($outputPath)) {
            throw new RuntimeException("Failed to save config file: $outputPath");
        }

        if ($injected) {
            $this->log('Injected TraceExtension into config: ' . basename($outputPath));
        } else {
            $this->log('TraceExtension already present in config: ' . basename($outputPath));
        }

        return $outputPath;
    }

    /**
     * Get the effective configuration content as string
     */
    public function getEffectiveConfig(string|null $sourceConfig = null): string
    {
        $tempConfig = $this->createConfigWithExtension($sourceConfig);
        $content = file_get_contents($tempConfig);
        unlink($tempConfig);

        return $content ?: '';
    }

    /**
     * Create minimal PHPUnit configuration
     */
    private function createMinimalConfig(DOMDocument $dom): void
    {
        $xml = <<<'XML'
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

        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('Failed to create minimal PHPUnit config');
        }
    }

    /**
     * Inject TraceExtension into the DOM
     *
     * @return bool True if injection occurred, false if already present
     */
    private function injectTraceExtension(DOMDocument $dom): bool
    {
        $xpath = new DOMXPath($dom);

        // Check if TraceExtension already exists
        $existing = $xpath->query('//extensions/bootstrap[@class="Koriym\\XdebugMcp\\TraceExtension"]');
        if ($existing->length > 0) {
            return false; // Already present
        }

        // Find or create extensions element
        $extensionsNodes = $xpath->query('//extensions');

        if ($extensionsNodes->length === 0) {
            // Create extensions element
            $phpunitElement = $xpath->query('//phpunit')->item(0);
            if (! $phpunitElement) {
                throw new RuntimeException('Invalid PHPUnit config: missing <phpunit> element');
            }

            $extensionsElement = $dom->createElement('extensions');
            $phpunitElement->appendChild($extensionsElement);
        } else {
            $extensionsElement = $extensionsNodes->item(0);
        }

        // Add TraceExtension
        $bootstrapElement = $dom->createElement('bootstrap');
        $bootstrapElement->setAttribute('class', 'Koriym\\XdebugMcp\\TraceExtension');
        $extensionsElement->appendChild($bootstrapElement);

        return true; // Injection occurred
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
