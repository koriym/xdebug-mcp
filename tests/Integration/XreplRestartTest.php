<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Integration;

use PHPUnit\Framework\TestCase;

use function dirname;
use function fclose;
use function feof;
use function fread;
use function function_exists;
use function fwrite;
use function is_resource;
use function microtime;
use function proc_close;
use function proc_open;
use function stream_set_blocking;
use function substr_count;
use function usleep;

use const PHP_BINARY;

class XreplRestartTest extends TestCase
{
    private const PROMPT = '(Xdebug)';

    public function testRestartStopsAtTheBreakpointAgain(): void
    {
        if (! function_exists('pcntl_exec')) {
            $this->markTestSkipped('xrepl restarts with pcntl_exec');
        }

        $root = dirname(__DIR__, 2);
        $fixture = $root . '/tests/fixtures/simple.php';
        $process = proc_open(
            [PHP_BINARY, $root . '/bin/xrepl', '--break=' . $fixture . ':11', '--', 'php', $fixture],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $this->assertTrue(is_resource($process));
        stream_set_blocking($pipes[1], false);

        $output = $this->readUntilPrompts($pipes[1], 1, '');
        fwrite($pipes[0], "s\n");
        $output = $this->readUntilPrompts($pipes[1], 2, $output);
        fwrite($pipes[0], "r\n");
        $output = $this->readUntilPrompts($pipes[1], 3, $output);
        fwrite($pipes[0], "q\n");
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $this->assertSame(2, substr_count($output, 'Level 0: {main} at simple.php:11'), $output);
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
