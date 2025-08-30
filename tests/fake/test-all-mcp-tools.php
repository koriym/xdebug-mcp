<?php
/**
 * Comprehensive MCP Tools Test Script
 * Tests all available MCP tools and generates response documentation
 */

declare(strict_types=1);

function sendMcpRequest(string $toolName, array $arguments = []): array
{
    $request = [
        'jsonrpc' => '2.0',
        'id' => rand(1, 1000),
        'method' => 'tools/call',
        'params' => [
            'name' => $toolName,
            'arguments' => $arguments,
        ],
    ];

    $requestJson = json_encode($request);
    
    // Execute MCP server with the request
    $command = sprintf('echo %s | php bin/xdebug-mcp', escapeshellarg($requestJson));
    $output = shell_exec($command);
    
    if (!$output) {
        return ['error' => ['message' => 'No output from MCP server']];
    }
    
    // Parse JSON response robustly: pick the last non-empty line
    $lines = array_values(array_filter(explode("\n", (string)$output), static fn($l) => trim($l) !== ''));
    $responseJson = $lines ? end($lines) : '';
    
    $response = json_decode($responseJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => [
            'message' => 'Invalid JSON response: ' . $responseJson,
            'decoder' => json_last_error_msg()
        ]];
    }
    
    return $response;
}

function testTool(string $toolName, array $arguments = [], string $description = ''): array
{
    echo "Testing: {$toolName}";
    if ($description) {
        echo " - {$description}";
    }
    echo "\n";
    
    $response = sendMcpRequest($toolName, $arguments);
    
    return [
        'tool' => $toolName,
        'arguments' => $arguments,
        'description' => $description,
        'response' => $response,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}

// List of all MCP tools to test
$toolsToTest = [
    // Session Management
    ['xdebug_list_sessions', [], 'List all active debugging sessions'],
    ['xdebug_connect', ['host' => '127.0.0.1', 'port' => 9004], 'Connect to Xdebug (will fail without active session)'],
    ['xdebug_disconnect', [], 'Disconnect from Xdebug session'],
    
    // Step Debugging (these will fail without active connection, but show response format)
    ['xdebug_step_into', [], 'Step into next function call'],
    ['xdebug_step_over', [], 'Step over current line'],
    ['xdebug_step_out', [], 'Step out of current function'],
    ['xdebug_continue', [], 'Continue execution until next breakpoint'],
    
    // Breakpoint Management
    ['xdebug_set_breakpoint', ['filename' => '/tmp/test.php', 'line' => 10], 'Set breakpoint at file:line'],
    ['xdebug_remove_breakpoint', ['breakpoint_id' => '1'], 'Remove breakpoint by ID'],
    ['xdebug_list_breakpoints', [], 'List all active breakpoints'],
    
    // Variable and Stack Inspection
    ['xdebug_get_stack', [], 'Get current stack trace'],
    ['xdebug_get_variables', ['context' => 0], 'Get variables in current context'],
    ['xdebug_eval', ['expression' => '2 + 2'], 'Evaluate PHP expression'],
    
    // Profiling
    ['xdebug_start_profiling', ['output_file' => '/tmp/profile.out'], 'Start performance profiling'],
    ['xdebug_stop_profiling', [], 'Stop profiling and get results'],
    ['xdebug_get_profile_info', [], 'Get profiling configuration'],
    
    // Code Coverage
    ['xdebug_start_coverage', ['track_unused' => true], 'Start code coverage tracking'],
    ['xdebug_stop_coverage', [], 'Stop code coverage tracking'],
    ['xdebug_get_coverage', ['format' => 'summary'], 'Get code coverage data'],
    ['xdebug_coverage_summary', ['coverage_data' => []], 'Get coverage statistics'],
    
    // Memory and Performance
    ['xdebug_get_memory_usage', [], 'Get current memory usage'],
    ['xdebug_get_peak_memory_usage', [], 'Get peak memory usage'],
    ['xdebug_get_stack_depth', [], 'Get current stack depth'],
    ['xdebug_get_time_index', [], 'Get execution time index'],
    
    // Configuration and Info
    ['xdebug_info', ['format' => 'array'], 'Get Xdebug configuration'],
    ['xdebug_get_features', [], 'Get all available features'],
    ['xdebug_set_feature', ['feature_name' => 'max_depth', 'value' => '100'], 'Set Xdebug feature'],
    ['xdebug_get_feature', ['feature_name' => 'max_depth'], 'Get specific feature value'],
    
    // Error Collection
    ['xdebug_start_error_collection', [], 'Start collecting PHP errors'],
    ['xdebug_stop_error_collection', [], 'Stop error collection'],
    ['xdebug_get_collected_errors', ['clear' => false], 'Get collected error messages'],
    
    // Function Tracing
    ['xdebug_start_trace', ['trace_file' => '/tmp/trace.xt'], 'Start function call tracing'],
    ['xdebug_stop_trace', [], 'Stop tracing and get data'],
    ['xdebug_get_tracefile_name', [], 'Get current trace filename'],
    
    // Function Monitoring
    ['xdebug_start_function_monitor', ['functions' => ['strlen', 'array_merge']], 'Monitor specific functions'],
    ['xdebug_stop_function_monitor', [], 'Stop function monitoring'],
    
    // Advanced Breakpoints
    ['xdebug_set_exception_breakpoint', ['exception_name' => 'Exception', 'state' => 'all'], 'Set exception breakpoint'],
    ['xdebug_set_watch_breakpoint', ['expression' => '$variable', 'type' => 'write'], 'Set watch breakpoint'],
    
    // Advanced Stack Information
    ['xdebug_get_function_stack', ['include_args' => true, 'limit' => 10], 'Get detailed function stack'],
    ['xdebug_print_function_stack', ['message' => 'Debug Stack'], 'Print formatted stack trace'],
    ['xdebug_call_info', [], 'Get calling context information'],
];

echo "=== MCP Tools Comprehensive Test ===\n";
echo "Testing " . count($toolsToTest) . " tools...\n\n";

$results = [];
$totalTests = count($toolsToTest);
$currentTest = 0;

foreach ($toolsToTest as [$toolName, $arguments, $description]) {
    $currentTest++;
    echo "[{$currentTest}/{$totalTests}] ";
    
    $result = testTool($toolName, $arguments, $description);
    $results[] = $result;
    
    // Show brief result
    if (isset($result['response']['error'])) {
        echo "  ❌ Error: " . $result['response']['error']['message'] . "\n";
    } elseif (isset($result['response']['result'])) {
        echo "  ✅ Success\n";
    } else {
        echo "  ⚠️ Unexpected response format\n";
    }
    
    echo "\n";
}

// Generate comprehensive markdown report
$markdownContent = generateMarkdownReport($results);

// Write the report
$reportFile = 'tests/fake/MCP_TOOLS_TEST_REPORT.md';
file_put_contents($reportFile, $markdownContent);

echo "=== Test Completed ===\n";
echo "Tested {$totalTests} tools\n";
echo "Report saved to: {$reportFile}\n";
echo "\nTo view the report:\n";
echo "cat {$reportFile}\n";
echo "# or\n";
echo "open {$reportFile}\n";

function generateMarkdownReport(array $results): string
{
    $markdown = "# MCP Tools Comprehensive Test Report\n\n";
    $markdown .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $markdown .= "Total tools tested: " . count($results) . "\n\n";
    
    // Summary table
    $markdown .= "## Summary\n\n";
    $markdown .= "| Tool | Status | Response Type |\n";
    $markdown .= "|------|--------|---------------|\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($results as $result) {
        $status = '❌';
        $responseType = 'Error';
        
        if (isset($result['response']['result'])) {
            $status = '✅';
            $responseType = 'Success';
            $successCount++;
        } elseif (isset($result['response']['error'])) {
            $errorCount++;
        }
        
        $markdown .= "| `{$result['tool']}` | {$status} | {$responseType} |\n";
    }
    
    $markdown .= "\n**Statistics:**\n";
    $markdown .= "- ✅ Successful: {$successCount}\n";
    $markdown .= "- ❌ Errors: {$errorCount}\n\n";
    
    // Detailed results
    $markdown .= "## Detailed Results\n\n";
    
    foreach ($results as $result) {
        $markdown .= "### `{$result['tool']}`\n\n";
        if ($result['description']) {
            $markdown .= "**Description:** {$result['description']}\n\n";
        }
        
        if (!empty($result['arguments'])) {
            $markdown .= "**Arguments:**\n```json\n" . json_encode($result['arguments'], JSON_PRETTY_PRINT) . "\n```\n\n";
        }
        
        $responseJson = json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $markdown .= "**Response:**\n```json\n" . maskLocalPaths((string)$responseJson) . "\n```\n\n";
        
        // Analysis
        if (isset($result['response']['result']['content'][0]['text'])) {
            $textContent = $result['response']['result']['content'][0]['text'];
            $markdown .= "**Response Analysis:**\n";
            
            // Try to parse as JSON if it looks like JSON
            if (str_starts_with(trim($textContent), '{') || str_starts_with(trim($textContent), '[')) {
                $parsed = json_decode($textContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $markdown .= "- Response contains JSON data\n";
                    $markdown .= "- Structure: " . getJsonStructure($parsed) . "\n";
                } else {
                    $markdown .= "- Response contains text with JSON-like format\n";
                }
            } else {
                $markdown .= "- Response contains plain text\n";
            }
        }
        
        $markdown .= "\n---\n\n";
    }
    
    // Response format analysis
    $markdown .= "## Response Format Analysis\n\n";
    $markdown .= "### JSON-only Responses\n";
    $jsonOnlyTools = [];
    $mixedFormatTools = [];
    
    foreach ($results as $result) {
        if (isset($result['response']['result']['content'][0]['text'])) {
            $textContent = $result['response']['result']['content'][0]['text'];
            if (str_starts_with(trim($textContent), '{') || str_starts_with(trim($textContent), '[')) {
                $parsed = json_decode($textContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $jsonOnlyTools[] = $result['tool'];
                } else {
                    $mixedFormatTools[] = $result['tool'];
                }
            } else {
                $mixedFormatTools[] = $result['tool'];
            }
        }
    }
    
    $markdown .= "**Pure JSON responses:**\n";
    foreach ($jsonOnlyTools as $tool) {
        $markdown .= "- `{$tool}`\n";
    }
    
    $markdown .= "\n**Mixed format responses (text + JSON):**\n";
    foreach ($mixedFormatTools as $tool) {
        $markdown .= "- `{$tool}`\n";
    }
    
    return $markdown;
}

function maskLocalPaths(string $text): string
{
    // Get current working directory for repo root replacement
    $repoRoot = getcwd();
    if ($repoRoot === false) {
        $repoRoot = __DIR__;
    }
    
    // Get user home directory
    $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';
    
    $patterns = [
        // Unix-style home directory paths
        '~' . preg_quote($homeDir, '~') . '~' => '{HOME}',
        // Windows-style home directory paths (C:\Users\username)
        '~' . preg_quote(str_replace('/', '\\', $homeDir), '~') . '~i' => '{HOME_WIN}',
        // Repository root paths
        '~' . preg_quote($repoRoot, '~') . '~' => '{REPO_ROOT}',
        // Windows-style repository root paths
        '~' . preg_quote(str_replace('/', '\\', $repoRoot), '~') . '~i' => '{REPO_ROOT}',
    ];
    
    $result = $text;
    foreach ($patterns as $pattern => $replacement) {
        $newResult = preg_replace($pattern, $replacement, $result);
        if ($newResult !== null) {
            $result = $newResult;
        }
    }
    
    return $result;
}

function getJsonStructure($data, int $depth = 0): string
{
    if ($depth > 2) return '...';
    
    if (is_array($data)) {
        if (empty($data)) return 'empty array';
        
        if (isset($data[0])) {
            return 'array[' . count($data) . ']';
        } else {
            $keys = array_keys($data);
            return 'object{' . implode(', ', array_slice($keys, 0, 3)) . (count($keys) > 3 ? ', ...' : '') . '}';
        }
    }
    
    return gettype($data);
}