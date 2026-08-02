<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit\Utilities;

use Koriym\XdebugMcp\Utilities\XdebugEnv;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function fopen;
use function putenv;
use function rewind;
use function stream_get_contents;

#[CoversClass(XdebugEnv::class)]
final class XdebugEnvTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('XDEBUG_MODE');
        putenv('XDEBUG_CONFIG');
        putenv('XDEBUG_TRIGGER');
    }

    #[Test]
    public function shellPrefixPinsModeAndClearsConfigAndTrigger(): void
    {
        $prefix = XdebugEnv::shellPrefix('coverage');

        $this->assertSame("XDEBUG_MODE='coverage' XDEBUG_CONFIG= XDEBUG_TRIGGER= ", $prefix);
    }

    #[Test]
    public function noticeIfInheritedIsSilentWithoutInheritedVars(): void
    {
        putenv('XDEBUG_MODE');
        putenv('XDEBUG_CONFIG');
        putenv('XDEBUG_TRIGGER');

        $this->assertSame('', $this->captureNotice());
    }

    #[Test]
    public function noticeIfInheritedReportsInheritedVars(): void
    {
        putenv('XDEBUG_MODE=coverage');
        putenv('XDEBUG_TRIGGER=1');

        $notice = $this->captureNotice();

        $this->assertStringContainsString('XDEBUG_MODE', $notice);
        $this->assertStringContainsString('XDEBUG_TRIGGER', $notice);
        $this->assertStringNotContainsString('XDEBUG_CONFIG,', $notice);
    }

    private function captureNotice(): string
    {
        $stream = fopen('php://memory', 'w+');
        $this->assertIsResource($stream);

        XdebugEnv::noticeIfInherited($stream);

        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
