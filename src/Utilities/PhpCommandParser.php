<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Utilities;

use RuntimeException;

use function array_slice;
use function basename;
use function in_array;
use function preg_match;
use function preg_split;
use function str_replace;
use function str_starts_with;
use function trim;

/**
 * Parser for PHP command-line invocations
 *
 * Centralizes knowledge about PHP binary detection, CLI option handling,
 * inline code options (-r/--run), and locating the script file within a
 * command array.
 */
final class PhpCommandParser
{
    /**
     * Regex pattern to match PHP binary executables.
     *
     * Matches 'php' optionally followed by a version number, with optional
     * .exe suffix. Supports both Unix (/) and Windows (\) path separators.
     *
     * Note: This intentionally does NOT match php-fpm, php-cgi, or other
     * PHP SAPI binaries, as those are server processes not suitable for CLI
     * script execution.
     */
    public const PHP_BINARY_PATTERN = '#(?:^|[\\\\/])php(?:[-@]?\d+(?:\.\d+)*)?(?:\.exe)?$#i';

    /** PHP CLI options that consume the following argument as their value. */
    public const PHP_OPTIONS_WITH_VALUE = ['-d', '-c', '-z', '-B', '-R', '-F', '-E'];

    /**
     * Long-form PHP CLI options that consume the following argument as their value.
     * These are the long aliases of {@see PHP_OPTIONS_WITH_VALUE}. The attached form
     * (e.g. "--define=foo=bar") needs no special handling; only the space-separated
     * form (e.g. "--define foo=bar") must skip the following value argument.
     */
    public const PHP_LONG_OPTIONS_WITH_VALUE = [
        '--define',
        '--php-ini',
        '--zend-extension',
        '--process-begin',
        '--process-code',
        '--process-file',
        '--process-end',
    ];

    /** PHP CLI options that execute inline source code instead of a file. */
    public const PHP_INLINE_CODE_OPTIONS = ['-r', '--run'];

    /**
     * Check if the given path is a PHP binary.
     */
    public static function isPhpBinary(string $path): bool
    {
        return preg_match(self::PHP_BINARY_PATTERN, $path) === 1;
    }

    /**
     * Check if the given path is a PHPUnit command.
     */
    public static function isPhpUnitCommand(string $path): bool
    {
        $binary = basename(str_replace('\\', '/', $path));

        return $binary === 'phpunit' || $binary === 'phpunit.phar';
    }

    /**
     * Find the local script file argument in PHP command parts.
     *
     * Scans the parts after an optional PHP binary, skipping PHP options and
     * their values. Returns the first non-option token, or null when inline
     * code is being executed.
     *
     * @param array<int, string> $parts Command parts after the PHP binary.
     *
     * @throws RuntimeException When an inline code option lacks its code argument.
     */
    public static function findLocalFileArgument(array $parts): string|null
    {
        for ($index = 0; isset($parts[$index]); $index++) {
            $arg = $parts[$index];

            if (in_array($arg, self::PHP_INLINE_CODE_OPTIONS, true)) {
                if (! isset($parts[$index + 1])) {
                    throw new RuntimeException("Code argument is required after {$arg}");
                }

                return null;
            }

            if (str_starts_with($arg, '-r') && $arg !== '-r') {
                return null;
            }

            if (str_starts_with($arg, '--run=')) {
                return null;
            }

            if ($arg === '--') {
                return $parts[$index + 1] ?? '';
            }

            if (! str_starts_with($arg, '-')) {
                return $arg;
            }

            $takesValue = in_array($arg, self::PHP_OPTIONS_WITH_VALUE, true)
                || in_array($arg, self::PHP_LONG_OPTIONS_WITH_VALUE, true);
            if (! $takesValue || ! isset($parts[$index + 1])) {
                continue;
            }

            $index++;
        }

        return '';
    }

    /**
     * Find the script argument index after the PHP binary.
     *
     * Skips PHP options (both short and long) that consume a value argument.
     *
     * @param array<int, string> $parts    Command parts.
     * @param int                $phpIndex Index of the PHP binary in $parts.
     *
     * @return int|false Index of the script argument, or false if not found.
     */
    public static function findScriptIndex(array $parts, int $phpIndex): int|false
    {
        $scriptIndex = $phpIndex + 1;

        while (isset($parts[$scriptIndex]) && str_starts_with($parts[$scriptIndex], '-')) {
            $currentOption = $parts[$scriptIndex];
            $scriptIndex++;

            if (
                ! in_array($currentOption, self::PHP_OPTIONS_WITH_VALUE, true)
                && ! in_array($currentOption, self::PHP_LONG_OPTIONS_WITH_VALUE, true)
            ) {
                continue;
            }

            if (! isset($parts[$scriptIndex])) {
                continue;
            }

            $scriptIndex++;
        }

        return isset($parts[$scriptIndex]) ? $scriptIndex : false;
    }

    /**
     * Detect whether a command string runs PHP inline code (-r / --run).
     *
     * Only inspects interpreter options before the script file; arguments
     * after the script boundary (e.g. `php app.php -r dry-run`) are ignored.
     */
    public static function isPhpInlineCodeScript(string $script): bool
    {
        $parts = preg_split('/\s+/', trim($script)) ?: [];
        if ($parts === [] || ! self::isPhpBinary($parts[0])) {
            return false;
        }

        return self::findPhpRunCodeArgument(array_slice($parts, 1)) !== null;
    }

    /**
     * Find a PHP inline-code option within interpreter arguments.
     *
     * Stops scanning at the first non-option token or "--" so that script
     * arguments are not mistaken for inline code.
     *
     * @param array<int, string> $args Command parts with the PHP binary already removed.
     *
     * @return array{code_index: int, code_prefix: string, option: string}|null
     */
    public static function findPhpRunCodeArgument(array $args): array|null
    {
        for ($index = 0; isset($args[$index]); $index++) {
            $arg = $args[$index];

            if ($arg === '-r' || $arg === '--run') {
                return ['code_index' => $index + 1, 'code_prefix' => '', 'option' => $arg];
            }

            if (str_starts_with($arg, '-r') && $arg !== '-r') {
                return ['code_index' => $index, 'code_prefix' => '-r', 'option' => '-r'];
            }

            if (str_starts_with($arg, '--run=')) {
                return ['code_index' => $index, 'code_prefix' => '--run=', 'option' => '--run'];
            }

            if ($arg === '--' || ! str_starts_with($arg, '-')) {
                return null;
            }

            if (
                ! in_array($arg, self::PHP_OPTIONS_WITH_VALUE, true)
                && ! in_array($arg, self::PHP_LONG_OPTIONS_WITH_VALUE, true)
            ) {
                continue;
            }

            if (! isset($args[$index + 1])) {
                continue;
            }

            $index++;
        }

        return null;
    }

    /**
     * Normalize a user-provided PHPUnit command into arguments for the local PHPUnit binary.
     *
     * Strips the leading PHP binary and PHPUnit command when present, and removes
     * flags that disable coverage.
     *
     * @param array<int, string> $args
     *
     * @return array<int, string>
     */
    public static function normalizePhpUnitArguments(array $args): array
    {
        if (! empty($args) && self::isPhpBinary($args[0])) {
            $args = array_slice($args, 1);
        }

        if (! empty($args) && self::isPhpUnitCommand($args[0])) {
            $args = array_slice($args, 1);
        }

        $normalized = [];
        foreach ($args as $arg) {
            if ($arg === '--no-coverage') {
                continue;
            }

            $normalized[] = $arg;
        }

        return $normalized;
    }

    /**
     * Normalize raw arguments by stripping the leading PHP binary.
     *
     * @param array<int, string> $args
     *
     * @return array<int, string>
     */
    public static function normalizeRawArguments(array $args): array
    {
        if (! empty($args) && self::isPhpBinary($args[0])) {
            return array_slice($args, 1);
        }

        return $args;
    }
}
