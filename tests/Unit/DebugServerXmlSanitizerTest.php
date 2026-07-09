<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\Dbgp\DbgpXml;
use PHPUnit\Framework\TestCase;

use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function simplexml_load_string;

class DebugServerXmlSanitizerTest extends TestCase
{
    public function testStripsNullByteFromAnonymousClassname(): void
    {
        $payload = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . '<response classname="Ray\\MediaQuery\\PagesInterface@anonymous'
            . "\x00"
            . '/path/to/file.php:42"/>';

        $clean = DbgpXml::sanitize($payload);

        $this->assertStringNotContainsString("\x00", $clean);

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
        $this->assertSame($input, DbgpXml::sanitize($input));
    }

    public function testPreservesUtf8MultibyteSequences(): void
    {
        $input = 'こんにちは';
        $this->assertSame($input, DbgpXml::sanitize($input));
    }

    public function testStripsAdditionalC0Controls(): void
    {
        $input = "begin\x01\x05\x0B\x0C\x0E\x1F\x7Fend";
        $this->assertSame('beginend', DbgpXml::sanitize($input));
    }

    public function testStripsInvalidNumericCharacterReferences(): void
    {
        $payload = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . '<response classname="Ray\\MediaQuery\\PagesInterface@anonymous'
            . '&#0;&#x0;&#x0B;'
            . '/path/to/file.php:42" valid="&#9;&#10;&#13;&#x20;"/>';

        $clean = DbgpXml::sanitize($payload);

        $this->assertStringNotContainsString('&#0;', $clean);
        $this->assertStringNotContainsString('&#x0;', $clean);
        $this->assertStringNotContainsString('&#x0B;', $clean);
        $this->assertStringContainsString('&#9;&#10;&#13;&#x20;', $clean);

        $useErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($clean);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('@anonymous', (string) $xml['classname']);
    }

    public function testStripsInvalidXmlCodepointReferences(): void
    {
        $payload = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . '<response invalid="&#11;&#xD800;&#xDFFF;&#xFFFE;&#xFFFF;&#x110000;" valid="&#x10000;"/>';

        $clean = DbgpXml::sanitize($payload);

        $this->assertStringNotContainsString('&#11;', $clean);
        $this->assertStringNotContainsString('&#xD800;', $clean);
        $this->assertStringNotContainsString('&#xDFFF;', $clean);
        $this->assertStringNotContainsString('&#xFFFE;', $clean);
        $this->assertStringNotContainsString('&#xFFFF;', $clean);
        $this->assertStringNotContainsString('&#x110000;', $clean);
        $this->assertStringContainsString('&#x10000;', $clean);

        $useErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($clean);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        $this->assertNotFalse($xml);
    }

    public function testStripsOverlongNumericCharacterReferences(): void
    {
        $input = 'begin&#xFFFFFFFFFFFF;&#99999999;end';
        $this->assertSame('beginend', DbgpXml::sanitize($input));
    }

    public function testIsXmlCharacterAcceptsValidCodepoints(): void
    {
        $this->assertTrue(DbgpXml::isXmlCharacter(0x09));
        $this->assertTrue(DbgpXml::isXmlCharacter(0x0A));
        $this->assertTrue(DbgpXml::isXmlCharacter(0x0D));
        $this->assertTrue(DbgpXml::isXmlCharacter(0x20));
        $this->assertTrue(DbgpXml::isXmlCharacter(0x10000));
        $this->assertFalse(DbgpXml::isXmlCharacter(0x00));
        $this->assertFalse(DbgpXml::isXmlCharacter(0x0B));
        $this->assertFalse(DbgpXml::isXmlCharacter(0xD800));
        $this->assertFalse(DbgpXml::isXmlCharacter(0x110000));
    }
}
