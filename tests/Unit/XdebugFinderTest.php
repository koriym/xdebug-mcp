<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\Exceptions\XdebugNotAvailableException;
use Koriym\XdebugMcp\XdebugFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function escapeshellarg;
use function extension_loaded;
use function file_put_contents;
use function microtime;
use function ob_end_clean;
use function ob_start;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const PHP_BINARY;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;

#[CoversClass(XdebugFinder::class)]
final class XdebugFinderTest extends TestCase
{
    /** @var list<string> */
    private array $fakeBinaries = [];

    protected function tearDown(): void
    {
        foreach ($this->fakeBinaries as $path) {
            unlink($path);
        }

        $this->fakeBinaries = [];
    }

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

    #[Test]
    public function targetThatAlreadyLoadsXdebugGetsNoInjection(): void
    {
        $binary = $this->fakePhp('1', '/nonexistent-extension-dir', '7.2');

        $this->assertSame('', XdebugFinder::getXdebugFlag($binary));
        $this->assertTrue(XdebugFinder::isXdebugAvailable($binary));
    }

    /**
     * Startup warnings printed before the answer must not be read as the
     * answer. The flag alone cannot show this — a probe that fails to parse
     * also yields '' — so assert the parsed state: only a successfully read
     * answer reports the target as available.
     */
    #[Test]
    public function targetProbeIgnoresStartupWarningsOnStdout(): void
    {
        $binary = $this->fakePhp('1', '/nonexistent-extension-dir', '7.2', "Warning: Module 'apcu' already loaded in Unknown on line 0");

        $this->assertSame('', XdebugFinder::getXdebugFlag($binary));
        $this->assertTrue(XdebugFinder::isXdebugAvailable($binary), 'The answer line must be found despite the warning');
    }

    /** A separator inside extension_dir must not shift the version field. */
    #[Test]
    public function targetProbeReadsVersionWhenExtensionDirContainsSeparator(): void
    {
        $binary = $this->fakePhp('1', '/opt/we|ird/dir', '7.2');

        $this->assertTrue(XdebugFinder::isXdebugAvailable($binary));
        $this->assertTrue(XdebugFinder::canUseHostHelpers($this->fakePhp('1', '/opt/we|ird/dir', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION)));
    }

    /** A target that cannot be executed must not block on reading stdin. */
    #[Test]
    public function targetProbeDoesNotBlockOnStdin(): void
    {
        $path = sys_get_temp_dir() . '/xdebug-mcp-blocking-' . uniqid();
        file_put_contents($path, "#!/bin/sh\nread line\necho \"\$line\"\n");
        chmod($path, 0o755);
        $this->fakeBinaries[] = $path;

        $started = microtime(true);
        $this->assertSame('', XdebugFinder::getXdebugFlag($path));
        $this->assertLessThan(10.0, microtime(true) - $started, 'Probe must not wait for stdin');
    }

    /** A foreign target without its own Xdebug must fail loudly, never borrow this interpreter's extension. */
    #[Test]
    public function targetWithoutLoadableXdebugThrows(): void
    {
        // A version no Homebrew keg or extension_dir can satisfy, so the
        // lookup genuinely fails instead of finding a version-matched build.
        $binary = $this->fakePhp('0', '/nonexistent-extension-dir', '4.9');

        $this->expectException(XdebugNotAvailableException::class);
        $this->expectExceptionMessageMatches('/not loadable in PHP 4\.9/');
        XdebugFinder::getXdebugFlag($binary);
    }

    /** An unrunnable binary is unknowable: report no flag rather than this interpreter's extension. */
    #[Test]
    public function unrunnableTargetGetsNoInjection(): void
    {
        $missing = sys_get_temp_dir() . '/xdebug-mcp-missing-' . uniqid() . '/php';

        $this->assertSame('', XdebugFinder::getXdebugFlag($missing));
        $this->assertFalse(XdebugFinder::isXdebugAvailable($missing));
    }

    /**
     * The helper gate is about PHP version, not path identity: the running
     * interpreter and any same-version target can run the host-built
     * auto_prepend helpers, a different version cannot.
     */
    #[Test]
    public function hostHelpersAreAllowedForAnySameVersionTarget(): void
    {
        $hostVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        $this->assertTrue(XdebugFinder::canUseHostHelpers(null));
        $this->assertTrue(XdebugFinder::canUseHostHelpers('php'));
        $this->assertTrue(XdebugFinder::canUseHostHelpers(PHP_BINARY));
        // A different path reporting the host's own version still qualifies.
        $this->assertTrue(XdebugFinder::canUseHostHelpers($this->fakePhp('1', '/x', $hostVersion)));

        $this->assertFalse(XdebugFinder::canUseHostHelpers($this->fakePhp('1', '/x', '7.2')));
        $this->assertFalse(XdebugFinder::canUseHostHelpers(sys_get_temp_dir() . '/xdebug-mcp-missing-' . uniqid()));
    }

    /** Stand-in for a PHP binary: answers the probe, ignoring the arguments it is given. */
    private function fakePhp(string $loaded, string $extensionDir, string $version, string $noise = ''): string
    {
        $path = sys_get_temp_dir() . '/xdebug-mcp-fake-php-' . uniqid();
        $noiseLine = $noise !== '' ? 'echo ' . escapeshellarg($noise) . "\n" : '';
        file_put_contents(
            $path,
            "#!/bin/sh\n"
            . $noiseLine
            . 'echo "__XDEBUG_MCP_PROBE__|' . $loaded . '|' . $extensionDir . '|' . $version . '"' . "\n",
        );
        chmod($path, 0o755);
        $this->fakeBinaries[] = $path;

        return $path;
    }
}
