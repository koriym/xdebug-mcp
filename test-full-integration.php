<?php
echo "=== 完全統合テスト ===\n";

require_once __DIR__ . "/vendor/autoload.php";

try {
    echo "1. DebugServerインスタンス作成...\n";
    $server = new \Koriym\XdebugMcp\DebugServer(
        'test-http-debug.php',
        9005, // Different port to avoid conflicts
        null,
        ["context" => "Full Integration Test"],
        false
    );
    
    echo "2. HTTPモード有効化（ポート8083）...\n";
    $server->enableHttpMode(8083);
    
    echo "3. HTTPサーバー開始...\n";
    echo "   アクセス: http://127.0.0.1:8083/debug/status\n";
    echo "   テスト用エンドポイント:\n";
    echo "   - /debug/status\n";
    echo "   - /debug/step\n";
    echo "   - /debug/variables\n";
    echo "   - /debug/continue\n";
    echo "   Ctrl+C で停止\n";
    echo "========================================\n";
    
    $server();
    
} catch (Throwable $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
}