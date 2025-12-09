<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use JsonException;
use Koriym\XdebugMcp\DTO\CliParams;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;

use function array_slice;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function ltrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * CLI arguments to MCP params normalizer
 *
 * Converts CLI-style arguments to structured MCP parameters following strict rules:
 * - Long options only: --key=value
 * - Type annotations: --key:type=value (str/int/float/bool)
 * - Position args after --: stored in positionalArgs
 * - No short options, no space-separated values, no ambiguity
 */
class CLIParamsNormalizer
{
    private const ALLOWED_TYPES = ['str', 'int', 'float', 'bool', 'json'];

    /** @var array<string, string> */
    private array $stringParams = [];

    /** @var array<string, int> */
    private array $intParams = [];

    /** @var array<string, float> */
    private array $floatParams = [];

    /** @var array<string, bool> */
    private array $boolParams = [];

    /** @var array<string, list<string>> */
    private array $jsonParams = [];

    /** @var list<string> */
    private array $positionalArgs = [];

    /**
     * Normalize CLI string to CliParams DTO
     *
     * @throws InvalidArgumentException On invalid format.
     */
    public function normalize(string $cliString): CliParams
    {
        // Reset state
        $this->stringParams = [];
        $this->intParams = [];
        $this->floatParams = [];
        $this->boolParams = [];
        $this->jsonParams = [];
        $this->positionalArgs = [];

        $tokens = $this->tokenize($cliString);
        $i = 0;

        // Process options until we hit --
        while ($i < count($tokens) && $tokens[$i] !== '--') {
            if (! str_starts_with($tokens[$i], '--')) {
                throw new InvalidArgumentException(
                    '不正：位置引数は -- 後のみ許可。例：--key:str=value -- args',
                );
            }

            $option = substr($tokens[$i], 2); // Remove --
            $this->parseOption($option);
            $i++;
        }

        // Process positional args after --
        if ($i < count($tokens) && $tokens[$i] === '--') {
            $i++; // Skip --
            $this->positionalArgs = array_slice($tokens, $i);
        }

        return new CliParams(
            stringParams: $this->stringParams,
            intParams: $this->intParams,
            floatParams: $this->floatParams,
            boolParams: $this->boolParams,
            jsonParams: $this->jsonParams,
            positionalArgs: $this->positionalArgs,
        );
    }

    /**
     * Tokenize CLI string respecting quotes
     *
     * @return list<string>
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

        return $tokens;
    }

    /**
     * Parse single option: --key:type=value or --key=value
     */
    private function parseOption(string $option): void
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

        if ($key === '') {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('不正：キー名が空です。'); // Empty key scenario is difficult to create through normal parsing flow

            // @codeCoverageIgnoreEnd
        }

        // Convert hyphens to underscores for consistency with PHP array keys
        $key = str_replace('-', '_', $key);

        // Store value in appropriate typed array
        match ($type) {
            'str' => $this->stringParams[$key] = $value,
            'int' => $this->intParams[$key] = $this->convertInt($value, $key),
            'float' => $this->floatParams[$key] = $this->convertFloat($value, $key),
            'bool' => $this->boolParams[$key] = $this->convertBool($value, $key),
            'json' => $this->jsonParams[$key] = $this->convertJson($value, $key),
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

    /**
     * Convert JSON string to list of strings
     *
     * @return list<string>
     */
    private function convertJson(string $value, string $key): array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(
                "不正：--{$key}:json の値は有効なJSONではありません。入力: '{$value}'",
            );
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(
                "不正：--{$key}:json の値は配列である必要があります。入力: '{$value}'",
            );
        }

        // Ensure all values are strings
        $result = [];
        foreach ($decoded as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException(
                    "不正：--{$key}:json の配列要素は文字列である必要があります。",
                );
            }

            $result[] = $item;
        }

        return $result;
    }
}
