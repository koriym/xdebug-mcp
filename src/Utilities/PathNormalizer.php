<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Utilities;

use function array_pop;
use function explode;
use function implode;
use function preg_match;
use function str_replace;
use function str_starts_with;
use function substr;

/**
 * Utility class for path normalization
 *
 * Normalizes paths by resolving . and .. segments.
 * Compatible with phar:// and other stream wrappers unlike realpath().
 */
final class PathNormalizer
{
    /**
     * Normalise a path by resolving . and .. segments.
     * Compatible with phar:// and other stream wrappers unlike realpath().
     */
    public static function normalise(string $path): string
    {
        // Handle Windows paths by normalising to forward slashes
        $path = str_replace('\\', '/', $path);

        // Preserve stream wrapper prefix (phar://, zip://, etc.)
        $prefix = '';
        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*://)(.*)$#', $path, $matches)) {
            $prefix = $matches[1];
            $path = $matches[2];
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $path = substr($path, 1);
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return $prefix . implode('/', $parts);
    }
}
