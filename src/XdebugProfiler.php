<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp;

use InvalidArgumentException;
use RuntimeException;

use function array_map;
use function array_merge;
use function end;
use function escapeshellarg;
use function explode;
use function file_exists;
use function file_get_contents;
use function filemtime;
use function filesize;
use function glob;
use function implode;
use function ini_get;
use function is_readable;
use function json_encode;
use function passthru;
use function round;
use function shell_exec;
use function str_contains;
use function strpos;
use function substr;
use function substr_count;
use function usort;

/**
 * Xdebug Cachegrind profile analyzer
 *
 * Executes PHP scripts with Xdebug profiling enabled and analyzes Cachegrind output.
 * Provides performance statistics and bottleneck identification from profile data.
 */
class XdebugProfiler
{
    public function executeProfile(string $targetFile, array $phpArgs = [], bool $jsonOutput = false): string
    {
        if (! file_exists($targetFile)) {
            throw new InvalidArgumentException("Target file not found: $targetFile");
        }

        // Get Xdebug output directory
        $xdebugOutputDir = ini_get('xdebug.output_dir') ?: '/tmp';

        if (! $jsonOutput) {
            echo "📊 Profiling: $targetFile\n";
        }

        // Build command with Xdebug profiling enabled
        $xdebugOptions = [
            '-dzend_extension=xdebug',
            '-dxdebug.mode=profile',
            '-dxdebug.start_with_request=yes',
            "-dxdebug.output_dir={$xdebugOutputDir}",
            '-dxdebug.use_compression=0',
        ];

        // Combine all arguments
        $allArgs = array_merge($xdebugOptions, [$targetFile], $phpArgs);
        $cmd = 'php ' . implode(' ', array_map('escapeshellarg', $allArgs));

        // Execute with passthru to show output
        $exitCode = 0;
        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("PHP execution failed with exit code: $exitCode");
        }

        // Find the created profile file (Xdebug generates its own filename)
        $profileFiles = glob("{$xdebugOutputDir}/cachegrind.out.*");
        if (empty($profileFiles)) {
            throw new RuntimeException('Profile file not created. Check Xdebug installation.');
        }

        // Get the most recent profile file
        usort($profileFiles, static function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $profileFiles[0];
    }

    public function parseProfileFile(string $profileFile): array
    {
        if (! file_exists($profileFile) || ! is_readable($profileFile)) {
            throw new InvalidArgumentException("Profile file not found or not readable: $profileFile");
        }

        $fileSize = filesize($profileFile);
        if ($fileSize === 0) {
            throw new RuntimeException("Profile file is empty: $profileFile");
        }

        $content = file_get_contents($profileFile);

        // Parse Cachegrind format
        $stats = [
            'file_path' => $profileFile,
            'file_size' => $fileSize,
            'functions_count' => substr_count($content, "\nfn="),
            'calls_count' => substr_count($content, "\ncalls="),
            'target_file' => '',
            'creator' => '',
            'command' => '',
        ];

        // Extract header information
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (strpos($line, 'creator: ') === 0) {
                $stats['creator'] = substr($line, 9);
            } elseif (strpos($line, 'cmd: ') === 0) {
                $stats['command'] = substr($line, 5);
                // Extract target file from command
                $parts = explode(' ', $stats['command']);
                $stats['target_file'] = end($parts);
            }
        }

        return $stats;
    }

    public function generateStatistics(array $stats): array
    {
        $fileSize = $stats['file_size'];
        $sizeFormatted = $fileSize > 1024 ? round($fileSize / 1024, 1) . 'K' : $fileSize . 'B';

        return [
            'profile_file' => $stats['file_path'],
            'file_size_bytes' => $fileSize,
            'file_size_formatted' => $sizeFormatted,
            'functions_count' => $stats['functions_count'],
            'calls_count' => $stats['calls_count'],
            'target_file' => $stats['target_file'],
            'creator' => $stats['creator'],
        ];
    }

    public function displayResults(array $stats, bool $jsonOutput = false): void
    {
        if ($jsonOutput) {
            echo json_encode($stats);
        } else {
            echo "✅ Profile complete: {$stats['profile_file']}\n";
            echo "📊 Size: {$stats['file_size_formatted']}\n";

            if ($stats['functions_count'] > 0) {
                echo "📈 Functions: {$stats['functions_count']}\n";
                echo "📞 Calls: {$stats['calls_count']}\n";
            }

            echo "\n💡 Analyze with Claude Code:\n";
            echo "   claude \"Analyze {$stats['profile_file']}\"\n";
            echo "\n💡 Or use KCachegrind/qcachegrind:\n";
            echo "   kcachegrind {$stats['profile_file']}\n";
        }
    }

    public function analyzeWithClaude(string $profileFile): void
    {
        $localeOutput = shell_exec('defaults read -g AppleLocale') ?: '';
        $lang = str_contains($localeOutput, 'ja_JP') ? 'Japanese' : 'English';
        $claudePrompt = "Analyze this Cachegrind profile file for performance bottlenecks, slow functions, and optimization opportunities. Provide specific recommendations for improving performance: $profileFile. Answer in $lang.";

        echo "\n🤖 Starting Claude Code analysis...\n";
        passthru('claude ' . escapeshellarg($claudePrompt));
        echo "\n";
        echo "🤖 Claude Code analysis completed. 'claude --continue' if you have follow-up questions.\n";
    }
}
