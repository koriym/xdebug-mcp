<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\Exceptions\XdebugNotAvailableException;

use function array_key_exists;
use function array_pop;
use function array_slice;
use function count;
use function escapeshellarg;
use function exec;
use function explode;
use function extension_loaded;
use function file_exists;
use function fwrite;
use function implode;
use function ini_get;
use function realpath;
use function sprintf;
use function str_starts_with;
use function trim;

use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;
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
 *
 * A target binary other than the running interpreter is probed by executing it:
 * an extension built for this interpreter's ABI cannot be loaded into another
 * PHP version, so its path is never reused across binaries.
 */
final class XdebugFinder
{
    /** Marks the probe answer so target startup warnings on stdout cannot be mistaken for it. */
    private const PROBE_MARKER = '__XDEBUG_MCP_PROBE__';

    /** @var array<string, array{loaded: bool, extensionDir: string, version: string}|null> */
    private static array $probeCache = [];

    /**
     * Get the appropriate Xdebug flag for command line usage
     *
     * @param string|null $phpBinary Target binary; null means the running interpreter
     *
     * @return string Empty string if already loaded, or -dzend_extension=PATH
     *
     * @throws XdebugNotAvailableException When a foreign target binary has no loadable Xdebug.
     */
    public static function getXdebugFlag(string|null $phpBinary = null): string
    {
        if (! self::isForeignBinary($phpBinary)) {
            // Check if Xdebug is already loaded
            if (extension_loaded('xdebug')) {
                return '';
            }

            // @codeCoverageIgnoreStart
            $xdebugPath = self::detectXdebugPath();

            if ($xdebugPath !== null) {
                return ' -dzend_extension=' . escapeshellarg($xdebugPath);
            }

            return '';
            // @codeCoverageIgnoreEnd
        }

        return self::targetXdebugFlag((string) $phpBinary);
    }

    /**
     * Detect Xdebug extension path from various installation methods
     *
     * @return string|null Path to xdebug.so or null if not found
     */
    public static function detectXdebugPath(): string|null
    {
        if (extension_loaded('xdebug')) {
            return null;
        }

        // @codeCoverageIgnoreStart
        return self::findExtensionFile(
            ini_get('extension_dir') ?: '',
            PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Check if Xdebug is available (loaded or can be loaded)
     *
     * @param string|null $phpBinary Target binary; null means the running interpreter
     *
     * @return bool True if Xdebug is available
     */
    public static function isXdebugAvailable(string|null $phpBinary = null): bool
    {
        if (! self::isForeignBinary($phpBinary)) {
            return extension_loaded('xdebug') || self::detectXdebugPath() !== null;
        }

        $probe = self::probe((string) $phpBinary);
        if ($probe === null) {
            return false;
        }

        return $probe['loaded'] || self::findExtensionFile($probe['extensionDir'], $probe['version']) !== null;
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
            exit(1); // @codeCoverageIgnore
        }
    }

    /** Resolve the flag for a binary that is not the running interpreter. */
    private static function targetXdebugFlag(string $phpBinary): string
    {
        $probe = self::probe($phpBinary);
        if ($probe === null) {
            // The binary could not be executed, so nothing is known about it.
            // Injecting this interpreter's extension would be wrong; let the
            // caller's own run report why the binary does not work.
            return '';
        }

        if ($probe['loaded']) {
            return '';
        }

        $path = self::findExtensionFile($probe['extensionDir'], $probe['version']);
        if ($path !== null) {
            return ' -dzend_extension=' . escapeshellarg($path);
        }

        throw new XdebugNotAvailableException(sprintf(
            'Xdebug is not loadable in PHP %s at "%s". Install Xdebug for that version, or enable it in its php.ini. '
            . 'This tool cannot inject the extension built for PHP %s.',
            $probe['version'],
            $phpBinary,
            PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        ));
    }

    /**
     * Whether the host-built `auto_prepend_file` helpers can run on the target.
     *
     * The helpers are written against this interpreter's PHP version, so the
     * question is version equality, not path identity: a second install of the
     * same version, or a PATH name like `php8.4`, is a perfectly good host for
     * them. The probe is skipped for the running interpreter, which is the
     * common local case; for anything else getXdebugFlag() has already probed
     * and cached, so this costs no extra process.
     */
    public static function canUseHostHelpers(string|null $phpBinary): bool
    {
        if (! self::isForeignBinary($phpBinary)) {
            return true;
        }

        $probe = self::probe((string) $phpBinary);

        return $probe !== null && $probe['version'] === PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }

    /**
     * A binary is foreign when it is neither unset nor literally the running
     * interpreter. This only decides whether a probe is needed — it is not a
     * version comparison (a PATH name never resolves here, so it reads as
     * foreign and gets probed).
     */
    private static function isForeignBinary(string|null $phpBinary): bool
    {
        if ($phpBinary === null || $phpBinary === '' || $phpBinary === 'php') {
            return false;
        }

        return (realpath($phpBinary) ?: $phpBinary) !== (realpath(PHP_BINARY) ?: PHP_BINARY);
    }

    /**
     * Ask the target binary about its own Xdebug state, extension_dir and version.
     *
     * The target may print startup warnings (duplicate modules, ABI notices) to
     * stdout before our echo, so the answer carries a marker and is matched per
     * line instead of being read as the whole output. stdin is closed because a
     * path that turns out not to be a PHP binary may otherwise sit waiting for
     * input forever.
     *
     * @return array{loaded: bool, extensionDir: string, version: string}|null Null when the binary cannot be run
     */
    private static function probe(string $phpBinary): array|null
    {
        if (array_key_exists($phpBinary, self::$probeCache)) {
            return self::$probeCache[$phpBinary];
        }

        $code = 'echo "\n", "' . self::PROBE_MARKER . '|", extension_loaded("xdebug") ? "1" : "0", "|", ini_get("extension_dir"), "|", PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION, "\n";';
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $output = [];
        $exitCode = 0;
        exec(
            sprintf(
                '%s -d error_reporting=0 -r %s 2>%s <%s',
                escapeshellarg($phpBinary),
                escapeshellarg($code),
                $nullDevice,
                $nullDevice,
            ),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            return self::$probeCache[$phpBinary] = null;
        }

        foreach ($output as $line) {
            if (! str_starts_with($line, self::PROBE_MARKER . '|')) {
                continue;
            }

            // Read the fixed fields from the ends: an extension_dir containing
            // the separator would otherwise shift everything after it.
            $parts = explode('|', trim($line));
            if (count($parts) < 4) {
                break;
            }

            $version = array_pop($parts);

            return self::$probeCache[$phpBinary] = [
                'loaded' => $parts[1] === '1',
                'extensionDir' => implode('|', array_slice($parts, 2)),
                'version' => $version,
            ];
        }

        return self::$probeCache[$phpBinary] = null;
    }

    /**
     * Look for the extension file in the reported extension_dir, then in the
     * Homebrew layout for that version.
     *
     * extension_dir comes from the binary itself and is keyed by the internal
     * API version (…/pecl/20240924), so whatever sits there matches that
     * build's ABI. The Homebrew path is only matched on major.minor, which
     * says nothing about architecture or thread safety, so it stays a
     * fallback for installations that keep the extension outside extension_dir.
     */
    private static function findExtensionFile(string $extensionDir, string $phpVersion): string|null
    {
        $extension = PHP_OS_FAMILY === 'Windows' ? 'php_xdebug.dll' : 'xdebug.so';

        if ($extensionDir !== '') {
            $standardPath = $extensionDir . DIRECTORY_SEPARATOR . $extension;
            if (file_exists($standardPath)) {
                return $standardPath;
            }
        }

        if (PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }

        foreach (["/opt/homebrew/opt/xdebug@{$phpVersion}/xdebug.so", "/usr/local/opt/xdebug@{$phpVersion}/xdebug.so"] as $brewPath) {
            if (file_exists($brewPath)) {
                return $brewPath;
            }
        }

        return null;
    }
}
