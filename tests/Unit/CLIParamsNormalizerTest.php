<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\CLIParamsNormalizer;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CLIParamsNormalizerTest extends TestCase
{
    private CLIParamsNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new CLIParamsNormalizer();
    }

    public function testBasicStringParameter(): void
    {
        $result = $this->normalizer->normalize('--name:str=John');
        $this->assertEquals(['name' => 'John'], $result);
    }

    public function testDefaultStringType(): void
    {
        $result = $this->normalizer->normalize('--name=John');
        $this->assertEquals(['name' => 'John'], $result);
    }

    public function testIntegerParameter(): void
    {
        $result = $this->normalizer->normalize('--count:int=42');
        $this->assertEquals(['count' => 42], $result);
    }

    public function testFloatParameter(): void
    {
        $result = $this->normalizer->normalize('--rate:float=0.95');
        $this->assertEquals(['rate' => 0.95], $result);
    }

    public function testBooleanParameters(): void
    {
        $result = $this->normalizer->normalize('--enabled:bool=true --disabled:bool=false');
        $this->assertEquals(['enabled' => true, 'disabled' => false], $result);
    }

    public function testJsonParameter(): void
    {
        $result = $this->normalizer->normalize('--tags:json=\'["a","b","c"]\'');
        $this->assertEquals(['tags' => ['a', 'b', 'c']], $result);
    }

    public function testQuotedValues(): void
    {
        $result = $this->normalizer->normalize('--message:str="Hello World" --path:str=\'/tmp/test file\'');
        $this->assertEquals(['message' => 'Hello World', 'path' => '/tmp/test file'], $result);
    }

    public function testPositionalArgs(): void
    {
        $result = $this->normalizer->normalize('--json:bool=true -- php tests/fake/loop-counter.php --arg1=value');
        $expected = [
            'json' => true,
            'args' => ['php', 'tests/fake/loop-counter.php', '--arg1=value'],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testArrayValues(): void
    {
        $result = $this->normalizer->normalize('--tag:str=a --tag:str=b');
        $this->assertEquals(['tag' => ['a', 'b']], $result);
    }

    public function testComplexExample(): void
    {
        $cli = '--json:bool=true --count:int=3 --name:str="Test User" --tags:json=\'["php","debug"]\' -- php script.php arg1 arg2';
        $result = $this->normalizer->normalize($cli);

        $expected = [
            'json' => true,
            'count' => 3,
            'name' => 'Test User',
            'tags' => ['php', 'debug'],
            'args' => ['php', 'script.php', 'arg1', 'arg2'],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testXdebugTraceExample(): void
    {
        $result = $this->normalizer->normalize('--json:bool=true -- php tests/fake/loop-counter.php');
        $expected = [
            'json' => true,
            'args' => ['php', 'tests/fake/loop-counter.php'],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testXdebugDebugExample(): void
    {
        $cli = '--break:str=file.php:42 --exit-on-break:bool=true --context:str="Debug session" -- php script.php';
        $result = $this->normalizer->normalize($cli);

        $expected = [
            'break' => 'file.php:42',
            'exit_on_break' => true,
            'context' => 'Debug session',
            'args' => ['php', 'script.php'],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testErrorMissingEquals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('不正：= が必要です');
        $this->normalizer->normalize('--name John');
    }

    public function testErrorInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('不正：型 \'invalid\' は許可されていません');
        $this->normalizer->normalize('--count:invalid=123');
    }

    public function testErrorInvalidInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('は整数ではありません');
        $this->normalizer->normalize('--count:int=abc');
    }

    public function testErrorInvalidFloat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('は数値ではありません');
        $this->normalizer->normalize('--rate:float=not_a_number');
    }

    public function testErrorInvalidBoolean(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('\'true\' または \'false\'');
        $this->normalizer->normalize('--enabled:bool=yes');
    }

    public function testErrorInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('有効なJSONではありません');
        $this->normalizer->normalize('--data:json={invalid json}');
    }

    public function testErrorPositionalArgsBeforeDashes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('位置引数は -- 後のみ許可');
        $this->normalizer->normalize('--name:str=test positional');
    }

    public function testPositionalZeroStringPreservation(): void
    {
        $result = $this->normalizer->normalize('--flag:bool=true -- php script.php 0');
        $expected = [
            'flag' => true,
            'args' => ['php', 'script.php', '0'],
        ];
        $this->assertEquals($expected, $result);
        $this->assertContains('0', $result['args'], 'The string "0" should be preserved as a positional argument');
    }
}
