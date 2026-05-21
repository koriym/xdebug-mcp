<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\Utilities\VendorFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(VendorFilter::class)]
final class VendorFilterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/xdebug_mcp_vendor_' . uniqid();
        mkdir($this->root . '/vendor/bear/resource', 0777, true);
        mkdir($this->root . '/vendor/ray/di', 0777, true);
        mkdir($this->root . '/vendor/composer', 0777, true);
    }

    protected function tearDown(): void
    {
        rmdir($this->root . '/vendor/composer');
        rmdir($this->root . '/vendor/ray/di');
        rmdir($this->root . '/vendor/ray');
        rmdir($this->root . '/vendor/bear/resource');
        rmdir($this->root . '/vendor/bear');
        rmdir($this->root . '/vendor');
        rmdir($this->root);
    }

    #[Test]
    public function excludesWholeVendorDirectoryByDefault(): void
    {
        $excludePaths = VendorFilter::excludePaths($this->root . '/vendor', null);

        $this->assertSame([$this->root . '/vendor/'], $excludePaths);
    }

    #[Test]
    public function includesOnlyMatchingVendorPackages(): void
    {
        $excludePaths = VendorFilter::excludePaths($this->root . '/vendor', 'bear/*');

        $this->assertContains($this->root . '/vendor/ray/di/', $excludePaths);
        $this->assertNotContains($this->root . '/vendor/bear/resource/', $excludePaths);
    }

    #[Test]
    public function starPatternIncludesEveryVendorPackage(): void
    {
        $excludePaths = VendorFilter::excludePaths($this->root . '/vendor', '*/*');

        $this->assertSame([], $excludePaths);
    }
}
