<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit\Utilities;

use Koriym\XdebugMcp\Utilities\PhpCommandParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PhpCommandParser::class)]
final class PhpCommandParserTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function providePhpBinaryPaths(): array
    {
        return [
            'simple php' => ['php', true],
            'php with major version' => ['php8.3', true],
            'php with full version' => ['php8.3.12', true],
            'php with dash version' => ['php-8.3', true],
            'php with at version' => ['php@8.3', true],
            'absolute path php' => ['/usr/bin/php', true],
            'homebrew php path' => ['/opt/homebrew/opt/php@8.3/bin/php', true],
            'windows php.exe' => ['C:\\php\\php.exe', true],
            'phpunit' => ['phpunit', false],
            'php-fpm' => ['php-fpm', false],
            'php-cgi' => ['php-cgi', false],
            'php script file' => ['script.php', false],
        ];
    }

    #[DataProvider('providePhpBinaryPaths')]
    #[Test]
    public function isPhpBinaryDetectsPhpInterpreters(string $path, bool $expected): void
    {
        $this->assertSame($expected, PhpCommandParser::isPhpBinary($path));
    }

    /** @return array<string, array{string, bool}> */
    public static function providePhpUnitCommands(): array
    {
        return [
            'phpunit' => ['phpunit', true],
            'phpunit.phar' => ['phpunit.phar', true],
            'vendor phpunit' => ['vendor/bin/phpunit', true],
            'phpunit word' => ['phpunit-custom', false],
            'php' => ['php', false],
        ];
    }

    #[DataProvider('providePhpUnitCommands')]
    #[Test]
    public function isPhpUnitCommandDetectsPhpunit(string $path, bool $expected): void
    {
        $this->assertSame($expected, PhpCommandParser::isPhpUnitCommand($path));
    }

    #[Test]
    public function findLocalFileArgumentReturnsFirstNonOptionToken(): void
    {
        $this->assertSame('script.php', PhpCommandParser::findLocalFileArgument(['script.php']));
        $this->assertSame('script.php', PhpCommandParser::findLocalFileArgument(['-d', 'memory_limit=1G', 'script.php']));
        $this->assertSame('script.php', PhpCommandParser::findLocalFileArgument(['--define', 'memory_limit=1G', 'script.php']));
    }

    #[Test]
    public function findLocalFileArgumentSkipsInlineCode(): void
    {
        $this->assertNull(PhpCommandParser::findLocalFileArgument(['-r', 'echo 1;']));
        $this->assertNull(PhpCommandParser::findLocalFileArgument(['--run', 'echo 1;']));
        $this->assertNull(PhpCommandParser::findLocalFileArgument(['-recho 1;']));
        $this->assertNull(PhpCommandParser::findLocalFileArgument(['--run=echo 1;']));
    }

    #[Test]
    public function findLocalFileArgumentThrowsWhenInlineCodeLacksArgument(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Code argument is required after -r');

        PhpCommandParser::findLocalFileArgument(['-r']);
    }

    #[Test]
    public function findLocalFileArgumentRespectsOptionEndMarker(): void
    {
        $this->assertSame('script.php', PhpCommandParser::findLocalFileArgument(['--', 'script.php', '-r']));
    }

    #[Test]
    public function findScriptIndexSkipsOptionsThatTakeValues(): void
    {
        $this->assertSame(1, PhpCommandParser::findScriptIndex(['php', 'script.php'], 0));
        $this->assertSame(3, PhpCommandParser::findScriptIndex(['php', '-d', 'memory_limit=1G', 'script.php'], 0));
        $this->assertSame(3, PhpCommandParser::findScriptIndex(['php', '--define', 'memory_limit=1G', 'script.php'], 0));
    }

    #[Test]
    public function findScriptIndexReturnsFalseWhenNoScript(): void
    {
        $this->assertFalse(PhpCommandParser::findScriptIndex(['php', '-d', 'memory_limit=1G'], 0));
    }

    /** @return array<string, array{string, bool}> */
    public static function provideInlineCodeScripts(): array
    {
        return [
            'inline run' => ['php -r "echo 1;"', true],
            'inline run long' => ['php --run "echo 1;"', true],
            'attached run' => ['php -recho 1;', true],
            'attached run long' => ['php --run=echo 1;', true],
            'script file' => ['php script.php -r dry-run', false],
            'with define' => ['php -d memory_limit=1G -r "echo 1;"', true],
            'not php' => ['python -c "print(1)"', false],
        ];
    }

    #[DataProvider('provideInlineCodeScripts')]
    #[Test]
    public function isPhpInlineCodeScriptDetectsInlineCode(string $script, bool $expected): void
    {
        $this->assertSame($expected, PhpCommandParser::isPhpInlineCodeScript($script));
    }

    #[Test]
    public function findPhpRunCodeArgumentReturnsCodeIndex(): void
    {
        $result = PhpCommandParser::findPhpRunCodeArgument(['-r', 'echo 1;']);
        $this->assertSame(['code_index' => 1, 'code_prefix' => '', 'option' => '-r'], $result);
    }

    #[Test]
    public function findPhpRunCodeArgumentStopsAtScriptBoundary(): void
    {
        $this->assertNull(PhpCommandParser::findPhpRunCodeArgument(['script.php', '-r', 'dry-run']));
        $this->assertNull(PhpCommandParser::findPhpRunCodeArgument(['--', '-r', 'echo 1;']));
    }

    #[Test]
    public function normalizePhpUnitArgumentsStripsPhpBinaryAndPhpunit(): void
    {
        $this->assertSame(['--filter', 'MyTest'], PhpCommandParser::normalizePhpUnitArguments(['php', 'phpunit', '--filter', 'MyTest']));
        $this->assertSame(['--filter', 'MyTest'], PhpCommandParser::normalizePhpUnitArguments(['phpunit', '--filter', 'MyTest']));
        $this->assertSame(['--filter', 'MyTest'], PhpCommandParser::normalizePhpUnitArguments(['--filter', 'MyTest']));
    }

    #[Test]
    public function normalizePhpUnitArgumentsRemovesNoCoverage(): void
    {
        $this->assertSame(['--filter', 'MyTest'], PhpCommandParser::normalizePhpUnitArguments(['phpunit', '--no-coverage', '--filter', 'MyTest']));
    }

    #[Test]
    public function normalizeRawArgumentsStripsLeadingPhpBinary(): void
    {
        $this->assertSame(['script.php'], PhpCommandParser::normalizeRawArguments(['php', 'script.php']));
        $this->assertSame(['script.php'], PhpCommandParser::normalizeRawArguments(['script.php']));
    }
}
