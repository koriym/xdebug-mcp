<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\DebugServer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function simplexml_load_string;
use function strpos;

class DebugServerXmlSanitizerTest extends TestCase
{
    private ReflectionMethod $sanitize;

    protected function setUp(): void
    {
        $this->sanitize = new ReflectionMethod(DebugServer::class, 'sanitizeDbgpXml');
        $this->sanitize->setAccessible(true);
    }

    public function testStripsNullByteFromAnonymousClassname(): void
    {
        $payload = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . '<response classname="Ray\\MediaQuery\\PagesInterface@anonymous'
            . "\x00"
            . '/path/to/file.php:42"/>';

        $clean = $this->sanitize->invoke(null, $payload);

        $this->assertFalse(strpos($clean, "\x00"));

        $useErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($clean);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('@anonymous', (string) $xml['classname']);
    }

    public function testPreservesTabNewlineAndCarriageReturn(): void
    {
        $input = "a\tb\nc\rd";
        $this->assertSame($input, $this->sanitize->invoke(null, $input));
    }

    public function testPreservesUtf8MultibyteSequences(): void
    {
        $input = 'こんにちは';
        $this->assertSame($input, $this->sanitize->invoke(null, $input));
    }

    public function testStripsAdditionalC0Controls(): void
    {
        $input = "begin\x01\x05\x0B\x0C\x0E\x1F\x7Fend";
        $this->assertSame('beginend', $this->sanitize->invoke(null, $input));
    }
}
