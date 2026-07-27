<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\XdebugTracer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function getenv;
use function putenv;

#[CoversClass(XdebugTracer::class)]
final class XdebugTracerTest extends TestCase
{
    private XdebugTracer $tracer;

    /** @var array<string, string|false> */
    private array $originalLocaleEnv = [];

    protected function setUp(): void
    {
        $this->tracer = new XdebugTracer();
        foreach (['LC_ALL', 'LANG', 'LC_MESSAGES'] as $var) {
            $this->originalLocaleEnv[$var] = getenv($var);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalLocaleEnv as $var => $value) {
            if ($value === false) {
                putenv($var);
                continue;
            }

            putenv("{$var}={$value}");
        }
    }

    /** @return array<string, array{string, string}> */
    public static function provideJapaneseEnvironments(): array
    {
        return [
            'LC_ALL ja_JP.UTF-8' => ['LC_ALL', 'ja_JP.UTF-8'],
            'LANG ja-JP' => ['LANG', 'ja-JP'],
            'LC_MESSAGES Japanese' => ['LC_MESSAGES', 'Japanese'],
            'LC_ALL contains 日本語' => ['LC_ALL', 'x日本語x'],
        ];
    }

    #[DataProvider('provideJapaneseEnvironments')]
    #[Test]
    public function detectLanguageReturnsJapaneseForJapaneseEnvironment(string $var, string $value): void
    {
        $this->clearLocaleEnv();
        putenv("{$var}={$value}");

        $detect = new ReflectionMethod($this->tracer, 'detectLanguage');

        $this->assertSame('Japanese', $detect->invoke($this->tracer));
    }

    #[Test]
    public function detectLanguageReturnsEnglishForNonJapaneseEnvironment(): void
    {
        $this->clearLocaleEnv();
        putenv('LC_ALL=en_US.UTF-8');

        $detect = new ReflectionMethod($this->tracer, 'detectLanguage');

        $this->assertSame('English', $detect->invoke($this->tracer));
    }

    #[Test]
    public function detectLanguagePrioritizesLcAllOverLang(): void
    {
        $this->clearLocaleEnv();
        putenv('LC_ALL=ja_JP.UTF-8');
        putenv('LANG=en_US.UTF-8');

        $detect = new ReflectionMethod($this->tracer, 'detectLanguage');

        $this->assertSame('Japanese', $detect->invoke($this->tracer));
    }

    #[Test]
    public function detectLanguagePrioritizesLcMessagesOverLang(): void
    {
        $this->clearLocaleEnv();
        putenv('LC_ALL');
        putenv('LC_MESSAGES=ja_JP.UTF-8');
        putenv('LANG=en_US.UTF-8');

        $detect = new ReflectionMethod($this->tracer, 'detectLanguage');

        $this->assertSame('Japanese', $detect->invoke($this->tracer));
    }

    #[Test]
    public function parsePrimaryAppleLanguageUsesFirstEntryOnly(): void
    {
        $method = new ReflectionMethod($this->tracer, 'parsePrimaryAppleLanguage');

        $this->assertSame('ja', $method->invoke($this->tracer, "(\n    ja,\n    en\n)"));
        $this->assertSame('en', $method->invoke($this->tracer, "(\n    en,\n    ja\n)"));
        $this->assertSame('ja', $method->invoke($this->tracer, 'ja,en'));
        $this->assertSame('', $method->invoke($this->tracer, ''));
    }

    /** @return array<string, array{string, bool}> */
    public static function provideLanguageOutputs(): array
    {
        return [
            'AppleLanguages ja' => ["(\n    ja,\n    en\n)", true],
            'English only' => ['en_US.UTF-8', false],
            'hiragana 日本語' => ['日本語', true],
            'mixed upper JA' => ['JA_JP', true],
        ];
    }

    #[DataProvider('provideLanguageOutputs')]
    #[Test]
    public function isJapaneseDetectsJapaneseContent(string $output, bool $expected): void
    {
        $method = new ReflectionMethod($this->tracer, 'isJapanese');

        $this->assertSame($expected, $method->invoke($this->tracer, $output));
    }

    private function clearLocaleEnv(): void
    {
        putenv('LC_ALL');
        putenv('LANG');
        putenv('LC_MESSAGES');
    }
}
