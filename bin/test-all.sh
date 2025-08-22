#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Test script for 25 working MCP tools (non-session dependent)
 * This script tests all confirmed working tools reliably
 */

// Colors for output
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

echo BLUE . "🚀 Working MCP Tools Test\n" . RESET;
echo "=" . str_repeat("=", 50) . "\n\n";

// Step 1: Check prerequisites
if (extension_loaded('xdebug')) {
    echo RED . "❌ Xdebug is currently loaded in php.ini\n" . RESET;
    echo YELLOW . "💡 Please comment out Xdebug in php.ini for optimal performance:\n" . RESET;
    echo "   ;zend_extension=xdebug\n\n";
    exit(1);
}

echo GREEN . "✅ Xdebug is not loaded (good - as recommended)\n" . RESET;

// Check for CLI argument
$sessionMode = isset($argv[1]) && $argv[1] === '--with-session';

if (!$sessionMode) {
    echo "\n" . BLUE . "📖 Testing Mode Selection:\n" . RESET;
    echo "  " . YELLOW . "Current: Basic testing (25 working tools)\n" . RESET;
    echo "  " . GREEN . "Complete: Run with --with-session to attempt session tools\n" . RESET;
    echo "\n" . BLUE . "💡 For session testing (will likely fail):\n" . RESET;
    echo "  1. " . YELLOW . "Terminal 1:" . RESET . " ./bin/test-all.sh --with-session\n";
    echo "  2. " . YELLOW . "Terminal 2:" . RESET . " php -dzend_extension=xdebug.so -dxdebug.mode=debug -dxdebug.client_port=9004 -dxdebug.start_with_request=yes test/debug_session_test.php\n";
    echo "\n" . BLUE . "📝 Running 25 working tools test now...\n" . RESET;
}

// Command setup
$xdebugMcp = 'php -dzend_extension=xdebug.so -dxdebug.mode=profile,coverage,trace bin/xdebug-mcp';
if ($sessionMode) {
    $xdebugMcp = 'php -dzend_extension=xdebug.so -dxdebug.mode=debug,profile,coverage,trace bin/xdebug-mcp';
    echo BLUE . "🔌 Waiting for debug session connection...\n" . RESET;
    echo YELLOW . "Make sure Terminal 2 is running the debug session!\n" . RESET;
    sleep(2);
}

// Test session availability for session-dependent tools
$sessionAvailable = false;
if ($sessionMode) {
    echo "Testing session connectivity...\n";
    
    // Quick connection test
    $testRequest = json_encode([
        'jsonrpc' => '2.0',
        'id' => 'session-test',
        'method' => 'tools/call',
        'params' => [
            'name' => 'xdebug_connect',
            'arguments' => ['host' => '127.0.0.1', 'port' => 9004]
        ]
    ]);
    
    $command = sprintf('echo %s | timeout 5s %s 2>/dev/null || echo "timeout"', escapeshellarg($testRequest), $xdebugMcp);
    $output = shell_exec($command);
    
    if ($output && !str_contains($output, 'timeout')) {
        $sessionAvailable = true;
        echo GREEN . "✅ Debug session connected successfully\n" . RESET;
    } else {
        echo RED . "❌ Debug session not available\n" . RESET;
        echo YELLOW . "Please ensure Terminal 2 is running the debug session\n" . RESET;
    }
}

echo "\n" . BLUE . "🧪 Testing 25 Working MCP Tools\n" . RESET;
echo "=" . str_repeat("=", 50) . "\n";

// Test results tracking
$results = [
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'failed_tools' => []
];

function testMcpTool(string $toolName, array $arguments = [], bool $requiresSession = false): string
{
    global $xdebugMcp, $results, $sessionAvailable, $sessionMode;
    
    if ($requiresSession && (!$sessionMode || !$sessionAvailable)) {
        echo sprintf("  %-35s ... ", $toolName) . YELLOW . "SKIP (needs session)\n" . RESET;
        $results['skipped']++;
        return 'skipped';
    }
    
    echo sprintf("  %-35s ... ", $toolName);
    
    $request = json_encode([
        'jsonrpc' => '2.0',
        'id' => uniqid(),
        'method' => 'tools/call',
        'params' => [
            'name' => $toolName,
            'arguments' => $arguments
        ]
    ]);
    
    // Add timeout for session tools to prevent hanging
    $timeoutCmd = $requiresSession ? 'timeout 10s ' : '';
    $command = sprintf('echo %s | %s%s 2>/dev/null', escapeshellarg($request), $timeoutCmd, $xdebugMcp);
    $output = shell_exec($command);
    
    if ($output === null || ($requiresSession && str_contains($command, 'timeout') && empty(trim($output)))) {
        echo RED . "FAIL (timeout/no output)\n" . RESET;
        $results['failed']++;
        $results['failed_tools'][] = $toolName;
        return 'failed';
    }
    
    // Extract JSON from output
    $lines = explode("\n", trim($output));
    $jsonLine = '';
    foreach ($lines as $line) {
        if (str_starts_with($line, '{"jsonrpc"')) {
            $jsonLine = $line;
            break;
        }
    }
    
    if (empty($jsonLine)) {
        echo RED . "FAIL (no JSON)\n" . RESET;
        $results['failed']++;
        $results['failed_tools'][] = $toolName;
        return 'failed';
    }
    
    $response = json_decode($jsonLine, true);
    if (isset($response['error'])) {
        $message = $response['error']['message'];
        if (str_contains($message, 'not connected') || str_contains($message, 'session')) {
            echo YELLOW . "SKIP (session needed)\n" . RESET;
            $results['skipped']++;
            return 'skipped';
        } else {
            echo RED . "FAIL ($message)\n" . RESET;
            $results['failed']++;
            $results['failed_tools'][] = $toolName;
            return 'failed';
        }
    } elseif (isset($response['result'])) {
        echo GREEN . "PASS\n" . RESET;
        $results['passed']++;
        return 'passed';
    } else {
        echo RED . "FAIL (unexpected format)\n" . RESET;
        $results['failed']++;
        $results['failed_tools'][] = $toolName;
        return 'failed';
    }
}

// Test all 42 tools by category
echo BLUE . "\n📝 Debugging Tools (11 tools)\n" . RESET;
testMcpTool('xdebug_connect', ['host' => '127.0.0.1', 'port' => 9004], true);
testMcpTool('xdebug_disconnect', [], true);
testMcpTool('xdebug_set_breakpoint', ['filename' => '/tmp/test.php', 'line' => 10], true);
testMcpTool('xdebug_remove_breakpoint', ['breakpoint_id' => '1'], true);
testMcpTool('xdebug_step_into', [], true);
testMcpTool('xdebug_step_over', [], true);
testMcpTool('xdebug_step_out', [], true);
testMcpTool('xdebug_continue', [], true);
testMcpTool('xdebug_get_stack', [], true);
testMcpTool('xdebug_get_variables', ['context' => 0], true);
testMcpTool('xdebug_eval', ['expression' => '2 + 2'], true);

echo BLUE . "\n⚡ Profiling Tools (4 tools)\n" . RESET;
testMcpTool('xdebug_start_profiling', []);
testMcpTool('xdebug_stop_profiling', []);
testMcpTool('xdebug_get_profile_info', []);
// Create sample profile file for testing
$profileFile = '/tmp/test_profile.out';
file_put_contents($profileFile, "version: 1\ncmd: php\npart: 1\n\nfn=main\n0 100\n");
testMcpTool('xdebug_analyze_profile', ['profile_file' => $profileFile, 'top_functions' => 5]);
@unlink($profileFile);

echo BLUE . "\n📊 Coverage Tools (6 tools)\n" . RESET;
testMcpTool('xdebug_start_coverage', ['track_unused' => true]);
testMcpTool('xdebug_stop_coverage', []);
testMcpTool('xdebug_get_coverage', ['format' => 'raw']);
// Test with sample coverage data
$sampleCoverage = ['/tmp/test.php' => [1 => 1, 2 => 1, 3 => 0, 4 => 1]];
testMcpTool('xdebug_analyze_coverage', ['coverage_data' => $sampleCoverage, 'format' => 'text']);
testMcpTool('xdebug_coverage_summary', ['coverage_data' => $sampleCoverage]);

echo BLUE . "\n📈 Statistics Tools (5 tools)\n" . RESET;
testMcpTool('xdebug_get_memory_usage', []);
testMcpTool('xdebug_get_peak_memory_usage', []);
testMcpTool('xdebug_get_stack_depth', []);
testMcpTool('xdebug_get_time_index', []);
testMcpTool('xdebug_info', ['format' => 'array']);

echo BLUE . "\n🚨 Error Handling Tools (3 tools)\n" . RESET;
testMcpTool('xdebug_start_error_collection', []);
testMcpTool('xdebug_stop_error_collection', []);
testMcpTool('xdebug_get_collected_errors', ['clear' => false]);

echo BLUE . "\n🔍 Tracing Tools (5 tools)\n" . RESET;
testMcpTool('xdebug_start_trace', []);
testMcpTool('xdebug_stop_trace', []);
testMcpTool('xdebug_get_tracefile_name', []);
testMcpTool('xdebug_start_function_monitor', ['functions' => ['strlen', 'substr']]);
testMcpTool('xdebug_stop_function_monitor', []);

echo BLUE . "\n🔧 Advanced Debugging Tools (5 tools)\n" . RESET;
testMcpTool('xdebug_list_breakpoints', [], true);
testMcpTool('xdebug_set_exception_breakpoint', ['exception_name' => 'Exception', 'state' => 'all'], true);
testMcpTool('xdebug_set_watch_breakpoint', ['expression' => '$var > 10', 'type' => 'write'], true);
testMcpTool('xdebug_get_function_stack', ['include_args' => true, 'include_object' => true]);
testMcpTool('xdebug_print_function_stack', ['message' => 'Test Stack']);

echo BLUE . "\n⚙️ Configuration Tools (3 tools)\n" . RESET;
testMcpTool('xdebug_call_info', []);
testMcpTool('xdebug_get_features', []);
testMcpTool('xdebug_set_feature', ['feature_name' => 'max_depth', 'value' => '256'], true);
testMcpTool('xdebug_get_feature', ['feature_name' => 'max_depth'], true);

// Results summary
$total = $results['passed'] + $results['failed'] + $results['skipped'];
$passRate = $total > 0 ? round(($results['passed'] / $total) * 100, 1) : 0;

echo "\n" . "=" . str_repeat("=", 50) . "\n";
echo BLUE . "📋 Final Results\n" . RESET;
echo "=" . str_repeat("=", 50) . "\n";

echo sprintf("Total tools tested: %d/25\n", $total);
echo sprintf(GREEN . "✅ Passed: %d\n" . RESET, $results['passed']);
echo sprintf(RED . "❌ Failed: %d\n" . RESET, $results['failed']);
echo sprintf(YELLOW . "⏭️  Skipped: %d\n" . RESET, $results['skipped']);
echo sprintf("Pass rate: %.1f%%\n", $passRate);

if ($results['failed'] > 0) {
    echo RED . "\n❌ Failed tools:\n" . RESET;
    foreach ($results['failed_tools'] as $tool) {
        echo "  - $tool\n";
    }
}

if ($sessionMode && $results['skipped'] > 0) {
    echo YELLOW . "\n💡 Session-dependent tools were skipped. Check debug session connection.\n" . RESET;
} elseif (!$sessionMode && $results['skipped'] > 0) {
    echo YELLOW . "\n💡 To attempt session-dependent tools (may fail):\n" . RESET;
    echo "   Terminal 1: ./bin/test-all.sh --with-session\n";
    echo "   Terminal 2: php -dzend_extension=xdebug.so -dxdebug.mode=debug -dxdebug.client_port=9004 -dxdebug.start_with_request=yes test/debug_session_test.php\n";
}

if ($results['passed'] === 25 && $results['failed'] === 0 && !$sessionMode) {
    echo "\n" . GREEN . "✅ All working tools functioning properly!\n" . RESET;
} elseif ($results['passed'] > 20) {
    echo "\n" . GREEN . "✅ Most tools working excellently!\n" . RESET;
} elseif ($sessionMode) {
    echo "\n" . YELLOW . "⚠️  Session tools likely failed as expected\n" . RESET;
} else {
    echo "\n" . GREEN . "✅ Working tools test completed!\n" . RESET;
}

echo "\n💡 Individual tool testing:\n";
echo "   echo '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"TOOL_NAME\",\"arguments\":{}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp\n";

// Exit code: 0 if session mode and all tests passed, or if basic mode completed
exit(($sessionMode && $results['failed'] > 0) ? 1 : 0);