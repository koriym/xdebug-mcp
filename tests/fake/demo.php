<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use XdebugMcp\Tests\Fake\FakeMcpServer;

echo "=== Xdebug MCP Server Demo ===\n\n";

$server = new FakeMcpServer();

// デモ用のテストケース
$demoSteps = [
    [
        'name' => '初期化',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => []
        ]
    ],
    [
        'name' => 'Xdebugに接続',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_connect',
                'arguments' => ['host' => '127.0.0.1', 'port' => 9003]
            ]
        ]
    ],
    [
        'name' => 'ブレークポイント設定',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_set_breakpoint',
                'arguments' => [
                    'filename' => __DIR__ . '/sample.php',
                    'line' => 5
                ]
            ]
        ]
    ],
    [
        'name' => 'ステップオーバー実行',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_step_over',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'スタックトレース取得',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_stack',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'ローカル変数取得',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_variables',
                'arguments' => ['context' => 0]
            ]
        ]
    ],
    [
        'name' => 'PHP式の評価',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_eval',
                'arguments' => ['expression' => '$sum']
            ]
        ]
    ],
    [
        'name' => 'ステップイン実行',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_step_into',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => '実行継続',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_continue',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => '配列の評価',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_eval',
                'arguments' => ['expression' => 'count($numbers)']
            ]
        ]
    ]
];

// リフレクションを使用してprivateメソッドにアクセス
$reflection = new ReflectionClass($server);
$handleRequestMethod = $reflection->getMethod('handleRequest');
$handleRequestMethod->setAccessible(true);

foreach ($demoSteps as $i => $step) {
    echo "--- ステップ " . ($i + 1) . ": {$step['name']} ---\n";
    
    try {
        $response = $handleRequestMethod->invoke($server, $step['request']);
        
        if (isset($response['result']['content'][0]['text'])) {
            echo $response['result']['content'][0]['text'] . "\n";
        } else {
            echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
        }
        
        echo "✅ 成功\n\n";
        
        // デモの進行を見やすくするため
        usleep(500000); // 0.5秒待機
        
    } catch (Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n\n";
    }
}

echo "=== デモ完了 ===\n";