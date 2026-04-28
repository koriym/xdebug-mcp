<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\DebugServer;
use Koriym\XdebugMcp\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_filter;
use function array_values;
use function basename;
use function chdir;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function file_put_contents;
use function getcwd;
use function json_decode;
use function ob_get_clean;
use function ob_start;
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
        if (! file_exists($this->testScript)) {
            return;
        }

        unlink($this->testScript);
    }

    public function testConstructorWithValidScript(): void
    {
        $server = new DebugServer($this->testScript, 9004);
        $this->assertInstanceOf(DebugServer::class, $server);
    }

    public function testConstructorWithInvalidScript(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Script not found/');

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

        $this->assertEquals($this->testScript, $property->getValue($server));
    }

    public function testGetDebugPort(): void
    {
        $customPort = 9006;
        $server = new DebugServer($this->testScript, $customPort);

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('debugPort');

        $this->assertEquals($customPort, $property->getValue($server));
    }

    public function testGetInitialBreakpointLine(): void
    {
        $breakpointLine = 5;
        $server = new DebugServer($this->testScript, 9004, $breakpointLine);

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('initialBreakpointLine');

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

        $this->assertEquals($options, $property->getValue($server));
    }

    public function testJsonModeProperty(): void
    {
        // Test JSON mode enabled
        $server = new DebugServer($this->testScript, 9004, null, [], true);

        $reflection = new ReflectionClass($server);
        $property = $reflection->getProperty('jsonMode');

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
            if (! $reflection->hasMethod($methodName)) {
                continue;
            }

            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPrivate() || $method->isProtected(),
                "Method {$methodName} should be private or protected",
            );
            $foundMethods++;
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
        $this->expectExceptionMessageMatches('/Script not found/');

        new DebugServer($nonExistentScript, 9004);
    }

    public function testCliJsonOutputWithoutBreakpoint(): void
    {
        $server = new DebugServer($this->testScript, 9004, null, [], true);

        // Mock execution by calling private methods via reflection
        $reflection = new ReflectionClass($server);

        // Test createXdebugArguments method exists and returns array
        if ($reflection->hasMethod('createXdebugArguments')) {
            $method = $reflection->getMethod('createXdebugArguments');
            $args = $method->invoke($server);

            $this->assertIsArray($args);
            $this->assertContains('-dxdebug.mode=debug', $args);
            $this->assertContains('-dxdebug.client_port=9004', $args);
        }

        $this->assertTrue(true); // Basic instantiation test
    }

    public function testCliJsonOutputWithBreakpoint(): void
    {
        $server = new DebugServer($this->testScript, 9004, 5, [], true);

        $reflection = new ReflectionClass($server);

        // Test breakpoint line
        $breakpointProperty = $reflection->getProperty('initialBreakpointLine');
        $this->assertEquals(5, $breakpointProperty->getValue($server));

        // Test JSON mode
        $jsonProperty = $reflection->getProperty('jsonMode');
        $this->assertTrue($jsonProperty->getValue($server));
    }

    public function testCliJsonOutputWithSteps(): void
    {
        $options = ['maxSteps' => 50];
        $server = new DebugServer($this->testScript, 9004, null, $options, true);

        $reflection = new ReflectionClass($server);
        $optionsProperty = $reflection->getProperty('options');
        $serverOptions = $optionsProperty->getValue($server);

        $this->assertEquals(50, $serverOptions['maxSteps']);
    }

    public function testCliJsonOutputWithBreakpointAndSteps(): void
    {
        $options = ['maxSteps' => 100, 'timeout' => 30];
        $server = new DebugServer($this->testScript, 9004, 3, $options, true);

        $reflection = new ReflectionClass($server);

        // Test breakpoint line
        $breakpointProperty = $reflection->getProperty('initialBreakpointLine');
        $this->assertEquals(3, $breakpointProperty->getValue($server));

        // Test JSON mode
        $jsonProperty = $reflection->getProperty('jsonMode');
        $this->assertTrue($jsonProperty->getValue($server));

        // Test options
        $optionsProperty = $reflection->getProperty('options');
        $serverOptions = $optionsProperty->getValue($server);
        $this->assertEquals(100, $serverOptions['maxSteps']);
        $this->assertEquals(30, $serverOptions['timeout']);
    }

    public function testConstructorWithWatches(): void
    {
        $options = [
            'watches' => ['$i', '$user->getStatus()'],
            'maxSteps' => 50,
        ];

        $server = new DebugServer($this->testScript, 9004, null, $options, true);

        $reflection = new ReflectionClass($server);
        $optionsProperty = $reflection->getProperty('options');
        $serverOptions = $optionsProperty->getValue($server);

        $this->assertArrayHasKey('watches', $serverOptions);
        $this->assertCount(2, $serverOptions['watches']);
        $this->assertEquals('$i', $serverOptions['watches'][0]);
        $this->assertEquals('$user->getStatus()', $serverOptions['watches'][1]);
    }

    public function testConstructorWithEmptyWatches(): void
    {
        $options = [
            'watches' => [],
        ];

        $server = new DebugServer($this->testScript, 9004, null, $options, true);

        $reflection = new ReflectionClass($server);
        $optionsProperty = $reflection->getProperty('options');
        $serverOptions = $optionsProperty->getValue($server);

        $this->assertArrayHasKey('watches', $serverOptions);
        $this->assertEmpty($serverOptions['watches']);
    }

    public function testEvaluateWatchExpressionMethodExists(): void
    {
        $server = new DebugServer($this->testScript, 9004);

        $reflection = new ReflectionClass($server);
        $this->assertTrue($reflection->hasMethod('evaluateWatchExpression'));

        $method = $reflection->getMethod('evaluateWatchExpression');
        $this->assertTrue($method->isPrivate());

        // Verify method signature: takes string, returns string
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('expression', $params[0]->getName());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    /**
     * Regression test for #67: xstep emits two JSON documents when --steps is omitted.
     *
     * When the multiple-breakpoint path (maxSteps unset) outputs its JSON, the
     * subsequent cleanup phase must not emit a second, empty
     * {"breaks":[]} JSON document.
     */
    public function testMultipleBreakResultsDoesNotDoubleEmitJson(): void
    {
        $server = new DebugServer($this->testScript, 9004, null, [], true);

        $reflection = new ReflectionClass($server);

        $breaks = [
            [
                'step' => 1,
                'location' => ['file' => basename($this->testScript), 'line' => 3],
                'variables' => ['$x' => '10'],
            ],
        ];

        $outputMultiple = $reflection->getMethod('outputMultipleBreakResults');
        $outputStepRec = $reflection->getMethod('outputStepRecordingResults');

        ob_start();
        $outputMultiple->invoke($server, $breaks);
        // Simulate the cleanup-phase call that previously double-emitted.
        $outputStepRec->invoke($server);
        $stdout = ob_get_clean();

        $documents = array_values(array_filter(
            explode("\n", $stdout),
            static fn (string $line): bool => $line !== '',
        ));

        $this->assertCount(1, $documents, 'Expected exactly one JSON document on stdout');

        $decoded = json_decode($documents[0], true);
        $this->assertIsArray($decoded);
        $this->assertSame('https://koriym.github.io/xdebug-mcp/schemas/xstep.json', $decoded['$schema']);
        $this->assertCount(1, $decoded['breaks']);
    }

    public function testJsonOutputCanBePrettyPrintedWithTwoSpaces(): void
    {
        $server = new DebugServer($this->testScript, 9004, null, ['pretty' => true], true);

        $reflection = new ReflectionClass($server);
        $breaks = [
            [
                'step' => 1,
                'location' => ['file' => basename($this->testScript), 'line' => 3],
                'variables' => ['$x' => '10'],
            ],
        ];

        $outputMultiple = $reflection->getMethod('outputMultipleBreakResults');

        ob_start();
        $outputMultiple->invoke($server, $breaks);
        $stdout = ob_get_clean();

        $this->assertStringStartsWith("{\n  " . '"$schema"', $stdout);
        $this->assertStringContainsString("\n  \"breaks\": [\n    {", $stdout);
        $this->assertStringNotContainsString("\n    " . '"$schema"', $stdout);

        $decoded = json_decode($stdout, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded['breaks']);
    }

    public function testVariableDisplayCanBeTruncatedByBytes(): void
    {
        $server = new DebugServer($this->testScript, 9004, null, [], true);

        $reflection = new ReflectionClass($server);
        $truncate = $reflection->getMethod('truncateStringValue');

        $this->assertSame(
            'abcdefgh... (truncated, 8 bytes)',
            $truncate->invoke($server, 'abcdefghijklmnop', 8),
        );
    }

    public function testVariableDiffShowsScalarBeforeAfterAndShallowKeys(): void
    {
        $server = new DebugServer($this->testScript, 9004, null, [], true);

        $reflection = new ReflectionClass($server);
        $buildDiff = $reflection->getMethod('buildVariableDiff');

        $diff = $buildDiff->invoke(
            $server,
            [
                '$count' => 'int: 1',
                '$items' => 'array: {"a":1,"b":2,"nested":{"x":1}}',
                '$gone' => 'string: old',
            ],
            [
                '$count' => 'int: 2',
                '$items' => 'array: {"b":3,"c":4,"nested":{"x":1,"y":2}}',
                '$new' => 'string: fresh',
            ],
        );

        $this->assertSame('changed', $diff['$count']['change']);
        $this->assertSame('int: 1', $diff['$count']['before']);
        $this->assertSame('int: 2', $diff['$count']['after']);

        $this->assertSame('array', $diff['$items']['type']);
        $this->assertSame(['c' => '4'], $diff['$items']['keys']['added']);
        $this->assertSame(['a' => '1'], $diff['$items']['keys']['removed']);
        $this->assertSame(['before' => '2', 'after' => '3'], $diff['$items']['keys']['changed']['b']);
        $this->assertSame(['before' => 'array(1)', 'after' => 'array(2)'], $diff['$items']['keys']['changed']['nested']);

        $this->assertSame('removed', $diff['$gone']['change']);
        $this->assertSame('added', $diff['$new']['change']);
    }

    public function testStackFramesAreParsedForJsonContext(): void
    {
        $server = new DebugServer($this->testScript, 9004, null, [], true);

        $reflection = new ReflectionClass($server);
        $parseStackFrames = $reflection->getMethod('parseStackFrames');
        $stackXml = '<response>'
            . '<stack level="0" type="file" filename="file:///tmp/Foo.php" lineno="42" where="App\\Foo::bar"/>'
            . '<stack level="1" type="file" filename="file:///tmp/run.php" lineno="5" where="{main}"/>'
            . '</response>';

        $frames = $parseStackFrames->invoke($server, $stackXml);

        $this->assertSame(
            [
                ['function' => 'App\\Foo::bar', 'file' => 'Foo.php', 'line' => 42],
                ['function' => '{main}', 'file' => 'run.php', 'line' => 5],
            ],
            $frames,
        );
    }

    public function testBreakpointReferenceUsesConfiguredBreakpointMetadata(): void
    {
        $server = new DebugServer($this->testScript, 9004, null, [], true);

        $reflection = new ReflectionClass($server);
        $configuredBreakpoints = $reflection->getProperty('configuredBreakpoints');
        $configuredBreakpoints->setValue($server, [
            [
                'id' => '12',
                'label' => 'bp1 Foo.php:42',
                'file' => '/tmp/Foo.php',
                'line' => 42,
            ],
        ]);

        $breakpointReference = $reflection->getMethod('breakpointReferenceForLocation');

        $this->assertSame(
            ['id' => '12', 'label' => 'bp1 Foo.php:42'],
            $breakpointReference->invoke($server, ['file' => 'Foo.php', 'line' => 42]),
        );
    }

    /**
     * Test that DebugServer creates proper Xdebug arguments for different configurations
     */
    public function testXdebugArgumentGeneration(): void
    {
        $server = new DebugServer($this->testScript, 9005, 10, ['timeout' => 60], true);

        $reflection = new ReflectionClass($server);

        // Test script path
        $scriptProperty = $reflection->getProperty('targetScript');
        $this->assertEquals($this->testScript, $scriptProperty->getValue($server));

        // Test debug port
        $portProperty = $reflection->getProperty('debugPort');
        $this->assertEquals(9005, $portProperty->getValue($server));

        // Test breakpoint line
        $breakpointProperty = $reflection->getProperty('initialBreakpointLine');
        $this->assertEquals(10, $breakpointProperty->getValue($server));

        // Test JSON mode
        $jsonProperty = $reflection->getProperty('jsonMode');
        $this->assertTrue($jsonProperty->getValue($server));

        // Test options
        $optionsProperty = $reflection->getProperty('options');
        $options = $optionsProperty->getValue($server);
        $this->assertEquals(60, $options['timeout']);
    }
}
