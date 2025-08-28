<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Koriym\XdebugMcp\Tests\Fake\FakeMcpServer;

echo "=== Xdebug Profiling & Coverage Demo ===\n\n";

$server = new FakeMcpServer();

// プロファイリング & カバレッジ デモ用のテストケース
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
        'name' => 'プロファイリング開始',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_start_profiling',
                'arguments' => ['output_file' => '/tmp/demo_profile.out']
            ]
        ]
    ],
    [
        'name' => 'プロファイル情報取得',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_profile_info',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'プロファイリング停止',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_stop_profiling',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'プロファイル解析',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_analyze_profile',
                'arguments' => [
                    'profile_file' => '/tmp/demo_profile.out',
                    'top_functions' => 5
                ]
            ]
        ]
    ],
    [
        'name' => 'コードカバレッジ開始',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_start_coverage',
                'arguments' => [
                    'include_patterns' => ['src/*.php', 'tests/*.php'],
                    'track_unused' => true
                ]
            ]
        ]
    ],
    [
        'name' => 'カバレッジデータ取得',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_coverage',
                'arguments' => ['format' => 'summary']
            ]
        ]
    ],
    [
        'name' => 'カバレッジサマリー',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_coverage_summary',
                'arguments' => []
            ]
        ]
    ],
    [
        'name' => 'HTMLカバレッジレポート生成',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_analyze_coverage',
                'arguments' => [
                    'coverage_data' => [
                        '/app/src/User.php' => [1 => 1, 2 => 1, 3 => 0, 4 => 1, 5 => -1],
                        '/app/src/Product.php' => [1 => 1, 2 => 1, 3 => 1, 4 => 0, 5 => 0]
                    ],
                    'format' => 'html',
                    'output_file' => '/tmp/coverage_report.html'
                ]
            ]
        ]
    ],
    [
        'name' => 'コードカバレッジ停止',
        'request' => [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_stop_coverage',
                'arguments' => []
            ]
        ]
    ]
];

// 各ステップを実行
foreach ($demoSteps as $step) {
    echo "📊 {$step['name']}\n";
    echo str_repeat("-", 40) . "\n";

    // ReflectionMethodを使って、プライベートメソッドにアクセス
    $method = new ReflectionMethod($server, 'handleRequest');
    $method->setAccessible(true);
    
    try {
        $response = $method->invoke($server, $step['request']);
        
        if (isset($response['result']['content'][0]['text'])) {
            $result = $response['result']['content'][0]['text'];
            // 長すぎる出力は省略
            if (strlen($result) > 200) {
                echo substr($result, 0, 200) . "...\n";
            } else {
                echo $result . "\n";
            }
        } elseif (isset($response['result'])) {
            echo "✅ " . $step['name'] . " 成功\n";
        } else {
            echo "❌ エラー: " . ($response['error']['message'] ?? 'Unknown error') . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 例外: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "🎉 プロファイリング & カバレッジ デモ完了！\n\n";

echo "📈 機能概要:\n";
echo "- プロファイリング: 関数の実行時間・呼び出し回数を測定\n";
echo "- コードカバレッジ: 実行されたコード行を追跡\n";
echo "- レポート生成: HTML、XML、JSON、テキスト形式対応\n";
echo "- PHPUnit連携: テストカバレッジの自動計測\n";
echo "\n";
echo "💡 実際の使用時は php -dzend_extension=xdebug でXdebugを有効にしてください\n";