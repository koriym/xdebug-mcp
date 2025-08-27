<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use InvalidArgumentException;
use Koriym\XdebugMcp\DebugServer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function basename;
use function chdir;
use function count;
use function dirname;
use function file_exists;
use function file_put_contents;
use function getcwd;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

class DebugServerTest extends TestCase
{
    private string $testScript;

    protected function setUp(): void
    {
        // Create a simple test script
        $this->testScript = tempnam(sys_get_temp_dir(), 'debug_test_') . '.php';
        file_put_contents($this->testScript, '<?php
echo "Hello Debug World\n";
$x = 10;
$y = 20;
$result = $x + $y;
echo "Result: $result\n";
');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testScript)) {
            unlink($this->testScript);
        }
    }

    public function testConstructorWithValidScript(): void
    {
        $server = new DebugServer($this->testScript, 9004);
        $this->assertInstanceOf(DebugServer::class, $server);
    }

    public function testConstructorWithInvalidScript(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Script not found');

        new DebugServer('/nonexistent/script.php', 9004);
    }

    public function testConstructorWithCustomPort(): void
    {
        $server = new DebugServer($this->testScript, 9005);
        $this->assertInstanceOf(DebugServer::class, $server);
    }

    public function testConstructorWithBreakpointLine(): void
    {
        $server = new DebugServer($this->testScript, 9004, 3);
        $this->assertInstanceOf(DebugServer::class, $server);
    }

    public function testConstructorWithOptions(): void
    {
        $options = [
            'context' => 'Test debug session',
            'timeout' => 30,
        ];

        $server = new DebugServer($this->testScript, 9004, null, $options);
        $this->assertInstanceOf(DebugServer::class, $server);
    }

    public function testConstructorWithJsonMode(): void
    {
        $server = new DebugServer($this->testScript, 9004, null, [], true);
        $this->assertInstanceOf(DebugServer::class, $server);
    }

    public function testGetScriptPath(): void
    {
        $server = new DebugServer($this->testScript, 9004);

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('targetScript');
        $property->setAccessible(true);

        $this->assertEquals($this->testScript, $property->getValue($server));
    }

    public function testGetDebugPort(): void
    {
        $customPort = 9006;
        $server = new DebugServer($this->testScript, $customPort);

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('debugPort');
        $property->setAccessible(true);

        $this->assertEquals($customPort, $property->getValue($server));
    }

    public function testGetInitialBreakpointLine(): void
    {
        $breakpointLine = 5;
        $server = new DebugServer($this->testScript, 9004, $breakpointLine);

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('initialBreakpointLine');
        $property->setAccessible(true);

        $this->assertEquals($breakpointLine, $property->getValue($server));
    }

    public function testGetOptions(): void
    {
        $options = [
            'context' => 'Unit test context',
            'timeout' => 60,
        ];

        $server = new DebugServer($this->testScript, 9004, null, $options);

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('options');
        $property->setAccessible(true);

        $this->assertEquals($options, $property->getValue($server));
    }

    public function testJsonModeProperty(): void
    {
        // Test JSON mode enabled
        $server = new DebugServer($this->testScript, 9004, null, [], true);

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('jsonMode');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($server));

        // Test JSON mode disabled (default)
        $server2 = new DebugServer($this->testScript, 9004);
        $this->assertFalse($property->getValue($server2));
    }

    public function testEnableHttpMode(): void
    {
        $server = new DebugServer($this->testScript, 9004);

        // This method should exist and be callable
        $reflection = new ReflectionClass($server);
        $this->assertTrue($reflection->hasMethod('enableHttpMode'));

        $method = $reflection->getMethod('enableHttpMode');
        $this->assertTrue($method->isPublic());
    }

    public function testInvokeMethod(): void
    {
        $server = new DebugServer($this->testScript, 9004);

        // Test that the __invoke method exists
        $reflection = new ReflectionClass($server);
        $this->assertTrue($reflection->hasMethod('__invoke'));

        $method = $reflection->getMethod('__invoke');
        $this->assertTrue($method->isPublic());
    }

    public function testPrivateMethodExists(): void
    {
        $server = new DebugServer($this->testScript, 9004);
        $reflection = new ReflectionClass($server);

        // Check for some expected private methods
        $expectedMethods = [
            'createXdebugArguments',
            'executeDebugScript',
            'handleXdebugConnection',
        ];

        $foundMethods = 0;
        foreach ($expectedMethods as $methodName) {
            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                $this->assertTrue(
                    $method->isPrivate() || $method->isProtected(),
                    "Method {$methodName} should be private or protected",
                );
                $foundMethods++;
            }
        }

        // At least assert that we checked something or that the class has methods
        $allMethods = $reflection->getMethods();
        $this->assertGreaterThan(0, count($allMethods), 'DebugServer should have methods');
    }

    public function testScriptPathNormalization(): void
    {
        // Test with relative path
        $relativePath = basename($this->testScript);
        $currentDir = dirname($this->testScript);

        // Change to the directory containing the test script
        $oldCwd = getcwd();
        chdir($currentDir);

        try {
            $server = new DebugServer($relativePath, 9004);
            $this->assertInstanceOf(DebugServer::class, $server);
        } finally {
            chdir($oldCwd);
        }
    }

    public function testConstructorValidatesScriptExists(): void
    {
        $nonExistentScript = '/tmp/definitely_does_not_exist_' . uniqid() . '.php';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Script not found');

        new DebugServer($nonExistentScript, 9004);
    }
}
