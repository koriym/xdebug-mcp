<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Utilities;

use function escapeshellarg;
use function fwrite;
use function getenv;
use function implode;
use function sprintf;

use const STDERR;

/**
 * Pins Xdebug environment variables for spawned child processes
 *
 * Environment variables take precedence over `-d` flags, so an inherited
 * XDEBUG_MODE / XDEBUG_CONFIG / XDEBUG_TRIGGER would hijack the tool's own
 * Xdebug configuration (or silently produce stale results). Explicitly
 * setting the variables in the child environment wins over any inherited
 * value, leaving the user's own shell environment untouched.
 */
final class XdebugEnv
{
    private const INHERITED_VARS = ['XDEBUG_MODE', 'XDEBUG_CONFIG', 'XDEBUG_TRIGGER'];

    /**
     * Shell prefix that pins the Xdebug mode for a child command
     *
     * XDEBUG_CONFIG and XDEBUG_TRIGGER are cleared because both can alter
     * the effective Xdebug mode/features regardless of `-d` flags.
     */
    public static function shellPrefix(string $mode): string
    {
        return sprintf('XDEBUG_MODE=%s XDEBUG_CONFIG= XDEBUG_TRIGGER= ', escapeshellarg($mode));
    }

    /**
     * Emit a one-line notice when inherited Xdebug variables are overridden
     *
     * @param resource|null $stderr Stream for the notice (STDERR by default)
     */
    public static function noticeIfInherited($stderr = null): void
    {
        $stderr ??= STDERR;

        $found = [];
        foreach (self::INHERITED_VARS as $var) {
            $value = getenv($var);
            if ($value === false || $value === '') {
                continue;
            }

            $found[] = $var;
        }

        if ($found === []) {
            return;
        }

        fwrite($stderr, sprintf(
            "Note: inherited %s ignored; the tool pins its own Xdebug configuration.\n",
            implode(', ', $found),
        ));
    }
}
