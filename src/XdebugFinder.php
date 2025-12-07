<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use function escapeshellarg;
use function extension_loaded;
use function file_exists;
use function fwrite;
use function ini_get;

use const DIRECTORY_SEPARATOR;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const PHP_OS_FAMILY;
use const STDERR;

/**
 * Intelligent Xdebug detection and loading utility
 *
 * Handles various Xdebug installation methods:
 * - Already loaded via php.ini
 * - Homebrew installation
 * - Standard extension_dir installation
 * - PECL installation
 */
final class XdebugFinder
{
    /**
     * Get the appropriate Xdebug flag for command line usage
     *
     * @return string Empty string if already loaded, or -dzend_extension=PATH
     */
    public static function getXdebugFlag(): string
    {
        // Check if Xdebug is already loaded
        if (extension_loaded('xdebug')) {
            return '';
        }

        // Try to detect Xdebug path
        $xdebugPath = self::detectXdebugPath();

        if ($xdebugPath !== null) {
            return ' -dzend_extension=' . escapeshellarg($xdebugPath);
        }

        // Xdebug not found - return empty and let caller handle
        return '';
    }

    /**
     * Detect Xdebug extension path from various installation methods
     *
     * @return string|null Path to xdebug.so or null if not found
     */
    public static function detectXdebugPath(): ?string
    {
        // 1. Check if already loaded (shouldn't reach here from getXdebugFlag but safety check)
        if (extension_loaded('xdebug')) {
            return null; // Already loaded, no path needed
        }

        // 2. Check Homebrew installation paths (macOS only)
        if (PHP_OS_FAMILY === 'Darwin') {
            $phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            $brewPaths = [
                "/opt/homebrew/opt/xdebug@{$phpVersion}/xdebug.so",
                "/usr/local/opt/xdebug@{$phpVersion}/xdebug.so",
            ];

            foreach ($brewPaths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        // 3. Check standard extension_dir
        $extensionDir = ini_get('extension_dir');
        if ($extensionDir !== false && $extensionDir !== '') {
            $extension = PHP_OS_FAMILY === 'Windows' ? 'php_xdebug.dll' : 'xdebug.so';
            $standardPath = $extensionDir . DIRECTORY_SEPARATOR . $extension;
            if (file_exists($standardPath)) {
                return $standardPath;
            }
        }

        return null;
    }

    /**
     * Check if Xdebug is available (loaded or can be loaded)
     *
     * @return bool True if Xdebug is available
     */
    public static function isXdebugAvailable(): bool
    {
        return extension_loaded('xdebug') || self::detectXdebugPath() !== null;
    }

    /**
     * Display installation guidance when Xdebug is not found
     *
     * @param bool $exitAfter Whether to exit after showing guidance
     */
    public static function showInstallationGuidance(bool $exitAfter = true): void
    {
        fwrite(STDERR, "❌ Xdebug not found. Please install Xdebug to use this tool.\n");
        fwrite(STDERR, "📖 More info: https://xdebug.org/docs/install\n");

        if ($exitAfter) {
            exit(1);
        }
    }
}
