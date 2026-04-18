<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Closure;
use Koriym\XdebugMcp\ClaudeTraceAnalyzer;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

class ClaudeTraceAnalyzerTest extends TestCase
{
    /** @var list<string> */
    private array $logMessages = [];
    private Closure $logger;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->logMessages = [];
        $this->logger = function (string $message): void {
            $this->logMessages[] = $message;
        };
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        $this->tempFiles = [];
    }

    public function testBuildPromptIncludesTargetScriptBasename(): void
    {
        $analyzer = new ClaudeTraceAnalyzer();

        $prompt = $analyzer->buildPrompt(
            [
                'target_script' => '/srv/app/login.php',
                'debug_port' => 9004,
                'trace_file' => null,
                'breakpoint_line' => null,
            ],
            '',
        );

        $this->assertStringContainsString('Analyze PHP debugging session for login.php', $prompt);
        $this->assertStringNotContainsString('## Trace Analysis', $prompt);
    }

    public function testBuildPromptIncludesTraceSectionWhenFileIsInsideTempDir(): void
    {
        $analyzer = new ClaudeTraceAnalyzer();
        $tracePath = $this->createTempTraceFile("level\tfunc\t0\tmain\n");

        $prompt = $analyzer->buildPrompt(
            [
                'target_script' => 'app.php',
                'debug_port' => 9004,
                'trace_file' => $tracePath,
                'breakpoint_line' => null,
            ],
            '',
        );

        $this->assertStringContainsString('## Trace Analysis', $prompt);
        $this->assertStringContainsString($tracePath, $prompt);
        $this->assertStringContainsString('Recent trace data', $prompt);
    }

    public function testBuildPromptRejectsTraceFileOutsideAllowedRoots(): void
    {
        $analyzer = new ClaudeTraceAnalyzer();

        $prompt = $analyzer->buildPrompt(
            [
                'target_script' => 'app.php',
                'debug_port' => 9004,
                'trace_file' => '/etc/hosts',
                'breakpoint_line' => null,
            ],
            '',
        );

        $this->assertStringNotContainsString('/etc/hosts', $prompt);
        $this->assertStringNotContainsString('## Trace Analysis', $prompt);
    }

    public function testBuildPromptSkipsTraceSectionWhenFileMissing(): void
    {
        $analyzer = new ClaudeTraceAnalyzer();

        $prompt = $analyzer->buildPrompt(
            [
                'target_script' => 'app.php',
                'debug_port' => 9004,
                'trace_file' => sys_get_temp_dir() . '/definitely-missing-' . uniqid() . '.xt',
                'breakpoint_line' => null,
            ],
            '',
        );

        $this->assertStringNotContainsString('## Trace Analysis', $prompt);
    }

    public function testBuildPromptIncludesVariablesBreakpointAndUserArgs(): void
    {
        $analyzer = new ClaudeTraceAnalyzer();

        $prompt = $analyzer->buildPrompt(
            [
                'target_script' => 'auth.php',
                'debug_port' => 9004,
                'trace_file' => null,
                'breakpoint_line' => 42,
                'current_variables' => ['user' => 'null'],
            ],
            'Why is user null?',
        );

        $this->assertStringContainsString('## Current Variables', $prompt);
        $this->assertStringContainsString('$user = null', $prompt);
        $this->assertStringContainsString('Stopped at line 42 in auth.php', $prompt);
        $this->assertStringContainsString('## Specific Analysis Request', $prompt);
        $this->assertStringContainsString('Why is user null?', $prompt);
    }

    public function testAnalyzeLogsFailureWhenRunnerReturnsNull(): void
    {
        $analyzer = new ClaudeTraceAnalyzer(static fn (string $cmd): string|null => null);

        $analyzer->analyze(
            [
                'target_script' => 'app.php',
                'debug_port' => 9004,
                'trace_file' => null,
                'breakpoint_line' => null,
            ],
            '',
            $this->logger,
        );

        $this->assertContains('❌ Claude analysis failed or produced no output', $this->logMessages);
    }

    public function testAnalyzeLogsResultLines(): void
    {
        $analyzer = new ClaudeTraceAnalyzer(
            static fn (string $cmd): string => "line one\n\nline two\n",
        );

        $analyzer->analyze(
            [
                'target_script' => 'app.php',
                'debug_port' => 9004,
                'trace_file' => null,
                'breakpoint_line' => null,
            ],
            '',
            $this->logger,
        );

        $this->assertContains('📊 Claude Analysis Result:', $this->logMessages);
        $this->assertContains('   line one', $this->logMessages);
        $this->assertContains('   line two', $this->logMessages);
    }

    public function testAnalyzePassesPromptToRunner(): void
    {
        $capturedCommand = null;
        $analyzer = new ClaudeTraceAnalyzer(
            static function (string $cmd) use (&$capturedCommand): string {
                $capturedCommand = $cmd;

                return 'ok';
            },
        );

        $analyzer->analyze(
            [
                'target_script' => 'app.php',
                'debug_port' => 9004,
                'trace_file' => null,
                'breakpoint_line' => null,
            ],
            '',
            $this->logger,
        );

        $this->assertIsString($capturedCommand);
        $this->assertStringStartsWith('claude --print ', $capturedCommand);
        $this->assertStringEndsWith(' 2>&1', $capturedCommand);
    }

    private function createTempTraceFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xdebug-mcp-trace-');
        if ($path === false) {
            $this->fail('Unable to create temp trace file');
        }

        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
