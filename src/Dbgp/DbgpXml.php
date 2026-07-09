<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Dbgp;

use function intval;
use function ltrim;
use function preg_replace;
use function preg_replace_callback;
use function strlen;
use function substr;

/**
 * Sanitize and validate DBGp XML payloads from Xdebug.
 *
 * Xdebug can emit raw control bytes (e.g. NUL inside anonymous-class names on
 * PHP 8.3+) and invalid numeric character references such as &#0;. Both forms
 * make libxml's strict parser reject the response.
 */
final class DbgpXml
{
    /**
     * Strip XML 1.0 illegal control characters and invalid numeric character references.
     */
    public static function sanitize(string $xml): string
    {
        $xml = preg_replace_callback(
            '/&#(x[0-9A-Fa-f]+|\d+);/',
            static function (array $matches): string {
                $value = $matches[1];
                $isHex = $value[0] === 'x';
                $digits = $isHex ? ltrim(substr($value, 1), '0') : ltrim($value, '0');
                $digits = $digits === '' ? '0' : $digits;

                if (strlen($digits) > ($isHex ? 6 : 7)) {
                    return '';
                }

                $codepoint = $isHex ? intval($digits, 16) : (int) $digits;

                return self::isXmlCharacter($codepoint) ? $matches[0] : '';
            },
            $xml,
        ) ?? $xml;

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xml) ?? $xml;
    }

    public static function isXmlCharacter(int $codepoint): bool
    {
        return $codepoint === 0x09
            || $codepoint === 0x0A
            || $codepoint === 0x0D
            || ($codepoint >= 0x20 && $codepoint <= 0xD7FF)
            || ($codepoint >= 0xE000 && $codepoint <= 0xFFFD)
            || ($codepoint >= 0x10000 && $codepoint <= 0x10FFFF);
    }
}
