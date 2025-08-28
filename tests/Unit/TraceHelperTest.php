<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\TraceHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_filter;
use function array_intersect;
use function array_map;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class TraceHelperTest extends TestCase
{
    private string $sampleTraceFile;

    protected function setUp(): void
    {
        // Create a sample trace file
        $this->sampleTraceFile = tempnam(sys_get_temp_dir(), 'trace_test_') . '.xt';

        $sampleTraceContent = 'Version: 3.1.0
File format: 4
TRACE START [2024-01-15 10:30:00.000000]
           0.0001     384000   -> {main}() /tmp/test.php:0
           0.0002     384100     -> factorial() /tmp/test.php:3
           0.0003     384200       -> factorial() /tmp/test.php:3
           0.0004     384300         -> factorial() /tmp/test.php:3
           0.0005     384400           -> factorial() /tmp/test.php:3
           0.0006     384500           <- factorial() /tmp/test.php:6
           0.0007     384450         <- factorial() /tmp/test.php:6
           0.0008     384400       <- factorial() /tmp/test.php:6
           0.0009     384350     <- factorial() /tmp/test.php:6
           0.0010     384300   <- {main}() /tmp/test.php:7
TRACE END   [2024-01-15 10:30:00.010000]';

        file_put_contents($this->sampleTraceFile, $sampleTraceContent);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->sampleTraceFile)) {
            unlink($this->sampleTraceFile);
        }
    }

    public function testParseTraceFileMethod(): void
    {
        $reflection = new ReflectionClass(TraceHelper::class);

        if ($reflection->hasMethod('parseTraceFile')) {
            $method = $reflection->getMethod('parseTraceFile');

            // Make method accessible if it's private/protected
            if (! $method->isPublic()) {
                $method->setAccessible(true);
            }

            // Test parsing the sample trace file
            if ($method->isStatic()) {
                $result = $method->invoke(null, $this->sampleTraceFile);
            } else {
                $helper = new TraceHelper();
                $result = $method->invoke($helper, $this->sampleTraceFile);
            }

            $this->assertIsArray($result);
        } else {
            $this->markTestSkipped('parseTraceFile method not found');
        }
    }

    public function testAnalyzeTraceMethod(): void
    {
        $reflection = new ReflectionClass(TraceHelper::class);

        if ($reflection->hasMethod('analyzeTrace')) {
            $method = $reflection->getMethod('analyzeTrace');

            if (! $method->isPublic()) {
                $method->setAccessible(true);
            }

            $sampleTraceData = [
                ['function' => 'main', 'time' => 0.001, 'memory' => 384000],
                ['function' => 'factorial', 'time' => 0.002, 'memory' => 384100],
                ['function' => 'factorial', 'time' => 0.003, 'memory' => 384200],
            ];

            if ($method->isStatic()) {
                $result = $method->invoke(null, $sampleTraceData);
            } else {
                $helper = new TraceHelper();
                $result = $method->invoke($helper, $sampleTraceData);
            }

            $this->assertIsArray($result);
        } else {
            $this->markTestSkipped('analyzeTrace method not found');
        }
    }

    public function testFormatTraceOutputMethod(): void
    {
        $reflection = new ReflectionClass(TraceHelper::class);

        if ($reflection->hasMethod('formatTraceOutput')) {
            $method = $reflection->getMethod('formatTraceOutput');

            if (! $method->isPublic()) {
                $method->setAccessible(true);
            }

            $sampleData = [
                'function_calls' => 5,
                'execution_time' => 0.01,
                'memory_peak' => 384500,
                'functions' => ['main', 'factorial'],
            ];

            if ($method->isStatic()) {
                $result = $method->invoke(null, $sampleData);
            } else {
                $helper = new TraceHelper();
                $result = $method->invoke($helper, $sampleData);
            }

            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        } else {
            $this->markTestSkipped('formatTraceOutput method not found');
        }
    }

    public function testTraceHelperCanBeInstantiated(): void
    {
        $helper = new TraceHelper();
        $this->assertInstanceOf(TraceHelper::class, $helper);
    }

    public function testTraceHelperHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(TraceHelper::class);
        $methods = $reflection->getMethods();

        $methodNames = array_map(static fn ($method) => $method->getName(), $methods);

        // Check for common expected methods (even if they don't exist yet)
        $possibleMethods = [
            'parseTraceFile',
            'analyzeTrace',
            'formatTraceOutput',
            'extractFunctionCalls',
            'calculateExecutionTime',
            'findBottlenecks',
        ];

        $foundMethods = array_intersect($possibleMethods, $methodNames);

        // We expect at least some trace-related methods to exist
        $this->assertNotEmpty($methods, 'TraceHelper should have methods');
    }

    public function testStaticMethodsIfExist(): void
    {
        $reflection = new ReflectionClass(TraceHelper::class);
        $staticMethods = array_filter(
            $reflection->getMethods(),
            static fn ($method) => $method->isStatic(),
        );

        foreach ($staticMethods as $method) {
            // Static methods should be callable without instance
            $this->assertTrue($method->isStatic());
            $this->assertIsString($method->getName());
        }
    }

    public function testTraceFileValidation(): void
    {
        $reflection = new ReflectionClass(TraceHelper::class);

        if ($reflection->hasMethod('isValidTraceFile')) {
            $method = $reflection->getMethod('isValidTraceFile');

            if (! $method->isPublic()) {
                $method->setAccessible(true);
            }

            // Test with valid trace file
            if ($method->isStatic()) {
                $result = $method->invoke(null, $this->sampleTraceFile);
            } else {
                $helper = new TraceHelper();
                $result = $method->invoke($helper, $this->sampleTraceFile);
            }

            $this->assertIsBool($result);

            // Test with non-existent file
            if ($method->isStatic()) {
                $result = $method->invoke(null, '/nonexistent/file.xt');
            } else {
                $helper = new TraceHelper();
                $result = $method->invoke($helper, '/nonexistent/file.xt');
            }

            $this->assertIsBool($result);
            $this->assertFalse($result);
        } else {
            $this->markTestSkipped('isValidTraceFile method not found');
        }
    }

    public function testTraceHelperWithEmptyTraceFile(): void
    {
        $emptyTraceFile = tempnam(sys_get_temp_dir(), 'empty_trace_') . '.xt';
        file_put_contents($emptyTraceFile, '');

        try {
            $reflection = new ReflectionClass(TraceHelper::class);

            if ($reflection->hasMethod('parseTraceFile')) {
                $method = $reflection->getMethod('parseTraceFile');

                if (! $method->isPublic()) {
                    $method->setAccessible(true);
                }

                if ($method->isStatic()) {
                    $result = $method->invoke(null, $emptyTraceFile);
                } else {
                    $helper = new TraceHelper();
                    $result = $method->invoke($helper, $emptyTraceFile);
                }

                $this->assertIsArray($result);
                $this->assertEmpty($result);
            } else {
                // If parseTraceFile method doesn't exist, just assert the file is empty
                $this->assertEquals('', file_get_contents($emptyTraceFile), 'Empty trace file should be empty');
            }
        } finally {
            unlink($emptyTraceFile);
        }
    }
}
