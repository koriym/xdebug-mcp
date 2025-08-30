<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function ltrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

use const JSON_ERROR_NONE;

/**
 * CLI arguments to MCP params normalizer
 *
 * Converts CLI-style arguments to structured MCP parameters following strict rules:
 * - Long options only: --key=value
 * - Type annotations: --key:type=value (str/int/float/bool/json)
 * - Position args after --: stored in "args" array
 * - No short options, no space-separated values, no ambiguity
 */
class CLIParamsNormalizer
{
    private const ALLOWED_TYPES = ['str', 'int', 'float', 'bool', 'json'];

    /**
     * Normalize CLI string to MCP params
     *
     * @param string $cliString CLI arguments string
     *
     * @return array Normalized MCP params
     *
     * @throws InvalidArgumentException On invalid format.
     */
    public function normalize(string $cliString): array
    {
        $tokens = $this->tokenize($cliString);
        $params = [];
        $i = 0;

        // Process options until we hit --
        while ($i < count($tokens) && $tokens[$i] !== '--') {
            if (! str_starts_with($tokens[$i], '--')) {
                throw new InvalidArgumentException(
                    '不正：位置引数は -- 後のみ許可。例：--key:str=value -- args',
                );
            }

            $option = substr($tokens[$i], 2); // Remove --
            $this->parseOption($option, $params);
            $i++;
        }

        // Process positional args after --
        if ($i < count($tokens) && $tokens[$i] === '--') {
            $i++; // Skip --
            $args = array_slice($tokens, $i);
            if (! empty($args)) {
                $params['args'] = $args;
            }
        }

        return $params;
    }

    /**
     * Tokenize CLI string respecting quotes
     */
    private function tokenize(string $cliString): array
    {
        $tokens = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $len = strlen($cliString);

        for ($i = 0; $i < $len; $i++) {
            $char = $cliString[$i];

            if (! $inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                continue; // Don't include opening quote
            }

            if ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = null;
                continue; // Don't include closing quote
            }

            if (! $inQuotes && $char === ' ') {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return array_values(array_filter($tokens, static fn ($t) => $t !== '')); // Remove only empty strings, preserve "0"
    }

    /**
     * Parse single option: --key:type=value or --key=value
     */
    private function parseOption(string $option, array &$params): void
    {
        // Check for = separator
        if (! str_contains($option, '=')) {
            throw new InvalidArgumentException(
                "不正：= が必要です。--{$option}:str=value を使用してください。",
            );
        }

        [$keyPart, $value] = explode('=', $option, 2);

        // Parse key:type or just key
        $type = 'str'; // Default type
        if (str_contains($keyPart, ':')) {
            [$key, $typeStr] = explode(':', $keyPart, 2);

            if (! in_array($typeStr, self::ALLOWED_TYPES, true)) {
                throw new InvalidArgumentException(
                    "不正：型 '{$typeStr}' は許可されていません。許可型: " . implode(', ', self::ALLOWED_TYPES),
                );
            }

            $type = $typeStr;
        } else {
            $key = $keyPart;
        }

        if (empty($key)) {
            throw new InvalidArgumentException('不正：キー名が空です。');
        }

        // Convert hyphens to underscores for consistency with PHP array keys
        $key = str_replace('-', '_', $key);

        // Convert value based on type
        $convertedValue = $this->convertValue($value, $type, $key);

        // Handle array values (repeated keys)
        if (isset($params[$key])) {
            if (! is_array($params[$key])) {
                $params[$key] = [$params[$key]]; // Convert to array
            }

            $params[$key][] = $convertedValue;
        } else {
            $params[$key] = $convertedValue;
        }
    }

    /**
     * Convert string value to specified type
     */
    private function convertValue(string $value, string $type, string $key): mixed
    {
        return match ($type) {
            'str' => $value,
            'int' => $this->convertInt($value, $key),
            'float' => $this->convertFloat($value, $key),
            'bool' => $this->convertBool($value, $key),
            'json' => $this->convertJson($value, $key),
            default => throw new InvalidArgumentException("未対応の型: {$type}")
        };
    }

    private function convertInt(string $value, string $key): int
    {
        if (! is_numeric($value) || ! ctype_digit(ltrim($value, '-'))) {
            throw new InvalidArgumentException(
                "不正：--{$key}:int の値 '{$value}' は整数ではありません。",
            );
        }

        return (int) $value;
    }

    private function convertFloat(string $value, string $key): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException(
                "不正：--{$key}:float の値 '{$value}' は数値ではありません。",
            );
        }

        return (float) $value;
    }

    private function convertBool(string $value, string $key): bool
    {
        $lowered = strtolower($value);
        if ($lowered === 'true') {
            return true;
        }

        if ($lowered === 'false') {
            return false;
        }

        throw new InvalidArgumentException(
            "不正：--{$key}:bool の値は 'true' または 'false' である必要があります。入力: '{$value}'",
        );
    }

    private function convertJson(string $value, string $key): mixed
    {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                "不正：--{$key}:json の値は有効なJSONではありません。エラー: " . json_last_error_msg(),
            );
        }

        return $decoded;
    }
}
