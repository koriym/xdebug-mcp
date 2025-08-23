#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Claude Natural Language Session Testing Script
 *
 * This script simulates natural language instructions to Claude for testing
 * the new global session management functionality in Xdebug MCP.
 *
 * Usage: php tests/claude/run_session_test.php
 */

// ANSI color codes for better output
const GREEN = "\033[32m";
const RED = "\033[31m";
const BLUE = "\033[34m";
const YELLOW = "\033[33m";
const CYAN = "\033[36m";
const RESET = "\033[0m";
const BOLD = "\033[1m";

class SessionTestRunner
{
    private string $mcpServerPath;
    private array $testResults = [];

    public function __construct()
    {
        $this->mcpServerPath = __DIR__ . '/../../bin/xdebug-mcp';
    }

    public function run(): void
    {
        echo BOLD . BLUE . "🧪 Claude Natural Language Session Testing\n" . RESET;
        echo CYAN . "Testing global session management with natural language instructions\n" . RESET;
        echo str_repeat('=', 60) . "\n\n";

        $this->runTestScenario();
        $this->showResults();
    }

    private function runTestScenario(): void
    {
        $scenarios = [
            [
                'title' => 'Session Initialization',
                'instruction' => 'demo1.phpのxdebugセッションを開始します。',
                'expected' => 'セッション開始の確認',
                'mcp_call' => 'xdebug_connect',
                'args' => ['host' => '127.0.0.1', 'port' => 9004],
            ],
            [
                'title' => 'Set Breakpoint in Method foo',
                'instruction' => 'メソッドfooの最初の行（line 18）にbreakpointを設定してください',
                'expected' => 'ブレークポイント設定の確認',
                'mcp_call' => 'xdebug_set_breakpoint',
                'args' => ['filename' => __DIR__ . '/demo1.php', 'line' => 18],
            ],
            [
                'title' => 'Set Additional Breakpoint',
                'instruction' => 'line 50にもbreakpointを設定してください',
                'expected' => '追加ブレークポイント設定の確認',
                'mcp_call' => 'xdebug_set_breakpoint',
                'args' => ['filename' => __DIR__ . '/demo1.php', 'line' => 50],
            ],
            [
                'title' => 'List Active Breakpoints',
                'instruction' => '現在設定されているbreakpointをすべて教えてください',
                'expected' => 'アクティブなブレークポイント一覧',
                'mcp_call' => 'xdebug_list_breakpoints',
                'args' => [],
            ],
            [
                'title' => 'Session Reconnection Test',
                'instruction' => 'セッションを再接続して状態が保持されているか確認してください',
                'expected' => 'セッション状態の保持確認',
                'mcp_call' => 'xdebug_connect',
                'args' => ['host' => '127.0.0.1', 'port' => 9004],
            ],
            [
                'title' => 'Get Stack Information',
                'instruction' => '現在のスタック情報を教えてください',
                'expected' => 'スタック情報の取得',
                'mcp_call' => 'xdebug_get_stack',
                'args' => [],
            ],
            [
                'title' => 'Variable Inspection',
                'instruction' => 'breakしたときの主なローカル変数を教えてください',
                'expected' => 'ローカル変数の一覧',
                'mcp_call' => 'xdebug_get_variables',
                'args' => ['context' => 0],
            ],
            [
                'title' => 'Expression Evaluation',
                'instruction' => '$tempという変数の値を評価してください',
                'expected' => '変数評価結果',
                'mcp_call' => 'xdebug_eval',
                'args' => ['expression' => '$temp'],
            ],
        ];

        foreach ($scenarios as $i => $scenario) {
            $this->runSingleTest($i + 1, $scenario);
            sleep(1); // Brief pause between tests
        }
    }

    private function runSingleTest(int $testNumber, array $scenario): void
    {
        echo BOLD . "Test {$testNumber}: {$scenario['title']}\n" . RESET;
        echo YELLOW . 'Claude指示: ' . RESET . "「{$scenario['instruction']}」\n";
        echo CYAN . '期待結果: ' . RESET . "{$scenario['expected']}\n";
        echo BLUE . 'MCPコール: ' . RESET . "{$scenario['mcp_call']}\n";

        $result = $this->executeMcpCall($scenario['mcp_call'], $scenario['args']);

        if ($result['success']) {
            echo GREEN . "✅ 実行成功\n" . RESET;
            if (! empty($result['response'])) {
                $this->displayResponse($result['response']);
            }

            $this->testResults[$testNumber] = ['status' => 'success', 'scenario' => $scenario['title']];
        } else {
            echo RED . "❌ 実行失敗: {$result['error']}\n" . RESET;
            $this->testResults[$testNumber] = ['status' => 'failed', 'scenario' => $scenario['title'], 'error' => $result['error']];
        }

        echo str_repeat('-', 50) . "\n\n";
    }

    private function executeMcpCall(string $toolName, array $args): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => rand(1000, 9999),
            'method' => 'tools/call',
            'params' => [
                'name' => $toolName,
                'arguments' => $args,
            ],
        ];

        $jsonRequest = json_encode($request);

        // Execute with timeout to prevent hanging
        $command = sprintf(
            'echo %s | gtimeout 10s php %s 2>&1',
            escapeshellarg($jsonRequest),
            escapeshellarg($this->mcpServerPath),
        );

        $output = shell_exec($command);

        if ($output === null) {
            return ['success' => false, 'error' => 'Command execution failed'];
        }

        // Filter out the "Xdebug MCP Server started." message
        $lines = explode("\n", trim($output));
        $jsonOutput = '';
        foreach ($lines as $line) {
            if (trim($line) && ! str_contains($line, 'Xdebug MCP Server started')) {
                $jsonOutput = trim($line);
                break;
            }
        }

        if (empty($jsonOutput)) {
            return ['success' => false, 'error' => 'No valid JSON response received'];
        }

        $response = json_decode($jsonOutput, true);

        if ($response === null) {
            return ['success' => false, 'error' => 'Invalid JSON response: ' . $jsonOutput];
        }

        if (isset($response['error'])) {
            return ['success' => false, 'error' => $response['error']['message'] ?? 'Unknown error'];
        }

        return ['success' => true, 'response' => $response['result'] ?? null];
    }

    private function displayResponse($response): void
    {
        echo CYAN . 'レスポンス: ' . RESET;
        if (is_string($response)) {
            echo $response . "\n";
        } else {
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }

    private function showResults(): void
    {
        echo BOLD . BLUE . "📊 テスト結果サマリー\n" . RESET;
        echo str_repeat('=', 60) . "\n";

        $successCount = 0;
        $failedCount = 0;

        foreach ($this->testResults as $testNum => $result) {
            if ($result['status'] === 'success') {
                echo GREEN . "✅ Test {$testNum}: {$result['scenario']}\n" . RESET;
                $successCount++;
            } else {
                echo RED . "❌ Test {$testNum}: {$result['scenario']} - {$result['error']}\n" . RESET;
                $failedCount++;
            }
        }

        echo "\n" . BOLD;
        echo GREEN . "成功: {$successCount}" . RESET . BOLD . ' / ';
        echo RED . "失敗: {$failedCount}" . RESET . BOLD . ' / ';
        echo '合計: ' . ($successCount + $failedCount) . RESET . "\n";

        if ($failedCount === 0) {
            echo "\n" . GREEN . BOLD . "🎉 すべてのテストが成功しました！グローバルセッション管理が正常に動作しています。\n" . RESET;
        } else {
            echo "\n" . YELLOW . BOLD . "⚠️  一部のテストが失敗しました。デバッグセッションが実行中でない可能性があります。\n" . RESET;
            echo CYAN . "💡 実際のデバッグセッションでテストするには、以下を実行してください:\n" . RESET;
            echo "   Terminal 1: php -dxdebug.mode=debug -dxdebug.start_with_request=yes -dxdebug.client_port=9004 tests/claude/demo1.php\n";
            echo "   Terminal 2: php tests/claude/run_session_test.php\n";
        }

        echo "\n" . BLUE . "グローバル状態ファイル確認:\n" . RESET;
        $stateFile = '/tmp/xdebug_session_global.json';
        if (file_exists($stateFile)) {
            echo GREEN . '✅ ' . RESET . "状態ファイルが存在: {$stateFile}\n";
            $stateContent = file_get_contents($stateFile);
            echo '内容: ' . $stateContent . "\n";
        } else {
            echo YELLOW . 'ℹ️  ' . RESET . "状態ファイルが存在しません（接続が確立されなかった）\n";
        }
    }
}

// Run the test
if (php_sapi_name() === 'cli') {
    $runner = new SessionTestRunner();
    $runner->run();
} else {
    echo "This script must be run from command line.\n";
    exit(1);
}
