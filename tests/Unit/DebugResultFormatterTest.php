<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Closure;
use Koriym\XdebugMcp\DebugResultFormatter;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function implode;
use function json_decode;
use function ob_get_clean;
use function ob_start;

use const JSON_THROW_ON_ERROR;

class DebugResultFormatterTest extends TestCase
{
    /** @var list<string> */
    private array $logMessages = [];
    private Closure $logger;

    protected function setUp(): void
    {
        $this->logMessages = [];
        $this->logger = function (string $message): void {
            $this->logMessages[] = $message;
        };
    }

    public function testBuildBreakpointPayloadOmitsContextWhenEmpty(): void
    {
        $formatter = new DebugResultFormatter();

        $payload = $formatter->buildBreakpointPayload(
            [],
            ['file' => '', 'lines' => 0, 'functions' => 0, 'max_depth' => 0, 'db_queries' => 0],
        );

        $this->assertSame(
            'https://koriym.github.io/xdebug-mcp/schemas/xstep.json',
            $payload['$schema'],
        );
        $this->assertFalse(
            array_key_exists('context', $payload),
            'context key must not be present when not provided',
        );
    }

    public function testBuildBreakpointPayloadIncludesContextWhenProvided(): void
    {
        $formatter = new DebugResultFormatter();

        $payload = $formatter->buildBreakpointPayload(
            [],
            ['file' => '', 'lines' => 0, 'functions' => 0, 'max_depth' => 0, 'db_queries' => 0],
            'Debugging login failure',
        );

        $this->assertArrayHasKey('context', $payload);
        $this->assertSame('Debugging login failure', $payload['context']);
    }

    public function testEmitJsonProducesSingleLineJson(): void
    {
        $formatter = new DebugResultFormatter();

        $break = [
            'step' => 1,
            'location' => ['file' => '/tmp/app.php', 'line' => 42],
            'variables' => ['x' => '10'],
        ];

        $payload = $formatter->buildBreakpointPayload(
            [$break],
            ['file' => '/tmp/trace.xt', 'lines' => 12, 'functions' => 3, 'max_depth' => 2, 'db_queries' => 0],
            'ctx',
        );

        ob_start();
        $formatter->emit($payload, true, $this->logger);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ctx', $decoded['context']);
        $this->assertSame('/tmp/trace.xt', $decoded['trace']['file']);
        $this->assertSame(1, $decoded['breaks'][0]['step']);
        $this->assertSame([], $this->logMessages, 'JSON mode must not emit log output');
    }

    public function testEmitTextPrintsBreakpointAndVariables(): void
    {
        $formatter = new DebugResultFormatter();

        $break = [
            'step' => 2,
            'location' => ['file' => '/srv/foo.php', 'line' => 17],
            'variables' => ['user' => 'null'],
        ];

        $payload = $formatter->buildBreakpointPayload(
            [$break],
            ['file' => '/tmp/out.xt', 'lines' => 5, 'functions' => 1, 'max_depth' => 1, 'db_queries' => 0],
        );

        $formatter->emit($payload, false, $this->logger);

        $joined = implode("\n", $this->logMessages);
        $this->assertStringContainsString('Step 2: /srv/foo.php:17', $joined);
        $this->assertStringContainsString('user = null', $joined);
        $this->assertStringContainsString('Trace file: /tmp/out.xt', $joined);
        $this->assertStringContainsString('Trace lines: 5', $joined);
    }

    public function testEmitTextSkipsTraceBlockWhenFileEmpty(): void
    {
        $formatter = new DebugResultFormatter();

        $payload = $formatter->buildBreakpointPayload(
            [
                [
                    'step' => 1,
                    'location' => ['file' => '/srv/foo.php', 'line' => 1],
                    'variables' => [],
                ],
            ],
            ['file' => '', 'lines' => 0, 'functions' => 0, 'max_depth' => 0, 'db_queries' => 0],
        );

        $formatter->emit($payload, false, $this->logger);

        $joined = implode("\n", $this->logMessages);
        $this->assertStringNotContainsString('Trace file:', $joined);
        $this->assertStringNotContainsString('Trace lines:', $joined);
    }
}
