<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function dirname;
use function fclose;
use function feof;
use function file_put_contents;
use function fread;
use function fwrite;
use function is_resource;
use function microtime;
use function proc_close;
use function proc_open;
use function sleep;
use function stream_set_blocking;
use function substr_count;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usleep;
use function var_export;

use const PHP_BINARY;

class XreplIdleTest extends TestCase
{
    private const PROMPT = '(Xdebug)';
    private const EXECUTION_TIMEOUT = 1.0;
    private const IDLE_SECONDS = 2;

    private string $driver;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $fixture = $root . '/tests/fixtures/simple.php';
        $this->driver = tempnam(sys_get_temp_dir(), 'xrepl_idle_');
        file_put_contents($this->driver, '<?php
require ' . var_export($root . '/vendor/autoload.php', true) . ';
(new Koriym\XdebugMcp\DebugServer(' . var_export($fixture, true) . ', 9004, null, [
    "breakpoints" => [["file" => ' . var_export($fixture, true) . ', "line" => 11]],
    "command" => [PHP_BINARY, ' . var_export($fixture, true) . '],
    "traceOnly" => false,
    "jsonOutput" => false,
    "executionTimeout" => ' . var_export(self::EXECUTION_TIMEOUT, true) . ',
], false))();
');
    }

    protected function tearDown(): void
    {
        unlink($this->driver);
    }

    public function testSessionSurvivesIdleLongerThanExecutionTimeout(): void
    {
        $process = proc_open([PHP_BINARY, $this->driver], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        $this->assertTrue(is_resource($process));
        stream_set_blocking($pipes[1], false);

        $output = $this->readUntilPrompts($pipes[1], 1, '');
        $this->assertStringContainsString('simple.php:11', $output);

        sleep(self::IDLE_SECONDS);
        fwrite($pipes[0], "s\n");
        $output = $this->readUntilPrompts($pipes[1], 2, $output);
        fwrite($pipes[0], "q\n");
        fclose($pipes[0]);
        fclose($pipes[1]);
        proc_close($process);

        $this->assertStringNotContainsString('TimeoutException', $output);
        $this->assertStringContainsString('simple.php:12', $output);
    }

    /** @param resource $stdout */
    private function readUntilPrompts($stdout, int $prompts, string $output): string
    {
        $deadline = microtime(true) + 30;
        while (substr_count($output, self::PROMPT) < $prompts && ! feof($stdout) && microtime(true) < $deadline) {
            $output .= (string) fread($stdout, 8192);
            usleep(50_000);
        }

        return $output;
    }
}
