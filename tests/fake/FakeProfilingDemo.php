<?php

namespace Koriym\XdebugMcp\Tests\Fake;

require_once __DIR__ . '/../../vendor/autoload.php';

use Koriym\XdebugMcp\Tests\Fake\FakeMcpServer;

/**
 * プロファイリングとカバレッジ機能のデモ
 */
class FakeProfilingDemo
{
    private FakeMcpServer $server;
    
    public function __construct()
    {
        $this->server = new FakeMcpServer();
    }
    
    public function run(): void
    {
        echo "=== Xdebug Profiling & Coverage Demo ===\n\n";
        
        $this->demonstrateProfiling();
        echo "\n" . str_repeat("=", 50) . "\n\n";
        $this->demonstrateCoverage();
    }
    
    private function demonstrateProfiling(): void
    {
        echo "📊 プロファイリング機能のデモ\n\n";
        
        // プロファイリング開始
        $this->executeDemo('プロファイリング開始', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_start_profiling',
                'arguments' => ['output_file' => '/tmp/demo_profile.out']
            ]
        ]);
        
        // サンプルコード実行のシミュレーション
        echo "サンプルPHPコードを実行中...\n";
        $this->simulateHeavyFunction();
        
        // プロファイリング停止
        $this->executeDemo('プロファイリング停止', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_stop_profiling',
                'arguments' => []
            ]
        ]);
        
        // プロファイル情報取得
        $this->executeDemo('プロファイル情報取得', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_profile_info',
                'arguments' => []
            ]
        ]);
    }
    
    private function demonstrateCoverage(): void
    {
        echo "📈 コードカバレッジ機能のデモ\n\n";
        
        // コードカバレッジ開始
        $this->executeDemo('コードカバレッジ追跡開始', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_start_coverage',
                'arguments' => [
                    'include_patterns' => ['src/*.php', 'tests/*.php'],
                    'track_unused' => true
                ]
            ]
        ]);
        
        // テスト実行のシミュレーション
        echo "テスト実行をシミュレーション中...\n";
        $this->simulateTestExecution();
        
        // カバレッジデータ取得
        $this->executeDemo('カバレッジデータ取得', [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_get_coverage',
                'arguments' => ['format' => 'summary']
            ]
        ]);
        
        // カバレッジサマリー
        $this->executeDemo('カバレッジサマリー統計', [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_coverage_summary',
                'arguments' => [
                    'coverage_data' => $this->generateSampleCoverageData()
                ]
            ]
        ]);
        
        // HTMLレポート生成
        $this->executeDemo('HTMLカバレッジレポート生成', [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_analyze_coverage',
                'arguments' => [
                    'coverage_data' => $this->generateSampleCoverageData(),
                    'format' => 'html',
                    'output_file' => '/tmp/coverage_report.html'
                ]
            ]
        ]);
        
        // コードカバレッジ停止
        $this->executeDemo('コードカバレッジ追跡停止', [
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'xdebug_stop_coverage',
                'arguments' => []
            ]
        ]);
    }
    
    private function executeDemo(string $description, array $request): void
    {
        echo "🔧 {$description}\n";
        
        try {
            $response = $this->server->processRequest($request);
            
            if (isset($response['result']['content'][0]['text'])) {
                $result = $response['result']['content'][0]['text'];
                echo "✅ " . substr($result, 0, 100) . (strlen($result) > 100 ? '...' : '') . "\n";
            } else {
                echo "❌ エラー: " . ($response['error']['message'] ?? 'Unknown error') . "\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 例外: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function simulateHeavyFunction(): void
    {
        // 重い処理のシミュレーション
        for ($i = 0; $i < 1000000; $i++) {
            $temp = $i * 2;
        }
        echo "✅ サンプルコード実行完了\n\n";
    }
    
    private function simulateTestExecution(): void
    {
        // テスト実行のシミュレーション
        echo "  - UserTest::testCreateUser() ... OK\n";
        echo "  - UserTest::testUpdateUser() ... OK\n";
        echo "  - ProductTest::testCreateProduct() ... OK\n";
        echo "  - ProductTest::testDeleteProduct() ... SKIP\n";
        echo "✅ テスト実行完了\n\n";
    }
    
    private function generateSampleCoverageData(): array
    {
        return [
            '/app/src/User.php' => [
                1 => 1,  // 実行された
                2 => 1,  // 実行された
                3 => 0,  // 実行されなかった
                4 => 1,  // 実行された
                5 => -1, // 未使用コード
                6 => 1,  // 実行された
                7 => 0,  // 実行されなかった
                8 => 1   // 実行された
            ],
            '/app/src/Product.php' => [
                1 => 1,  // 実行された
                2 => 1,  // 実行された
                3 => 1,  // 実行された
                4 => 0,  // 実行されなかった
                5 => 0,  // 実行されなかった
                6 => 1   // 実行された
            ],
            '/app/src/Utils.php' => [
                1 => 1,  // 実行された
                2 => 0,  // 実行されなかった
                3 => 0,  // 実行されなかった
                4 => 0,  // 実行されなかった
                5 => 1   // 実行された
            ]
        ];
    }
}

// デモ実行
$demo = new FakeProfilingDemo();
$demo->run();