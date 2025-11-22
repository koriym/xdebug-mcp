<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use RuntimeException;

use function array_map;
use function array_merge;
use function array_reverse;
use function array_search;
use function array_shift;
use function array_slice;
use function array_splice;
use function file_exists;
use function filemtime;
use function fwrite;
use function getenv;
use function glob;
use function implode;
use function in_array;
use function passthru;
use function preg_match;
use function usort;

use const STDERR;

/**
 * Unified command runner for Xdebug tools with Docker support
 *
 * Implements Strategy pattern to handle both local and containerized PHP execution
 */
class XdebugRunner
{
    private string $mode;
    private array $commandParts;
    private array $xdebugOptions = [];
    private string|null $context = null;
    private string|null $includeVendor = null;

    public function __construct(array $argv)
    {
        $this->parseArguments($argv);
    }

    public function setMode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function setContext(string|null $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function setIncludeVendor(string|null $includeVendor): self
    {
        $this->includeVendor = $includeVendor;

        return $this;
    }

    public function setXdebugOptions(array $options): self
    {
        $this->xdebugOptions = $options;

        return $this;
    }

    public function run(): int
    {
        if ($this->isDockerCommand($this->commandParts)) {
            $command = $this->buildDockerCommand($this->commandParts);
        } else {
            $this->validateLocalFile($this->commandParts);
            $command = $this->buildLocalCommand($this->commandParts);
        }

        // Debug: output command before execution
        if (getenv('XDEBUG_RUNNER_DEBUG')) {
            fwrite(STDERR, "DEBUG: Executing command: $command\n");
        }

        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Get the most recently generated trace file
     */
    public function getLatestTraceFile(): string|null
    {
        $traceFiles = glob('/tmp/trace.*.xt');
        if (empty($traceFiles)) {
            return null;
        }

        // Sort by modification time, newest first
        usort($traceFiles, static function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $traceFiles[0];
    }

    /**
     * Get the most recently generated profile file
     */
    public function getLatestProfileFile(): string|null
    {
        $profileFiles = glob('/tmp/cachegrind.out.*');
        if (empty($profileFiles)) {
            return null;
        }

        usort($profileFiles, static function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $profileFiles[0];
    }

    private function parseArguments(array $argv): void
    {
        // Find '--' separator
        $separatorIndex = array_search('--', $argv);
        if ($separatorIndex === false) {
            throw new RuntimeException('Missing -- separator');
        }

        // Get command parts after '--'
        $this->commandParts = array_slice($argv, $separatorIndex + 1);

        if (empty($this->commandParts)) {
            throw new RuntimeException('Command is required after --');
        }
    }

    private function isDockerCommand(array $parts): bool
    {
        $containerCommands = ['docker', 'podman', 'kubectl'];

        return isset($parts[0]) && in_array($parts[0], $containerCommands, true);
    }

    private function validateLocalFile(array $parts): void
    {
        // Skip 'php' if present
        if (isset($parts[0]) && $parts[0] === 'php') {
            array_shift($parts);
        }

        $targetFile = $parts[0] ?? '';

        if (! file_exists($targetFile)) {
            throw new RuntimeException("File '$targetFile' not found");
        }
    }

    private function buildLocalCommand(array $parts): string
    {
        // Skip 'php' if present
        if (isset($parts[0]) && $parts[0] === 'php') {
            array_shift($parts);
        }

        $xdebugArgs = $this->generateXdebugArguments();

        return 'php ' . implode(' ', $xdebugArgs) . ' ' . implode(' ', array_map('escapeshellarg', $parts));
    }

    private function buildDockerCommand(array $parts): string
    {
        // Find the position of 'php' command within Docker command
        $phpIndex = $this->findPhpCommandIndex($parts);

        if ($phpIndex === false) {
            throw new RuntimeException('PHP command not found in Docker command. Expected format: docker ... php script.php');
        }

        $xdebugArgs = $this->generateXdebugArguments();

        // Insert Xdebug arguments right after 'php' command
        // Need to insert as separate elements, not as a single array
        foreach (array_reverse($xdebugArgs) as $arg) {
            array_splice($parts, $phpIndex + 1, 0, [$arg]);
        }

        // Build command - Xdebug options are already properly formatted as -d flags
        // No need to escape them
        return implode(' ', $parts);
    }

    private function findPhpCommandIndex(array $parts): int|false
    {
        // Search for 'php' or 'php8.x' pattern
        // Look for the LAST occurrence to avoid matching container names
        $lastPhpIndex = false;

        foreach ($parts as $index => $part) {
            if ($part === 'php' || preg_match('/^php\d+\.\d+$/', $part)) {
                $lastPhpIndex = $index;
            }
        }

        return $lastPhpIndex;
    }

    private function generateXdebugArguments(): array
    {
        $args = [
            '-dxdebug.mode=' . $this->mode,
            '-dxdebug.start_with_request=yes',
            '-dxdebug.output_dir=/tmp',
            '-dxdebug.use_compression=0',
        ];

        // Add mode-specific options
        if ($this->mode === 'trace') {
            $args[] = '-dxdebug.trace_format=1';

            // Add prepend file for vendor filtering if specified
            if ($this->includeVendor !== null) {
                $prependFile = __DIR__ . '/../prepend_filter.php';
                $args[] = '-dprepend_file=' . $prependFile;
            }
        }

        // Merge with custom options
        return array_merge($args, $this->xdebugOptions);
    }
}
