<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\CLIParamsNormalizer;
use Koriym\XdebugMcp\DTO\CliParams;
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
        $this->assertInstanceOf(CliParams::class, $result);
        $this->assertSame('John', $result->getString('name'));
    }

    public function testDefaultStringType(): void
    {
        $result = $this->normalizer->normalize('--name=John');
        $this->assertSame('John', $result->getString('name'));
    }

    public function testIntegerParameter(): void
    {
        $result = $this->normalizer->normalize('--count:int=42');
        $this->assertSame(42, $result->getInt('count'));
    }

    public function testFloatParameter(): void
    {
        $result = $this->normalizer->normalize('--rate:float=0.95');
        $this->assertSame(0.95, $result->getFloat('rate'));
    }

    public function testBooleanParameters(): void
    {
        $result = $this->normalizer->normalize('--enabled:bool=true --disabled:bool=false');
        $this->assertTrue($result->getBool('enabled'));
        $this->assertFalse($result->getBool('disabled'));
    }

    public function testJsonParameter(): void
    {
        $result = $this->normalizer->normalize('--tags:json=\'["a","b","c"]\'');
        $this->assertSame(['a', 'b', 'c'], $result->getJson('tags'));
    }

    public function testQuotedValues(): void
    {
        $result = $this->normalizer->normalize('--message:str="Hello World" --path:str=\'/tmp/test file\'');
        $this->assertSame('Hello World', $result->getString('message'));
        $this->assertSame('/tmp/test file', $result->getString('path'));
    }

    public function testPositionalArgs(): void
    {
        $result = $this->normalizer->normalize('--json:bool=true -- php tests/fake/loop-counter.php --arg1=value');
        $this->assertTrue($result->getBool('json'));
        $this->assertSame(['php', 'tests/fake/loop-counter.php', '--arg1=value'], $result->positionalArgs);
    }

    public function testComplexExample(): void
    {
        $cli = '--json:bool=true --count:int=3 --name:str="Test User" --tags:json=\'["php","debug"]\' -- php script.php arg1 arg2';
        $result = $this->normalizer->normalize($cli);

        $this->assertTrue($result->getBool('json'));
        $this->assertSame(3, $result->getInt('count'));
        $this->assertSame('Test User', $result->getString('name'));
        $this->assertSame(['php', 'debug'], $result->getJson('tags'));
        $this->assertSame(['php', 'script.php', 'arg1', 'arg2'], $result->positionalArgs);
    }

    public function testXdebugTraceExample(): void
    {
        $result = $this->normalizer->normalize('--json:bool=true -- php tests/fake/loop-counter.php');
        $this->assertTrue($result->getBool('json'));
        $this->assertSame(['php', 'tests/fake/loop-counter.php'], $result->positionalArgs);
    }

    public function testXdebugDebugExample(): void
    {
        $cli = '--break:str=file.php:42 --exit-on-break:bool=true --context:str="Debug session" -- php script.php';
        $result = $this->normalizer->normalize($cli);

        $this->assertSame('file.php:42', $result->getString('break'));
        $this->assertTrue($result->getBool('exit_on_break'));
        $this->assertSame('Debug session', $result->getString('context'));
        $this->assertSame(['php', 'script.php'], $result->positionalArgs);
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
        $this->assertTrue($result->getBool('flag'));
        $this->assertSame(['php', 'script.php', '0'], $result->positionalArgs);
        $this->assertContains('0', $result->positionalArgs, 'The string "0" should be preserved as a positional argument');
    }

    public function testHasMethod(): void
    {
        $result = $this->normalizer->normalize('--name:str=test --count:int=5');
        $this->assertTrue($result->has('name'));
        $this->assertTrue($result->has('count'));
        $this->assertFalse($result->has('missing'));
    }

    public function testToStringArray(): void
    {
        $result = $this->normalizer->normalize('--name:str=test --count:int=5 --rate:float=1.5 --enabled:bool=true --tags:json=\'["a","b"]\'');
        $stringArray = $result->toStringArray();

        $this->assertSame('test', $stringArray['name']);
        $this->assertSame('5', $stringArray['count']);
        $this->assertSame('1.5', $stringArray['rate']);
        $this->assertSame('true', $stringArray['enabled']);
        $this->assertSame('["a","b"]', $stringArray['tags']);
    }

    public function testDefaultValues(): void
    {
        $result = $this->normalizer->normalize('--name:str=test');

        $this->assertSame('', $result->getString('missing'));
        $this->assertSame('default', $result->getString('missing', 'default'));
        $this->assertSame(0, $result->getInt('missing'));
        $this->assertSame(10, $result->getInt('missing', 10));
        $this->assertSame(0.0, $result->getFloat('missing'));
        $this->assertSame(1.5, $result->getFloat('missing', 1.5));
        $this->assertFalse($result->getBool('missing'));
        $this->assertTrue($result->getBool('missing', true));
        $this->assertSame([], $result->getJson('missing'));
    }

    public function testJsonNonArrayError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('配列である必要があります');
        // Use valid JSON that's not an array (a number)
        $this->normalizer->normalize('--data:json=42');
    }

    public function testJsonNonStringElementsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('配列要素は文字列である必要があります');
        $this->normalizer->normalize('--data:json=\'[1, 2, 3]\'');
    }
}
