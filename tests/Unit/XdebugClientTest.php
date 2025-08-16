<?php

namespace XdebugMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use XdebugMcp\XdebugClient;

class XdebugClientTest extends TestCase
{
    private XdebugClient $client;

    protected function setUp(): void
    {
        $this->client = new XdebugClient('127.0.0.1', 9003);
    }

    public function testConstructor(): void
    {
        $client = new XdebugClient('localhost', 9000);
        
        $this->assertInstanceOf(XdebugClient::class, $client);
    }

    public function testConstructorWithDefaults(): void
    {
        $client = new XdebugClient();
        
        $this->assertInstanceOf(XdebugClient::class, $client);
    }

    public function testEnsureConnectedThrowsWhenNotConnected(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not connected to Xdebug session');
        
        $this->invokePrivateMethod($this->client, 'ensureConnected');
    }

    public function testParseXmlWithValidXml(): void
    {
        $xmlString = '<response command="init" transaction_id="1"><attr>value</attr></response>';
        
        $result = $this->invokePrivateMethod($this->client, 'parseXml', [$xmlString]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('@attributes', $result);
        $this->assertEquals('init', $result['@attributes']['command']);
        $this->assertEquals('1', $result['@attributes']['transaction_id']);
        $this->assertArrayHasKey('attr', $result);
    }

    public function testParseXmlWithInvalidXml(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to parse XML response');
        
        $this->invokePrivateMethod($this->client, 'parseXml', ['<invalid xml']);
    }

    public function testXmlToArrayWithAttributes(): void
    {
        $xml = simplexml_load_string('<test attr1="value1" attr2="value2">content</test>');
        
        $result = $this->invokePrivateMethod($this->client, 'xmlToArray', [$xml]);
        
        $this->assertArrayHasKey('@attributes', $result);
        $this->assertEquals('value1', $result['@attributes']['attr1']);
        $this->assertEquals('value2', $result['@attributes']['attr2']);
        $this->assertArrayHasKey('#text', $result);
        $this->assertEquals('content', $result['#text']);
    }

    public function testXmlToArrayWithChildren(): void
    {
        $xml = simplexml_load_string('
            <parent>
                <child1>value1</child1>
                <child2 attr="test">value2</child2>
            </parent>
        ');
        
        $result = $this->invokePrivateMethod($this->client, 'xmlToArray', [$xml]);
        
        $this->assertArrayHasKey('child1', $result);
        $this->assertArrayHasKey('child2', $result);
        $this->assertEquals(['#text' => 'value1'], $result['child1']);
        $this->assertEquals([
            '@attributes' => ['attr' => 'test'],
            '#text' => 'value2'
        ], $result['child2']);
    }

    public function testXmlToArrayWithMultipleSameChildren(): void
    {
        $xml = simplexml_load_string('
            <parent>
                <item>first</item>
                <item>second</item>
                <item>third</item>
            </parent>
        ');
        
        $result = $this->invokePrivateMethod($this->client, 'xmlToArray', [$xml]);
        
        $this->assertArrayHasKey('item', $result);
        $this->assertIsArray($result['item']);
        $this->assertCount(3, $result['item']);
        $this->assertEquals(['#text' => 'first'], $result['item'][0]);
        $this->assertEquals(['#text' => 'second'], $result['item'][1]);
        $this->assertEquals(['#text' => 'third'], $result['item'][2]);
    }

    public function testGetProfileInfoWithoutXdebug(): void
    {
        $result = $this->client->getProfileInfo();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('profiler_status', $result);
        $this->assertEquals('active', $result['profiler_status']);
        $this->assertArrayHasKey('output_dir', $result);
        $this->assertArrayHasKey('output_name', $result);
    }

    public function testStartCoverageWithDefaults(): void
    {
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension not loaded');
        }

        $result = $this->client->startCoverage();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('coverage_started', $result['status']);
        $this->assertArrayHasKey('flags', $result);
    }

    public function testStartCoverageWithTrackUnusedFalse(): void
    {
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug extension not loaded');
        }

        $result = $this->client->startCoverage(['track_unused' => false]);
        
        $this->assertIsArray($result);
        $this->assertEquals('coverage_started', $result['status']);
        $this->assertEquals(0, $result['flags']);
    }

    public function testStopCoverage(): void
    {
        $result = $this->client->stopCoverage();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('coverage_stopped', $result['status']);
    }

    public function testGetCoverageWithoutXdebug(): void
    {
        if (extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug is loaded, cannot test without Xdebug scenario');
        }

        $result = $this->client->getCoverage();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetCoverageInfo(): void
    {
        $result = $this->client->getCoverageInfo();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('coverage_enabled', $result);
        $this->assertArrayHasKey('xdebug_version', $result);
        $this->assertArrayHasKey('coverage_mode', $result);
        
        $this->assertIsBool($result['coverage_enabled']);
        
        if (extension_loaded('xdebug')) {
            $this->assertTrue($result['coverage_enabled']);
            $this->assertNotEmpty($result['xdebug_version']);
        } else {
            $this->assertFalse($result['coverage_enabled']);
            $this->assertFalse($result['xdebug_version']);
        }
    }

    public function testStartProfilingThrowsWhenNotConnected(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not connected to Xdebug session');
        
        $this->client->startProfiling('/tmp/profile.out');
    }

    public function testStopProfilingThrowsWhenNotConnected(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not connected to Xdebug session');
        
        $this->client->stopProfiling();
    }

    private function invokePrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}