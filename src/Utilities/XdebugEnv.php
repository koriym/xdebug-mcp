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
     * XDEBUG_CONFIG and XDEBUG_TRIGGER are truly unset via `env -u` — not
     * emptied, because an empty-but-present variable still counts as set
     * for Xdebug (an empty XDEBUG_TRIGGER activates the trigger). Both can
     * alter the effective Xdebug mode/features regardless of `-d` flags.
     */
    public static function shellPrefix(string $mode): string
    {
        return sprintf('env -u XDEBUG_CONFIG -u XDEBUG_TRIGGER XDEBUG_MODE=%s ', escapeshellarg($mode));
    }

    /**
     * Emit a one-line notice when inherited Xdebug variables are overridden
     *
     * XDEBUG_MODE is reported only when its value differs from the pinned
     * mode — an inherited value identical to what the tool would set is not
     * overridden in effect. XDEBUG_CONFIG/XDEBUG_TRIGGER are reported on any
     * presence, including empty-but-present (which Xdebug treats as set).
     *
     * @param string        $pinnedMode The mode the tool pins via shellPrefix()
     * @param resource|null $stderr     Stream for the notice (STDERR by default)
     */
    public static function noticeIfInherited(string $pinnedMode, $stderr = null): void
    {
        $stderr ??= STDERR;

        $found = [];
        foreach (self::INHERITED_VARS as $var) {
            $value = getenv($var);
            if ($value === false) {
                continue;
            }

            if ($var === 'XDEBUG_MODE' && $value === $pinnedMode) {
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
