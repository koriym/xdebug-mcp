<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\XdebugFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
use function ob_end_clean;
use function ob_start;

#[CoversClass(XdebugFinder::class)]
final class XdebugFinderTest extends TestCase
{
    #[Test]
    public function getXdebugFlagReturnsEmptyWhenLoaded(): void
    {
        if (! extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug not loaded');
        }

        $flag = XdebugFinder::getXdebugFlag();
        $this->assertSame('', $flag);
    }

    #[Test]
    public function detectXdebugPathReturnsNullWhenLoaded(): void
    {
        if (! extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug not loaded');
        }

        $path = XdebugFinder::detectXdebugPath();
        $this->assertNull($path);
    }

    #[Test]
    public function isXdebugAvailableReturnsTrueWhenLoaded(): void
    {
        if (! extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug not loaded');
        }

        $this->assertTrue(XdebugFinder::isXdebugAvailable());
    }

    #[Test]
    public function showInstallationGuidanceWithoutExit(): void
    {
        ob_start();
        XdebugFinder::showInstallationGuidance(false);
        ob_end_clean();

        // Just ensure it doesn't throw - output goes to STDERR
        $this->assertTrue(true);
    }
}
