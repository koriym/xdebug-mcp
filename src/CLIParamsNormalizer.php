<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use JsonException;
use Koriym\XdebugMcp\DTO\CliParams;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;

use function array_is_list;
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

    /**
     * Normalize CLI string to CliParams DTO
     *
     * @throws InvalidArgumentException On invalid format.
     */
    public function normalize(string $cliString): CliParams
    {
        /** @var array<string, string> $stringParams */
        $stringParams = [];
        /** @var array<string, int> $intParams */
        $intParams = [];
        /** @var array<string, float> $floatParams */
        $floatParams = [];
        /** @var array<string, bool> $boolParams */
        $boolParams = [];
        /** @var array<string, list<string>> $jsonParams */
        $jsonParams = [];
        /** @var list<string> $positionalArgs */
        $positionalArgs = [];

        $tokens = $this->tokenize($cliString);
        $i = 0;

        while ($i < count($tokens) && $tokens[$i] !== '--') {
            if (! str_starts_with($tokens[$i], '--')) {
                throw new InvalidArgumentException(
                    '不正：位置引数は -- 後のみ許可。例：--key:str=value -- args',
                );
            }

            $option = substr($tokens[$i], 2);
            $this->parseOption($option, $stringParams, $intParams, $floatParams, $boolParams, $jsonParams);
            $i++;
        }

        if ($i < count($tokens) && $tokens[$i] === '--') {
            $i++;
            $positionalArgs = array_slice($tokens, $i);
        }

        return new CliParams(
            stringParams: $stringParams,
            intParams: $intParams,
            floatParams: $floatParams,
            boolParams: $boolParams,
            jsonParams: $jsonParams,
            positionalArgs: $positionalArgs,
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

                continue;
            }

            if ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = null;

                continue;
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
     * @param array<string, string>       $stringParams
     * @param array<string, int>          $intParams
     * @param array<string, float>        $floatParams
     * @param array<string, bool>         $boolParams
     * @param array<string, list<string>> $jsonParams
     */
    private function parseOption(
        string $option,
        array &$stringParams,
        array &$intParams,
        array &$floatParams,
        array &$boolParams,
        array &$jsonParams,
    ): void {
        if (! str_contains($option, '=')) {
            throw new InvalidArgumentException(
                "不正：= が必要です。--{$option}:str=value を使用してください。",
            );
        }

        [$keyPart, $value] = explode('=', $option, 2);

        $type = 'str';
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
            throw new InvalidArgumentException('不正：キー名が空です。');
        }

        $key = str_replace('-', '_', $key);

        match ($type) {
            'str' => $stringParams[$key] = $value,
            'int' => $intParams[$key] = $this->convertInt($value, $key),
            'float' => $floatParams[$key] = $this->convertFloat($value, $key),
            'bool' => $boolParams[$key] = $this->convertBool($value, $key),
            'json' => $jsonParams[$key] = $this->convertJson($value, $key),
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

    /** @return list<string> */
    private function convertJson(string $value, string $key): array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(
                "不正：--{$key}:json の値は有効なJSONではありません。入力: '{$value}'",
            );
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new InvalidArgumentException(
                "不正：--{$key}:json の値は配列である必要があります。",
            );
        }

        foreach ($decoded as $element) {
            if (is_string($element)) {
                continue;
            }

            throw new InvalidArgumentException(
                "不正：--{$key}:json の配列要素は文字列である必要があります。",
            );
        }

        /** @var list<string> $decoded */
        return $decoded;
    }
}
