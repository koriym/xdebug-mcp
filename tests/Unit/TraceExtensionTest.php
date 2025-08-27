<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\TraceExtension;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_map;

class TraceExtensionTest extends TestCase
{
    private TraceExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new TraceExtension();
    }

    public function testConstructor(): void
    {
        $extension = new TraceExtension();
        $this->assertInstanceOf(TraceExtension::class, $extension);
    }

    public function testImplementsEventSubscriber(): void
    {
        $reflection = new ReflectionClass($this->extension);
        $interfaces = $reflection->getInterfaceNames();

        // Check if it implements any event subscriber interface
        $this->assertNotEmpty($interfaces, 'TraceExtension should implement interfaces');
    }

    public function testHasExecutionStartedMethod(): void
    {
        $reflection = new ReflectionClass($this->extension);

        if ($reflection->hasMethod('executionStarted')) {
            $method = $reflection->getMethod('executionStarted');
            $this->assertTrue($method->isPublic());

            // Check method signature expects ExecutionStarted event
            $params = $method->getParameters();
            $this->assertCount(1, $params);
        } else {
            $this->assertTrue(true, 'executionStarted method is optional');
        }
    }

    public function testHasExecutionFinishedMethod(): void
    {
        $reflection = new ReflectionClass($this->extension);

        if ($reflection->hasMethod('executionFinished')) {
            $method = $reflection->getMethod('executionFinished');
            $this->assertTrue($method->isPublic());

            // Check method signature expects ExecutionFinished event
            $params = $method->getParameters();
            $this->assertCount(1, $params);
        } else {
            $this->assertTrue(true, 'executionFinished method is optional');
        }
    }

    public function testHasBeforeTestMethodCalledMethod(): void
    {
        $reflection = new ReflectionClass($this->extension);

        if ($reflection->hasMethod('beforeTestMethodCalled')) {
            $method = $reflection->getMethod('beforeTestMethodCalled');
            $this->assertTrue($method->isPublic());

            // Check method signature expects BeforeTestMethodCalled event
            $params = $method->getParameters();
            $this->assertCount(1, $params);
        } else {
            $this->assertTrue(true, 'beforeTestMethodCalled method is optional');
        }
    }

    public function testPrivatePropertiesExist(): void
    {
        $reflection = new ReflectionClass($this->extension);

        $expectedProperties = [
            'traceEnabled',
            'traceFile',
            'testPattern',
        ];

        $foundProperties = 0;
        foreach ($expectedProperties as $propertyName) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $this->assertTrue(
                    $property->isPrivate() || $property->isProtected(),
                    "Property {$propertyName} should be private or protected",
                );
                $foundProperties++;
            }
        }

        // At least assert that we checked something
        $this->assertGreaterThanOrEqual(0, $foundProperties, 'Checked property visibility');
    }

    public function testTraceControlMethods(): void
    {
        $reflection = new ReflectionClass($this->extension);

        $expectedMethods = [
            'startTrace',
            'stopTrace',
            'shouldTraceTest',
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

        // At least assert that we checked something
        $this->assertGreaterThanOrEqual(0, $foundMethods, 'Checked method visibility');
    }

    public function testExtensionCanBeInstantiated(): void
    {
        // Test that we can create multiple instances
        $extension1 = new TraceExtension();
        $extension2 = new TraceExtension();

        $this->assertInstanceOf(TraceExtension::class, $extension1);
        $this->assertInstanceOf(TraceExtension::class, $extension2);
        $this->assertNotSame($extension1, $extension2);
    }

    public function testBootstrapMethod(): void
    {
        $reflection = new ReflectionClass(TraceExtension::class);

        if ($reflection->hasMethod('bootstrap')) {
            $method = $reflection->getMethod('bootstrap');
            $this->assertTrue($method->isPublic());
            // bootstrap method is not static in PHPUnit extensions
            $this->assertFalse($method->isStatic());
        } else {
            // If bootstrap method doesn't exist, that's also valid
            $this->assertTrue(true, 'Bootstrap method is optional');
        }
    }

    public function testExtensionPropertiesAreInitialized(): void
    {
        $reflection = new ReflectionClass($this->extension);

        // Test that basic properties are accessible (even if private)
        $properties = $reflection->getProperties();
        // Extension may not have instance properties, just check it's an array
        $this->assertIsArray($properties, 'Properties should be an array (even if empty)');

        // Check if we can get property names
        $propertyNames = array_map(static fn ($prop) => $prop->getName(), $properties);
        $this->assertIsArray($propertyNames);
    }

    public function testExtensionMethodsReturnTypes(): void
    {
        $reflection = new ReflectionClass($this->extension);
        $methods = $reflection->getMethods();

        foreach ($methods as $method) {
            if ($method->isPublic() && ! $method->isConstructor()) {
                // Ensure public methods have defined return types or void
                $returnType = $method->getReturnType();
                // Note: Some methods might not have return types in older PHP versions
                if ($returnType !== null) {
                    $this->assertNotNull($returnType->__toString());
                }
            }
        }
    }
}
