<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit\Profiler;

use Koriym\XdebugMcp\Profiler\CachegrindAnalyzer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_column;
use function dirname;

#[CoversClass(CachegrindAnalyzer::class)]
final class CachegrindAnalyzerTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        $this->fixture = dirname(__DIR__, 2) . '/fixtures/cachegrind_test.out';
    }

    #[Test]
    public function analyzesCachegrindFixture(): void
    {
        $analyzer = new CachegrindAnalyzer();
        $result = $analyzer->analyze($this->fixture);

        $this->assertSame(31, $result['total_lines']);
        $this->assertSame(5, $result['functions_count']);
        $this->assertSame(3, $result['user_functions']);
        $this->assertSame(2, $result['internal_functions']);
        $this->assertSame(2, $result['total_calls']);
        $this->assertSame(1.235, $result['execution_time_ms']);
        $this->assertSame(2.0, $result['peak_memory_mb']);
        $this->assertSame(1, $result['file_io_operations']);
        $this->assertSame(0, $result['database_operations']);
        $this->assertSame([], $result['optimization_suggestions']);

        $functions = array_column($result['bottleneck_functions'], 'function');
        $this->assertSame(['{main}', 'main', 'process', 'fopen', 'php::strlen'], $functions);

        $main = $result['bottleneck_functions'][0];
        $this->assertSame(54.9, $main['percentage']);
        $this->assertSame(0.5, $main['time_ms']);
    }

    #[Test]
    public function includeVendorIncludesMatchingPackage(): void
    {
        $analyzer = new CachegrindAnalyzer();
        $result = $analyzer->analyze($this->fixture, 'test/*');

        $this->assertSame(6, $result['functions_count']);
        $this->assertSame(4, $result['user_functions']);
        $this->assertSame(2, $result['internal_functions']);

        $functions = array_column($result['bottleneck_functions'], 'function');
        $this->assertContains('Test\\Package\\Helper::help', $functions);
    }

    #[Test]
    public function includeVendorExcludesNonMatchingPackage(): void
    {
        $analyzer = new CachegrindAnalyzer();
        $result = $analyzer->analyze($this->fixture, 'other/*');

        $this->assertSame(5, $result['functions_count']);

        $functions = array_column($result['bottleneck_functions'], 'function');
        $this->assertNotContains('Test\\Package\\Helper::help', $functions);
    }

    #[Test]
    public function countsPdoStaticMethodsAsDatabaseOperations(): void
    {
        $fixture = dirname(__DIR__, 2) . '/fixtures/cachegrind_pdo_test.out';
        $analyzer = new CachegrindAnalyzer();
        $result = $analyzer->analyze($fixture);

        $this->assertSame(1, $result['database_operations']);
        $this->assertSame(0, $result['file_io_operations']);

        $functions = array_column($result['bottleneck_functions'], 'function');
        $this->assertContains('PDO::query', $functions);
    }

    #[Test]
    public function throwsExceptionForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Profile file not found or not readable');

        $analyzer = new CachegrindAnalyzer();
        $analyzer->analyze('/nonexistent/cachegrind.out');
    }
}
