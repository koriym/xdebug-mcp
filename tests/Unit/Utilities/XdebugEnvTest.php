<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit\Utilities;

use Koriym\XdebugMcp\Utilities\XdebugEnv;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function fopen;
use function getenv;
use function putenv;
use function rewind;
use function sprintf;
use function stream_get_contents;

#[CoversClass(XdebugEnv::class)]
final class XdebugEnvTest extends TestCase
{
    private const VARS = ['XDEBUG_MODE', 'XDEBUG_CONFIG', 'XDEBUG_TRIGGER'];

    /** @var array<string, string|false> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        // Save and clear; tests must not leak env changes into the PHPUnit
        // process (integration tests observe the inherited environment)
        foreach (self::VARS as $var) {
            $this->savedEnv[$var] = getenv($var);
            putenv($var);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnv as $var => $value) {
            putenv($value === false ? $var : sprintf('%s=%s', $var, $value));
        }
    }

    #[Test]
    public function shellPrefixPinsModeAndUnsetsConfigAndTrigger(): void
    {
        $prefix = XdebugEnv::shellPrefix('coverage');

        $this->assertSame("env -u XDEBUG_CONFIG -u XDEBUG_TRIGGER XDEBUG_MODE='coverage' ", $prefix);
    }

    #[Test]
    public function noticeIfInheritedIsSilentWithoutInheritedVars(): void
    {
        $this->assertSame('', $this->captureNotice('coverage'));
    }

    #[Test]
    public function noticeIfInheritedReportsInheritedVars(): void
    {
        putenv('XDEBUG_MODE=coverage');
        putenv('XDEBUG_TRIGGER=1');

        $notice = $this->captureNotice('debug,trace');

        $this->assertStringContainsString('XDEBUG_MODE', $notice);
        $this->assertStringContainsString('XDEBUG_TRIGGER', $notice);
        $this->assertStringNotContainsString('XDEBUG_CONFIG,', $notice);
    }

    #[Test]
    public function noticeIfInheritedReportsEmptyButPresentVars(): void
    {
        // An empty-but-present variable still counts as set for Xdebug
        putenv('XDEBUG_TRIGGER=');

        $this->assertStringContainsString('XDEBUG_TRIGGER', $this->captureNotice('trace'));
    }

    #[Test]
    public function noticeIfInheritedSkipsModeIdenticalToPinned(): void
    {
        putenv('XDEBUG_MODE=coverage');

        $this->assertSame('', $this->captureNotice('coverage'));
    }

    #[Test]
    public function noticeIfInheritedReportsModeDifferentFromPinned(): void
    {
        putenv('XDEBUG_MODE=debug');

        $this->assertStringContainsString('XDEBUG_MODE', $this->captureNotice('coverage'));
    }

    private function captureNotice(string $pinnedMode): string
    {
        $stream = fopen('php://memory', 'w+');
        $this->assertIsResource($stream);

        XdebugEnv::noticeIfInherited($pinnedMode, $stream);

        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
