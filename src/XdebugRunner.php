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
use function escapeshellarg;
use function file_exists;
use function filemtime;
use function fwrite;
use function getenv;
use function glob;
use function implode;
use function passthru;
use function usort;

use const STDERR;

/**
 * Unified command runner for Xdebug tools with Docker support
 *
 * Implements Strategy pattern to handle both local and containerized PHP execution.
 * Supports Docker, Podman, and Kubectl container commands.
 *
 * @example Local execution
 *   $runner = new XdebugRunner(['script', '--', 'php', 'test.php']);
 *   $runner->setMode('trace')->run();
 * @example Docker execution
 *   $runner = new XdebugRunner(['script', '--', 'docker', 'compose', 'run', '--rm', 'php', 'php', '/app/test.php']);
 *   $runner->setMode('profile')->run();
 */
class XdebugRunner
{
    private string $mode = 'trace';

    /** @var string[] */
    private array $commandParts;

    /** @var string[] */
    private array $xdebugOptions = [];
    private string|null $context = null;
    private string|null $includeVendor = null;
    private string $outputDir = '/tmp';

    /**
     * @param string[] $argv Command line arguments including '--' separator
     *
     * @throws RuntimeException If '--' separator is missing or no command provided.
     */
    public function __construct(array $argv)
    {
        $this->parseArguments($argv);
    }

    public function setMode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setContext(string|null $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getContext(): string|null
    {
        return $this->context;
    }

    public function setIncludeVendor(string|null $includeVendor): self
    {
        $this->includeVendor = $includeVendor;

        return $this;
    }

    public function getIncludeVendor(): string|null
    {
        return $this->includeVendor;
    }

    /** @param string[] $options Additional Xdebug options */
    public function setXdebugOptions(array $options): self
    {
        $this->xdebugOptions = $options;

        return $this;
    }

    public function setOutputDir(string $dir): self
    {
        $this->outputDir = $dir;

        return $this;
    }

    /**
     * Execute the command with Xdebug configuration
     *
     * @return int Exit code from the executed command
     */
    public function run(): int
    {
        if ($this->isDockerCommand($this->commandParts)) {
            $command = $this->buildDockerCommand($this->commandParts);
        } else {
            $this->validateLocalFile($this->commandParts);
            $command = $this->buildLocalCommand($this->commandParts);
        }

        if (getenv('XDEBUG_RUNNER_DEBUG')) {
            fwrite(STDERR, "DEBUG: Executing command: $command\n");
        }

        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Build the command string without executing (for testing)
     *
     * @return string The command that would be executed
     */
    public function buildCommand(): string
    {
        if ($this->isDockerCommand($this->commandParts)) {
            return $this->buildDockerCommand($this->commandParts);
        }

        $this->validateLocalFile($this->commandParts);

        return $this->buildLocalCommand($this->commandParts);
    }

    /**
     * Get the most recently generated trace file
     */
    public function getLatestTraceFile(): string|null
    {
        $traceFiles = glob($this->outputDir . '/trace.*.xt');
        if ($traceFiles === [] || $traceFiles === false) {
            return null;
        }

        usort($traceFiles, static fn ($a, $b): int => filemtime($b) - filemtime($a));

        return $traceFiles[0];
    }

    /**
     * Get the most recently generated profile file
     */
    public function getLatestProfileFile(): string|null
    {
        $profileFiles = glob($this->outputDir . '/cachegrind.out.*');
        if ($profileFiles === [] || $profileFiles === false) {
            return null;
        }

        usort($profileFiles, static fn ($a, $b): int => filemtime($b) - filemtime($a));

        return $profileFiles[0];
    }

    /**
     * Check if the command parts represent a Docker/container command
     *
     * @param string[] $parts
     */
    public function isDockerCommand(array $parts): bool
    {
        return ContainerHelper::isContainerCommand($parts);
    }

    /**
     * Get the command parts after parsing
     *
     * @return string[]
     */
    public function getCommandParts(): array
    {
        return $this->commandParts;
    }

    /**
     * @param string[] $argv
     *
     * @throws RuntimeException
     */
    private function parseArguments(array $argv): void
    {
        $separatorIndex = array_search('--', $argv, true);
        if ($separatorIndex === false) {
            throw new RuntimeException('Missing -- separator. Usage: script [options] -- command');
        }

        $this->commandParts = array_slice($argv, (int) $separatorIndex + 1);

        if ($this->commandParts === []) {
            throw new RuntimeException('Command is required after --');
        }
    }

    /**
     * @param string[] $parts
     *
     * @throws RuntimeException
     */
    private function validateLocalFile(array $parts): void
    {
        $workingParts = $parts;

        // Skip 'php' if present
        if (isset($workingParts[0]) && $workingParts[0] === 'php') {
            array_shift($workingParts);
        }

        $targetFile = $workingParts[0] ?? '';

        if ($targetFile !== '' && ! file_exists($targetFile)) {
            throw new RuntimeException("File not found: '$targetFile'");
        }
    }

    /** @param string[] $parts */
    private function buildLocalCommand(array $parts): string
    {
        $workingParts = $parts;

        // Skip 'php' if present
        if (isset($workingParts[0]) && $workingParts[0] === 'php') {
            array_shift($workingParts);
        }

        $xdebugArgs = $this->generateXdebugArguments();

        return 'php ' . implode(' ', $xdebugArgs) . ' ' . implode(' ', array_map(escapeshellarg(...), $workingParts));
    }

    /**
     * @param string[] $parts
     *
     * @throws RuntimeException
     */
    private function buildDockerCommand(array $parts): string
    {
        $phpIndex = $this->findPhpCommandIndex($parts);

        if ($phpIndex === false) {
            throw new RuntimeException(
                'PHP command not found in Docker command. Expected format: docker ... php script.php',
            );
        }

        $xdebugArgs = $this->generateXdebugArguments();

        // Insert Xdebug arguments right after 'php' command
        foreach (array_reverse($xdebugArgs) as $arg) {
            array_splice($parts, $phpIndex + 1, 0, [$arg]);
        }

        return implode(' ', $parts);
    }

    /**
     * Find the position of PHP command within Docker command
     *
     * @param string[] $parts
     *
     * @return int|false Position of PHP command or false if not found
     */
    public function findPhpCommandIndex(array $parts): int|false
    {
        return ContainerHelper::findPhpCommandIndex($parts);
    }

    /** @return string[] */
    private function generateXdebugArguments(): array
    {
        $args = [
            '-dxdebug.mode=' . $this->mode,
            '-dxdebug.start_with_request=yes',
            '-dxdebug.output_dir=' . $this->outputDir,
            '-dxdebug.use_compression=0',
        ];

        // Add mode-specific options
        if ($this->mode === 'trace') {
            $args[] = '-dxdebug.trace_format=1';

            if ($this->includeVendor !== null) {
                $prependFile = __DIR__ . '/prepend_filter.php';
                if (file_exists($prependFile)) {
                    $args[] = '-dauto_prepend_file=' . $prependFile;
                }
            }
        }

        if ($this->mode === 'profile') {
            $args[] = '-dxdebug.profiler_output_name=cachegrind.out.%p';
        }

        return array_merge($args, $this->xdebugOptions);
    }
}
