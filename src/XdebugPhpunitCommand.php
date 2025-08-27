<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use RuntimeException;
use SplFileObject;

use function array_map;
use function array_merge;
use function array_shift;
use function array_slice;
use function basename;
use function escapeshellarg;
use function filemtime;
use function filesize;
use function fwrite;
use function getenv;
use function glob;
use function implode;
use function in_array;
use function passthru;
use function preg_match;
use function putenv;
use function register_shutdown_function;
use function round;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function substr;
use function usort;

use const PHP_INT_MAX;
use const STDERR;

class XdebugPhpunitCommand
{
    private PhpunitConfigManager $configManager;
    private string $projectRoot;
    private string $phpunitBin;
    private string $xdebugOutputDir;
    private string $xdebugOutputDirEscaped;
    private bool $verbose = false;
    private bool $dryRun = false;
    private string $mode = 'trace'; // trace or profile

    /** @var array<string> */
    private array $phpunitArgs = [];

    /** @var array<string> */
    private array $tempFiles = [];

    public function __construct(string $projectRoot, string $xdebugOutputDir)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->phpunitBin = escapeshellarg($this->projectRoot . '/vendor/bin/phpunit');
        $this->xdebugOutputDir = rtrim($xdebugOutputDir, '/');
        $this->xdebugOutputDirEscaped = escapeshellarg($this->xdebugOutputDir);
    }

    /**
     * Parse command line arguments and execute
     *
     * @param array<string> $argv
     */
    public function __invoke(array $argv): int
    {
        try {
            $this->parseArguments($argv);
            $this->configManager = new PhpunitConfigManager($this->projectRoot, $this->verbose);

            register_shutdown_function([$this, 'cleanup']);

            if ($this->dryRun) {
                return $this->showConfig();
            }

            return $this->executePhpunit();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 70; // EX_SOFTWARE
        }
    }

    /**
     * Parse command line arguments
     *
     * @param array<string> $argv
     */
    private function parseArguments(array $argv): void
    {
        $scriptName = array_shift($argv) ?? 'xdebug-phpunit';

        if (empty($argv) || in_array($argv[0], ['--help', '-h'])) {
            $this->showHelp($scriptName);
            exit(0);
        }

        while (! empty($argv)) {
            $arg = array_shift($argv);

            switch ($arg) {
                case '--verbose':
                    $this->verbose = true;
                    break;

                case '--dry-run':
                    $this->dryRun = true;
                    break;

                case '--profile':
                    $this->mode = 'profile';
                    break;

                case '--trace':
                    $this->mode = 'trace';
                    break;

                case '--':
                    // Stop option parsing, remaining args are for PHPUnit
                    $this->phpunitArgs = array_merge($this->phpunitArgs, $argv);
                    break 2;

                default:
                    $this->phpunitArgs[] = $arg;
                    break;
            }
        }
    }

    /**
     * Auto-detect TRACE_TEST pattern from arguments
     */
    private function detectTracePattern(): string|null
    {
        $pattern = getenv('TRACE_TEST') ?: null;
        if ($pattern) {
            $this->log("Using TRACE_TEST environment variable: $pattern");

            return $pattern;
        }

        foreach ($this->phpunitArgs as $i => $arg) {
            // Class::method format
            if (str_contains($arg, '::')) {
                $this->log("Auto-detected method pattern: $arg");

                return $arg;
            }

            // --filter=pattern
            if (str_starts_with($arg, '--filter=')) {
                $filter = substr($arg, 9);
                $pattern = "*::$filter";
                $this->log("Auto-detected filter pattern: $pattern");

                return $pattern;
            }

            // --filter pattern (separate argument)
            if ($arg === '--filter' && isset($this->phpunitArgs[$i + 1])) {
                $filter = $this->phpunitArgs[$i + 1];
                $pattern = "*::$filter";
                $this->log("Auto-detected filter pattern: $pattern");

                return $pattern;
            }

            // Test file path
            if (preg_match('/(.+Test)\.php$/', $arg, $matches)) {
                $className = basename($matches[1]);
                $this->log("Auto-detected test file pattern: $className");

                return $className;
            }
        }

        return null;
    }

    /**
     * Show effective configuration and exit
     */
    private function showConfig(): int
    {
        $this->log('Showing effective PHPUnit configuration:');

        $originalConfig = $this->configManager->findConfigFile();
        $effectiveConfig = $this->configManager->getEffectiveConfig($originalConfig);

        echo "Effective PHPUnit configuration:\n";
        echo "================================\n";
        echo $effectiveConfig;

        if ($originalConfig) {
            echo "\nSource: $originalConfig\n";
        } else {
            echo "\nSource: Generated minimal config\n";
        }

        $pattern = $this->detectTracePattern();
        if ($pattern) {
            echo "TRACE_TEST pattern: $pattern\n";
            echo 'Analysis mode: ' . $this->mode . "\n";
        } else {
            echo "No TRACE_TEST pattern detected - would run PHPUnit normally\n";
        }

        return 0;
    }

    /**
     * Execute PHPUnit with dynamic configuration
     */
    private function executePhpunit(): int
    {
        $pattern = $this->detectTracePattern();

        if (! $pattern) {
            $this->log('No TRACE_TEST pattern detected, running PHPUnit normally');

            return $this->runPhpunitNormally();
        }

        // Set environment for TraceExtension
        $_ENV['TRACE_TEST'] = $pattern;
        putenv("TRACE_TEST=$pattern");

        echo "Tracing: $pattern\n";

        // Create config with TraceExtension
        $originalConfig = $this->configManager->findConfigFile();
        $effectiveConfig = $this->configManager->createConfigWithExtension($originalConfig);
        $this->tempFiles[] = $effectiveConfig;

        // Configure Xdebug
        $xdebugOpts = $this->getXdebugOptions();

        // Build command
        $cmd = $this->buildPhpunitCommand($effectiveConfig, $xdebugOpts);

        // Show the expanded command
        echo "Executing: $cmd\n";

        $this->log("Executing: $cmd");

        // Execute PHPUnit
        passthru($cmd, $exitCode);

        // Report generated files
        $this->reportGeneratedFiles();

        return $exitCode;
    }

    /**
     * Run PHPUnit without Xdebug (normal mode)
     */
    private function runPhpunitNormally(): int
    {
        $originalConfig = $this->configManager->findConfigFile();
        $configArg = $originalConfig ? '--configuration ' . escapeshellarg($originalConfig) : '';

        $cmd = "{$this->phpunitBin} $configArg " . implode(' ', array_map('escapeshellarg', $this->phpunitArgs));

        // Show the expanded command
        echo "Executing: $cmd\n";

        $this->log("Executing normal PHPUnit: $cmd");

        passthru($cmd, $exitCode);

        return $exitCode;
    }

    /**
     * Get Xdebug options based on mode
     */
    private function getXdebugOptions(): string
    {
        if ($this->mode === 'profile') {
            return '-dxdebug.mode=profile ' .
                   '-dxdebug.start_with_request=yes ' .
                   "-dxdebug.output_dir={$this->xdebugOutputDirEscaped} " .
                   '-dxdebug.profiler_output_name=cachegrind.out.%%s ' .
                   '-dxdebug.use_compression=0';
        }

        return '-dxdebug.mode=trace ' .
               '-dxdebug.trace_format=1 ' .
               '-dxdebug.use_compression=0 ' .
               '-dxdebug.collect_params=4 ' .
               '-dxdebug.collect_return=1 ' .
               "-dxdebug.output_dir={$this->xdebugOutputDirEscaped}";
    }

    /**
     * Build PHPUnit command
     */
    private function buildPhpunitCommand(string $configFile, string $xdebugOpts): string
    {
        $phpunitArgs = implode(' ', array_map('escapeshellarg', $this->phpunitArgs));

        return "php $xdebugOpts {$this->phpunitBin} --configuration " . escapeshellarg($configFile) . " $phpunitArgs";
    }

    /**
     * Report generated trace/profile files
     */
    private function reportGeneratedFiles(): void
    {
        if ($this->mode === 'profile') {
            $this->reportProfileFiles();

            return;
        }

        $this->reportTraceFiles();
    }

    /**
     * Report generated profile files
     */
    private function reportProfileFiles(): void
    {
        $files = glob($this->xdebugOutputDir . '/cachegrind.out.*');
        if (empty($files)) {
            echo "No profile files found (pattern may not have matched any tests)\n";

            return;
        }

        // Sort by modification time, newest first
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, 3);

        echo "\nProfile files generated:\n";
        foreach ($files as $file) {
            $size = $this->formatFileSize(filesize($file));
            echo "  $file ($size)\n";
        }

        echo "\nAnalyze profiles with:\n";
        echo '  ./vendor/bin/xdebug-profile ' . escapeshellarg($files[0]) . "\n";
        echo '  kcachegrind ' . escapeshellarg($files[0]) . "\n";
    }

    /**
     * Report generated trace files
     */
    private function reportTraceFiles(): void
    {
        $files = glob($this->xdebugOutputDir . '/trace_*.xt');
        if (empty($files)) {
            echo "No trace files found (pattern may not have matched any tests)\n";

            return;
        }

        // Sort by modification time, newest first
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, 3);

        echo "\nTrace files generated:\n";
        foreach ($files as $file) {
            $lines = $this->countFileLines($file);
            echo "  $file ($lines lines)\n";
        }

        echo "\nAnalyze traces with:\n";
        echo '  head -20 ' . escapeshellarg($files[0]) . "\n";
        echo "  grep 'function_name' " . escapeshellarg($files[0]) . "\n";
    }

    /**
     * Clean up temporary files
     */
    public function cleanup(): void
    {
        $this->configManager->cleanup($this->tempFiles);
    }

    /**
     * Show help information
     */
    private function showHelp(string $scriptName): void
    {
        echo "Usage: $scriptName [OPTIONS] [TEST_PATTERN] [-- PHPUNIT_ARGS...]\n\n";
        echo "AI-optimized PHPUnit with selective Xdebug analysis.\n";
        echo "Auto-injects TraceExtension (no manual phpunit.xml setup required).\n\n";
        echo "OPTIONS:\n";
        echo "  --dry-run         Show effective phpunit.xml and exit\n";
        echo "  --verbose         Show detailed operation logs\n";
        echo "  --profile         Profile mode (default: trace)\n";
        echo "  --trace           Trace mode (explicit)\n\n";
        echo "EXAMPLES:\n";
        echo "  $scriptName tests/UserTest.php::testLogin    # Trace specific method\n";
        echo "  $scriptName --profile tests/UserTest.php     # Profile entire test file\n";
        echo "  $scriptName --dry-run --filter=testAuth     # Show effective config\n\n";
        echo "PATTERN DETECTION:\n";
        echo "  UserTest.php::testLogin  → Trace specific method\n";
        echo "  --filter=testAuth       → Trace methods matching filter\n";
        echo "  tests/UserTest.php      → Trace entire test class\n";
        echo "  No pattern              → Run PHPUnit normally (no Xdebug)\n\n";
    }

    /**
     * Format file size for display
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . 'M';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . 'K';
        }

        return $bytes . 'B';
    }

    /**
     * Log message if verbose mode is enabled
     */
    private function log(string $message): void
    {
        if ($this->verbose) {
            fwrite(STDERR, "  $message\n");
        }
    }

    /**
     * Output error message
     */
    private function error(string $message): void
    {
        fwrite(STDERR, "Error: $message\n");
    }

    /**
     * Count lines in file efficiently without loading into memory
     */
    private function countFileLines(string $path): int
    {
        $file = new SplFileObject($path);
        $file->seek(PHP_INT_MAX);

        return $file->key() + 1;
    }
}
