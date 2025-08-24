<?php

declare(strict_types=1);

namespace XdebugMcp\Testing;

use JsonException;

use function array_key_exists;
use function escapeshellarg;
use function explode;
use function extension_loaded;
use function file_put_contents;
use function is_array;
use function json_decode;
use function json_encode;
use function round;
use function shell_exec;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * MCP Tool Test Runner - Provides reusable testing functionality
 * Extracted from monolithic test-all.sh for better maintainability
 */
class McpTestRunner
{
    // Color constants
    private const string GREEN = "\033[32m";
    private const string RED = "\033[31m";
    private const string YELLOW = "\033[33m";
    private const string BLUE = "\033[34m";
    private const string RESET = "\033[0m";

    // Tool count constants for maintainability
    public const int TOTAL_WORKING_TOOLS = 25;
    public const int PROFILING_TOOLS = 4;
    public const int COVERAGE_TOOLS = 5;
    public const int STATISTICS_TOOLS = 6;
    public const int ERROR_COLLECTION_TOOLS = 3;
    public const int TRACING_TOOLS = 5;
    public const int CONFIGURATION_TOOLS = 2;

    private array $results = [
        'passed' => 0,
        'failed' => 0,
        'skipped' => 0,
        'failed_tools' => [],
    ];
    private string $xdebugMcp;
    private bool $sessionAvailable = false;

    public function __construct(private bool $sessionMode = false)
    {
        $this->setupXdebugCommand();
    }

    private function setupXdebugCommand(): void
    {
        if ($this->sessionMode) {
            $this->xdebugMcp = 'php -dzend_extension=xdebug.so -dxdebug.mode=debug,profile,coverage,trace bin/xdebug-mcp';
        } else {
            $this->xdebugMcp = 'php -dzend_extension=xdebug.so -dxdebug.mode=profile,coverage,trace bin/xdebug-mcp';
        }
    }

    public function checkPrerequisites(): bool
    {
        if (extension_loaded('xdebug')) {
            echo self::RED . "❌ Xdebug is currently loaded in php.ini\n" . self::RESET;
            echo self::YELLOW . "💡 Please comment out Xdebug in php.ini for optimal performance:\n" . self::RESET;
            echo "   ;zend_extension=xdebug\n\n";

            return false;
        }

        echo self::GREEN . "✅ Xdebug is not loaded (good - as recommended)\n" . self::RESET;

        return true;
    }

    public function testSessionConnectivity(): bool
    {
        if (! $this->sessionMode) {
            return false;
        }

        echo "Testing session connectivity...\n";

        $testRequest = json_encode([
            'jsonrpc' => '2.0',
            'id' => 'session-test',
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_connect',
                'arguments' => ['host' => '127.0.0.1', 'port' => 9004],
            ],
        ]);

        $command = sprintf('echo %s | timeout 5s %s 2>/dev/null || echo "timeout"', escapeshellarg($testRequest), $this->xdebugMcp);
        $output = shell_exec($command);

        // Trim output and check for timeout first
        $output = trim($output ?? '');

        if (str_contains($output, 'timeout')) {
            echo self::RED . "❌ Debug session not available (timeout)\n" . self::RESET;
            echo self::YELLOW . "Please ensure Terminal 2 is running the debug session\n" . self::RESET;

            return false;
        }

        if (empty($output)) {
            echo self::RED . "❌ Debug session not available (no output)\n" . self::RESET;
            echo self::YELLOW . "Please ensure Terminal 2 is running the debug session\n" . self::RESET;

            return false;
        }

        // Extract JSON line from output
        $lines = explode("\n", $output);
        $jsonLine = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, '{"jsonrpc"')) {
                $jsonLine = $line;
                break;
            }
        }

        if (empty($jsonLine)) {
            echo self::RED . "❌ Debug session not available (no valid JSON response)\n" . self::RESET;
            echo self::YELLOW . "Please ensure Terminal 2 is running the debug session\n" . self::RESET;

            return false;
        }

        // Attempt to decode JSON and validate response
        $response = json_decode($jsonLine, true);
        if ($response === null || ! is_array($response)) {
            echo self::RED . "❌ Debug session not available (invalid JSON)\n" . self::RESET;
            echo self::YELLOW . "Please ensure Terminal 2 is running the debug session\n" . self::RESET;

            return false;
        }

        // Check for error in response
        if (array_key_exists('error', $response)) {
            echo self::RED . "❌ Debug session not available (error: {$response['error']['message']})\n" . self::RESET;
            echo self::YELLOW . "Please ensure Terminal 2 is running the debug session\n" . self::RESET;

            return false;
        }

        // Check for result field indicating success (null result is valid in JSON-RPC)
        if (! array_key_exists('result', $response)) {
            echo self::RED . "❌ Debug session not available (no result field)\n" . self::RESET;
            echo self::YELLOW . "Please ensure Terminal 2 is running the debug session\n" . self::RESET;

            return false;
        }

        // All checks passed - session is available
        $this->sessionAvailable = true;
        echo self::GREEN . "✅ Debug session connected successfully\n" . self::RESET;

        return true;
    }

    public function testMcpTool(string $toolName, array $arguments = [], bool $requiresSession = false): string
    {
        if ($requiresSession && (! $this->sessionMode || ! $this->sessionAvailable)) {
            echo sprintf('  %-35s ... ', $toolName) . self::YELLOW . "SKIP (needs session)\n" . self::RESET;
            $this->results['skipped']++;

            return 'skipped';
        }

        echo sprintf('  %-35s ... ', $toolName);

        // Validate inputs
        if (empty($toolName)) {
            echo self::RED . "FAIL (invalid tool name)\n" . self::RESET;
            $this->results['failed']++;
            $this->results['failed_tools'][] = $toolName;

            return 'failed';
        }

        // Create JSON request with error handling
        try {
            $request = json_encode([
                'jsonrpc' => '2.0',
                'id' => uniqid(),
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => $arguments,
                ],
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            echo self::RED . 'FAIL (JSON encoding error: ' . $e->getMessage() . ")\n" . self::RESET;
            $this->results['failed']++;
            $this->results['failed_tools'][] = $toolName;

            return 'failed';
        }

        $timeoutCmd = $requiresSession ? 'timeout 10s ' : '';
        $command = sprintf('echo %s | %s%s 2>/dev/null', escapeshellarg($request), $timeoutCmd, $this->xdebugMcp);

        try {
            $output = shell_exec($command);
        } catch (Exception $e) {
            echo self::RED . 'FAIL (execution error: ' . $e->getMessage() . ")\n" . self::RESET;
            $this->results['failed']++;
            $this->results['failed_tools'][] = $toolName;

            return 'failed';
        }

        if ($output === null || ($requiresSession && str_contains($command, 'timeout') && empty(trim($output)))) {
            echo self::RED . "FAIL (timeout/no output)\n" . self::RESET;
            $this->results['failed']++;
            $this->results['failed_tools'][] = $toolName;

            return 'failed';
        }

        $lines = explode("\n", trim($output));
        $jsonLine = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, '{"jsonrpc"')) {
                $jsonLine = $line;
                break;
            }
        }

        if (empty($jsonLine)) {
            echo self::RED . "FAIL (no JSON)\n" . self::RESET;
            $this->results['failed']++;
            $this->results['failed_tools'][] = $toolName;

            return 'failed';
        }

        $response = json_decode($jsonLine, true);
        if (isset($response['error'])) {
            $message = $response['error']['message'];
            if (str_contains($message, 'not connected') || str_contains($message, 'session')) {
                echo self::YELLOW . "SKIP (session needed)\n" . self::RESET;
                $this->results['skipped']++;

                return 'skipped';
            }

            echo self::RED . "FAIL ($message)\n" . self::RESET;
            $this->results['failed']++;
            $this->results['failed_tools'][] = $toolName;

            return 'failed';
        }

        if (isset($response['result'])) {
            echo self::GREEN . "PASS\n" . self::RESET;
            $this->results['passed']++;

            return 'passed';
        }

        echo self::RED . "FAIL (unexpected format)\n" . self::RESET;
        $this->results['failed']++;
        $this->results['failed_tools'][] = $toolName;

        return 'failed';
    }

    public function runProfilingTools(): void
    {
        echo self::BLUE . "\n⚡ Profiling Tools (" . self::PROFILING_TOOLS . " tools)\n" . self::RESET;
        $this->testMcpTool('xdebug_start_profiling', []);
        $this->testMcpTool('xdebug_stop_profiling', []);
        $this->testMcpTool('xdebug_get_profile_info', []);

        // Create sample profile file for testing
        $profileFile = tempnam(sys_get_temp_dir(), 'test_profile_');
        if ($profileFile === false) {
            echo self::YELLOW . "  Warning: Could not create temporary profile file for testing\n" . self::RESET;
        } else {
            file_put_contents($profileFile, "version: 1\ncmd: php\npart: 1\n\nfn=main\n0 100\n");
            $this->testMcpTool('xdebug_analyze_profile', ['profile_file' => $profileFile, 'top_functions' => 5]);
            @unlink($profileFile);
        }
    }

    public function runCoverageTools(): void
    {
        echo self::BLUE . "\n📊 Coverage Tools (" . self::COVERAGE_TOOLS . " tools)\n" . self::RESET;
        $this->testMcpTool('xdebug_start_coverage', ['track_unused' => true]);
        $this->testMcpTool('xdebug_stop_coverage', []);
        $this->testMcpTool('xdebug_get_coverage', ['format' => 'raw']);

        // Test with sample coverage data
        $sampleCoverage = ['/tmp/test.php' => [1 => 1, 2 => 1, 3 => 0, 4 => 1]];
        $this->testMcpTool('xdebug_analyze_coverage', ['coverage_data' => $sampleCoverage, 'format' => 'text']);
        $this->testMcpTool('xdebug_coverage_summary', ['coverage_data' => $sampleCoverage]);
    }

    public function runStatisticsTools(): void
    {
        echo self::BLUE . "\n📈 Statistics Tools (" . self::STATISTICS_TOOLS . " tools)\n" . self::RESET;
        $this->testMcpTool('xdebug_get_memory_usage', []);
        $this->testMcpTool('xdebug_get_peak_memory_usage', []);
        $this->testMcpTool('xdebug_get_stack_depth', []);
        $this->testMcpTool('xdebug_get_time_index', []);
        $this->testMcpTool('xdebug_get_function_stack', []);
        $this->testMcpTool('xdebug_info', ['format' => 'array']);
    }

    public function runErrorCollectionTools(): void
    {
        echo self::BLUE . "\n🚨 Error Collection Tools (" . self::ERROR_COLLECTION_TOOLS . " tools)\n" . self::RESET;
        $this->testMcpTool('xdebug_start_error_collection', []);
        $this->testMcpTool('xdebug_stop_error_collection', []);
        $this->testMcpTool('xdebug_get_collected_errors', ['clear' => false]);
    }

    public function runTracingTools(): void
    {
        echo self::BLUE . "\n🔍 Tracing Tools (" . self::TRACING_TOOLS . " tools)\n" . self::RESET;
        $this->testMcpTool('xdebug_start_trace', []);
        $this->testMcpTool('xdebug_stop_trace', []);
        $this->testMcpTool('xdebug_get_tracefile_name', []);
        $this->testMcpTool('xdebug_start_function_monitor', ['functions' => ['strlen', 'substr']]);
        $this->testMcpTool('xdebug_stop_function_monitor', []);
    }

    public function runConfigurationTools(): void
    {
        echo self::BLUE . "\n⚙️ Configuration Tools (" . self::CONFIGURATION_TOOLS . " tools)\n" . self::RESET;
        $this->testMcpTool('xdebug_call_info', []);
        $this->testMcpTool('xdebug_print_function_stack', ['message' => 'Test Stack']);
    }

    public function runWorkingToolsTest(): void
    {
        echo self::BLUE . "\n🧪 Testing " . self::TOTAL_WORKING_TOOLS . " Working MCP Tools\n" . self::RESET;
        echo '=' . str_repeat('=', 50) . "\n";

        $this->runProfilingTools();
        $this->runCoverageTools();
        $this->runStatisticsTools();
        $this->runErrorCollectionTools();
        $this->runTracingTools();
        $this->runConfigurationTools();
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function printResults(): void
    {
        $total = $this->results['passed'] + $this->results['failed'] + $this->results['skipped'];
        $passRate = $total > 0 ? round($this->results['passed'] / $total * 100, 1) : 0;

        echo "\n" . '=' . str_repeat('=', 50) . "\n";
        echo self::BLUE . "📋 Final Results\n" . self::RESET;
        echo '=' . str_repeat('=', 50) . "\n";

        echo sprintf("Total tools tested: %d/%d\n", $total, self::TOTAL_WORKING_TOOLS);
        echo sprintf(self::GREEN . "✅ Passed: %d\n" . self::RESET, $this->results['passed']);
        echo sprintf(self::RED . "❌ Failed: %d\n" . self::RESET, $this->results['failed']);
        echo sprintf(self::YELLOW . "⏭️  Skipped: %d\n" . self::RESET, $this->results['skipped']);
        echo sprintf("Pass rate: %.1f%%\n", $passRate);

        if ($this->results['failed'] > 0) {
            echo self::RED . "\n❌ Failed tools:\n" . self::RESET;
            foreach ($this->results['failed_tools'] as $tool) {
                echo "  - $tool\n";
            }
        }

        if ($this->results['passed'] === self::TOTAL_WORKING_TOOLS && $this->results['failed'] === 0 && ! $this->sessionMode) {
            echo "\n" . self::GREEN . "✅ All working tools functioning properly!\n" . self::RESET;
        } elseif ($this->results['passed'] > 20) {
            echo "\n" . self::GREEN . "✅ Most tools working excellently!\n" . self::RESET;
        } else {
            echo "\n" . self::GREEN . "✅ Working tools test completed!\n" . self::RESET;
        }
    }
}
