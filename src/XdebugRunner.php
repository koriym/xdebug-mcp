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
use function in_array;
use function passthru;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function trim;
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
    /**
     * Regex pattern to match PHP binary executables
     *
     * Matches 'php' optionally followed by version number, with optional .exe suffix.
     * Supports both Unix (/) and Windows (\) path separators.
     *
     * Note: This intentionally does NOT match php-fpm, php-cgi, or other PHP SAPI binaries,
     * as those are server processes not suitable for CLI script execution.
     */
    private const PHP_BINARY_PATTERN = '#(?:^|[\\\\/])php(?:[-@]?\d+(?:\.\d+)*)?(?:\.exe)?$#i';

    /** PHP CLI options that consume the following argument as their value. */
    private const PHP_OPTIONS_WITH_VALUE = ['-d', '-c', '-z', '-B', '-R', '-F', '-E'];

    /**
     * Long-form PHP CLI options that consume the following argument as their value.
     * These are the long aliases of {@see PHP_OPTIONS_WITH_VALUE}. The attached form
     * (e.g. "--define=foo=bar") needs no special handling; only the space-separated
     * form (e.g. "--define foo=bar") must skip the following value argument.
     */
    private const PHP_LONG_OPTIONS_WITH_VALUE = [
        '--define',
        '--php-ini',
        '--zend-extension',
        '--process-begin',
        '--process-code',
        '--process-file',
        '--process-end',
    ];

    /** PHP CLI options that execute inline source code instead of a file. */
    private const PHP_INLINE_CODE_OPTIONS = ['-r', '--run'];

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
            $command = $this->buildDockerCommand($this->commandParts); // @codeCoverageIgnore
        } else {
            $this->validateLocalFile($this->commandParts);
            $command = $this->buildLocalCommand($this->commandParts);
        }

        if (getenv('XDEBUG_RUNNER_DEBUG')) {
            fwrite(STDERR, "DEBUG: Executing command: $command\n"); // @codeCoverageIgnore
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
     * Check if the given path is a PHP binary
     *
     * Matches (Unix):
     * - php, php.exe
     * - php8.3, php8, php83
     * - php-8.3, php@8.3
     * - /usr/bin/php
     * - /opt/homebrew/opt/php@8.3/bin/php
     *
     * Matches (Windows):
     * - php.exe
     * - C:\php\php.exe
     * - C:\Program Files\php8.3\php.exe
     *
     * Does NOT match (intentionally excluded):
     * - php-fpm, php-cgi (server SAPIs, not CLI binaries)
     * - phpunit, phpcs, phpstan (PHP tools, not interpreters)
     * - script.php (PHP source files)
     */
    public static function isPhpBinary(string $path): bool
    {
        return preg_match(self::PHP_BINARY_PATTERN, $path) === 1;
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

        // Skip PHP binary if present (handles full paths like /usr/bin/php or php8.3)
        if (isset($workingParts[0]) && self::isPhpBinary($workingParts[0])) {
            array_shift($workingParts);
        }

        $targetFile = $this->findLocalFileArgument($workingParts);

        if ($targetFile !== null && $targetFile !== '' && ! file_exists($targetFile)) {
            throw new RuntimeException("File not found: '$targetFile'");
        }
    }

    /** @param string[] $parts Command parts after the PHP binary, if one was present. */
    private function findLocalFileArgument(array $parts): string|null
    {
        for ($index = 0; isset($parts[$index]); $index++) {
            $arg = $parts[$index];

            if (in_array($arg, self::PHP_INLINE_CODE_OPTIONS, true)) {
                if (! isset($parts[$index + 1])) {
                    throw new RuntimeException("Code argument is required after {$arg}");
                }

                return null;
            }

            if (str_starts_with($arg, '-r') && $arg !== '-r') {
                return null;
            }

            if (str_starts_with($arg, '--run=')) {
                return null;
            }

            if ($arg === '--') {
                return $parts[$index + 1] ?? '';
            }

            if (! str_starts_with($arg, '-')) {
                return $arg;
            }

            $takesValue = in_array($arg, self::PHP_OPTIONS_WITH_VALUE, true)
                || in_array($arg, self::PHP_LONG_OPTIONS_WITH_VALUE, true);
            if (! $takesValue || ! isset($parts[$index + 1])) {
                continue;
            }

            $index++;
        }

        return '';
    }

    /** @param string[] $parts */
    private function buildLocalCommand(array $parts): string
    {
        $workingParts = $parts;
        $phpBinary = 'php';

        // Use specified PHP binary if present (handles full paths like /usr/bin/php or php8.3)
        if (isset($workingParts[0]) && self::isPhpBinary($workingParts[0])) {
            $phpBinary = array_shift($workingParts);
        }

        $xdebugArgs = $this->generateXdebugArguments(true);
        $envPrefix = $this->includeVendor !== null
            ? sprintf('XDEBUG_MCP_INCLUDE_VENDOR=%s ', escapeshellarg($this->includeVendor))
            : '';

        return $envPrefix . escapeshellarg($phpBinary) . ' ' . implode(' ', $xdebugArgs) . ' ' . implode(' ', array_map(escapeshellarg(...), $workingParts));
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

        // Do not inject the local auto_prepend_file into containers. The host
        // path is not guaranteed to exist inside the container.
        $xdebugArgs = $this->generateXdebugArguments(false);

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
    private function generateXdebugArguments(bool $enableLocalVendorFilter): array
    {
        // Add zend_extension flag if Xdebug is not already loaded
        $xdebugFlag = XdebugFinder::getXdebugFlag();
        $args = [];

        if ($xdebugFlag !== '') {
            $args[] = trim($xdebugFlag); // @codeCoverageIgnore
        }

        $args = array_merge($args, [
            '-dxdebug.mode=' . $this->mode,
            '-dxdebug.start_with_request=yes',
            '-dxdebug.output_dir=' . $this->outputDir,
            '-dxdebug.use_compression=0',
        ]);

        // Add mode-specific options
        if ($this->mode === 'trace') {
            $args[] = '-dxdebug.trace_format=1';

            if ($enableLocalVendorFilter) {
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
