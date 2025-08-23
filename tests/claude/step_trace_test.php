#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Natural Language Step Tracing Test
 *
 * This test demonstrates how Claude can perform step-by-step debugging
 * through natural language commands using the global session management.
 */

// ANSI colors
const GREEN = "\033[32m";
const BLUE = "\033[34m";
const YELLOW = "\033[33m";
const CYAN = "\033[36m";
const RESET = "\033[0m";
const BOLD = "\033[1m";

class StepTraceTest
{
    private string $mcpServerPath;
    private int|null $mcpServerPid = null;

    public function __construct()
    {
        $this->mcpServerPath = __DIR__ . '/../../bin/xdebug-mcp';
    }

    public function run(): void
    {
        echo BOLD . BLUE . "🔍 Natural Language Step Tracing Test\n" . RESET;
        echo CYAN . "Testing step-by-step debugging with global session management\n" . RESET;
        echo str_repeat('=', 60) . "\n\n";

        // Start MCP server in background
        $this->startMcpServer();

        try {
            $this->runStepTraceScenario();
        } finally {
            // Clean up background MCP server
            $this->stopMcpServer();
        }
    }

    private function startMcpServer(): void
    {
        echo BLUE . "🚀 Starting MCP server in background...\n" . RESET;

        // Start MCP server in background and capture PID
        $command = sprintf('php %s > /dev/null 2>&1 & echo $!', escapeshellarg($this->mcpServerPath));
        $pid = (int) shell_exec($command);

        if ($pid > 0) {
            $this->mcpServerPid = $pid;
            echo GREEN . "✅ MCP server started (PID: {$pid})\n" . RESET;

            // Give server a moment to start listening
            sleep(2);
        } else {
            echo YELLOW . "⚠️  Could not start MCP server in background\n" . RESET;
        }
    }

    private function stopMcpServer(): void
    {
        if ($this->mcpServerPid !== null) {
            echo BLUE . "🛑 Stopping MCP server (PID: {$this->mcpServerPid})...\n" . RESET;

            // Kill the background MCP server
            shell_exec("kill {$this->mcpServerPid} 2>/dev/null");
            $this->mcpServerPid = null;

            echo GREEN . "✅ MCP server stopped\n" . RESET;
        }
    }

    private function runStepTraceScenario(): void
    {
        $scenarios = [
            [
                'step' => 1,
                'instruction' => 'グローバルセッション状態を初期化してください',
                'action' => 'clear_global_state',
                'expected' => 'セッション状態のクリア',
            ],
            [
                'step' => 2,
                'instruction' => 'demo1.phpのデバッグセッションを開始してください',
                'action' => 'connect',
                'args' => ['host' => '127.0.0.1', 'port' => 9004],
                'expected' => '新しいセッション開始または既存セッション再接続',
            ],
            [
                'step' => 3,
                'instruction' => 'もう一度接続して、グローバル状態が保持されているか確認してください',
                'action' => 'connect',
                'args' => ['host' => '127.0.0.1', 'port' => 9004],
                'expected' => 'セッション状態の再利用（reopened）',
            ],
            [
                'step' => 4,
                'instruction' => 'Calculator::fooメソッドの開始部分（18行目）にブレークポイントを設定してください',
                'action' => 'set_breakpoint',
                'args' => ['filename' => __DIR__ . '/demo1.php', 'line' => 18],
                'expected' => 'ブレークポイントID取得',
            ],
            [
                'step' => 5,
                'instruction' => 'fibonacci関数の再帰部分（40行目）にもブレークポイントを設定してください',
                'action' => 'set_breakpoint',
                'args' => ['filename' => __DIR__ . '/demo1.php', 'line' => 40],
                'expected' => '追加ブレークポイントID取得',
            ],
            [
                'step' => 6,
                'instruction' => '現在設定されているすべてのブレークポイントを表示してください',
                'action' => 'list_breakpoints',
                'args' => [],
                'expected' => 'アクティブブレークポイント一覧',
            ],
            [
                'step' => 7,
                'instruction' => 'プログラムの実行を開始してください（continue）',
                'action' => 'continue',
                'args' => [],
                'expected' => '実行継続またはブレークポイント停止',
            ],
            [
                'step' => 8,
                'instruction' => '現在のスタック情報を教えてください',
                'action' => 'get_stack',
                'args' => [],
                'expected' => 'コールスタック情報',
            ],
            [
                'step' => 9,
                'instruction' => '現在のスコープの変数をすべて表示してください',
                'action' => 'get_variables',
                'args' => ['context' => 0],
                'expected' => 'ローカル変数一覧',
            ],
            [
                'step' => 10,
                'instruction' => '$tempの値を評価してください',
                'action' => 'eval',
                'args' => ['expression' => '$temp'],
                'expected' => '変数の現在値',
            ],
            [
                'step' => 11,
                'instruction' => '次の行にステップイン（step into）してください',
                'action' => 'step_into',
                'args' => [],
                'expected' => '関数内部への移動',
            ],
            [
                'step' => 12,
                'instruction' => 'この行をステップオーバー（step over）してください',
                'action' => 'step_over',
                'args' => [],
                'expected' => '同レベルでの次行移動',
            ],
            [
                'step' => 13,
                'instruction' => 'グローバル状態ファイルの内容を確認してください',
                'action' => 'check_global_state',
                'args' => [],
                'expected' => 'セッション状態ファイルの内容表示',
            ],
        ];

        foreach ($scenarios as $scenario) {
            $this->executeStep($scenario);
            echo "\n";
            sleep(1);
        }

        $this->showFinalResults();
    }

    private function executeStep(array $scenario): void
    {
        echo BOLD . "Step {$scenario['step']}: " . RESET;
        echo YELLOW . "「{$scenario['instruction']}」\n" . RESET;
        echo CYAN . '期待結果: ' . RESET . "{$scenario['expected']}\n";

        switch ($scenario['action']) {
            case 'clear_global_state':
                $this->clearGlobalState();
                break;

            case 'connect':
                $this->executeConnect($scenario['args']);
                break;

            case 'set_breakpoint':
                $this->executeSetBreakpoint($scenario['args']);
                break;

            case 'list_breakpoints':
                $this->executeListBreakpoints();
                break;

            case 'continue':
                $this->executeContinue();
                break;

            case 'get_stack':
                $this->executeGetStack();
                break;

            case 'get_variables':
                $this->executeGetVariables($scenario['args']);
                break;

            case 'eval':
                $this->executeEval($scenario['args']);
                break;

            case 'step_into':
                $this->executeStepInto();
                break;

            case 'step_over':
                $this->executeStepOver();
                break;

            case 'check_global_state':
                $this->checkGlobalState();
                break;
        }

        echo str_repeat('-', 50);
    }

    private function clearGlobalState(): void
    {
        $stateFile = '/tmp/xdebug_session_global.json';
        if (file_exists($stateFile)) {
            unlink($stateFile);
            echo GREEN . "✅ グローバル状態ファイルをクリアしました\n" . RESET;
        } else {
            echo BLUE . "ℹ️  グローバル状態ファイルは存在しません\n" . RESET;
        }
    }

    private function executeConnect(array $args): void
    {
        $result = $this->callMcp('xdebug_connect', $args);
        if ($result['success']) {
            echo GREEN . '✅ 接続成功: ' . RESET;
            $response = $result['response'];
            if (is_string($response)) {
                echo $response . "\n";
            } else {
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo YELLOW . "⚠️  接続結果: {$result['error']}\n" . RESET;
        }
    }

    private function executeSetBreakpoint(array $args): void
    {
        $result = $this->callMcp('xdebug_set_breakpoint', $args);
        $this->displayMcpResult($result, 'ブレークポイント設定');
    }

    private function executeListBreakpoints(): void
    {
        $result = $this->callMcp('xdebug_list_breakpoints', []);
        $this->displayMcpResult($result, 'ブレークポイント一覧');
    }

    private function executeContinue(): void
    {
        $result = $this->callMcp('xdebug_continue', []);
        $this->displayMcpResult($result, '実行継続');
    }

    private function executeGetStack(): void
    {
        $result = $this->callMcp('xdebug_get_stack', []);
        $this->displayMcpResult($result, 'スタック情報');
    }

    private function executeGetVariables(array $args): void
    {
        $result = $this->callMcp('xdebug_get_variables', $args);
        $this->displayMcpResult($result, '変数情報');
    }

    private function executeEval(array $args): void
    {
        $result = $this->callMcp('xdebug_eval', $args);
        $this->displayMcpResult($result, '式評価');
    }

    private function executeStepInto(): void
    {
        $result = $this->callMcp('xdebug_step_into', []);
        $this->displayMcpResult($result, 'ステップイン');
    }

    private function executeStepOver(): void
    {
        $result = $this->callMcp('xdebug_step_over', []);
        $this->displayMcpResult($result, 'ステップオーバー');
    }

    private function checkGlobalState(): void
    {
        $stateFile = '/tmp/xdebug_session_global.json';
        if (file_exists($stateFile)) {
            echo GREEN . '✅ グローバル状態ファイル存在: ' . RESET . $stateFile . "\n";
            $content = file_get_contents($stateFile);
            echo CYAN . "内容:\n" . RESET;
            $decoded = json_decode($content, true);
            if ($decoded) {
                echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo $content . "\n";
            }
        } else {
            echo YELLOW . "⚠️  グローバル状態ファイルが存在しません\n" . RESET;
        }
    }

    private function callMcp(string $toolName, array $args): array
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

        // Send request to background MCP server (if running)
        if ($this->mcpServerPid !== null) {
            // For now, still use individual process calls
            // Background server communication would need socket/HTTP implementation
        }

        $command = sprintf(
            'echo %s | gtimeout 8s php %s 2>&1',
            escapeshellarg($jsonRequest),
            escapeshellarg($this->mcpServerPath),
        );

        $output = shell_exec($command);

        if ($output === null) {
            return ['success' => false, 'error' => 'Command execution failed'];
        }

        // Filter out server startup message
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

    private function displayMcpResult(array $result, string $operation): void
    {
        if ($result['success']) {
            echo GREEN . "✅ {$operation}成功: " . RESET;
            if (is_string($result['response'])) {
                echo $result['response'] . "\n";
            } else {
                echo json_encode($result['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo YELLOW . "⚠️  {$operation}: {$result['error']}\n" . RESET;
        }
    }

    private function showFinalResults(): void
    {
        echo BOLD . BLUE . "\n🏁 ステップトレーステスト完了\n" . RESET;
        echo CYAN . "グローバルセッション状態管理の動作確認結果:\n" . RESET;

        $stateFile = '/tmp/xdebug_session_global.json';
        if (file_exists($stateFile)) {
            echo GREEN . "✅ セッション状態が正常に管理されています\n" . RESET;
            echo "状態ファイル: {$stateFile}\n";
        } else {
            echo YELLOW . "ℹ️  セッション状態ファイルは作成されませんでした\n" . RESET;
            echo "（実際のデバッグセッションが実行されていない場合の正常動作）\n";
        }

        echo "\n" . BLUE . "💡 実際のデバッグセッションでテストするには:\n" . RESET;
        echo "Terminal 1: php -dxdebug.mode=debug -dxdebug.client_port=9004 test/debug_session_test.php\n";
        echo "Terminal 2: php tests/claude/step_trace_test.php\n";
    }
}

// Run the test
if (php_sapi_name() === 'cli') {
    $test = new StepTraceTest();
    $test->run();
} else {
    echo "This script must be run from command line.\n";
    exit(1);
}
