# PHP Xdebug MCP サーバー

> AIが私たちのようにXdebugを使って開発

<img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

PHP Xdebug のデバッグ、プロファイル、カバレッジ分析をAIが制御できるMCPサーバーです。

## 機能

- **42個のXdebugツール**: デバッグ、プロファイル、カバレッジの完全自動化
- **トレースベースデバッグ**: AIが実行時データを分析（var_dump不要）
- **IDE互換性**: ポート9004でPhpStorm/VS Code（9003）との競合を回避
- **コマンドラインツール**: 6つの独立したデバッグユーティリティ

## ツールカテゴリ

- **デバッグ**: セッション管理、ブレークポイント、ステップ実行、変数検査
- **プロファイル**: パフォーマンス分析、関数タイミング、Cachegrind出力
- **カバレッジ**: 行/関数カバレッジ、HTML/XMLレポート、PHPUnit連携
- **拡張**: メモリ統計、エラー収集、トレース、高度なブレークポイント

## クイックスタート

```bash
# インストール
composer install

# サーバー起動
./bin/xdebug-server

# ツールテスト
./bin/xdebug-trace test/debug_test.php
./bin/xdebug-profile test/debug_test.php  
./bin/xdebug-coverage test/debug_test.php

# Claude Desktop連携
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp"
```

## セットアップ

```bash
composer install
```

**推奨: bin/xdebug-* コマンドを使用**
```bash
# 最良のアプローチ - ツールがXdebugを自動処理
./bin/xdebug-trace script.php
./bin/xdebug-profile script.php
./bin/xdebug-coverage script.php
```

**手動アプローチ（上記と同等）**
```bash
# binコマンドと同じだが手動
php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_port=9004 script.php
```

**php.ini: Xdebugをコメントアウト（パフォーマンス向上）**
```ini
# php.ini でパフォーマンス向上のためコメントアウト
;zend_extension=xdebug
# その他のXdebug設定はbin/xdebug-*コマンドで自動処理されます
```

### MCP設定

```bash
# Claude Desktop
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp"

# 確認
claude mcp list
```

## コマンドラインツール

- `xdebug-server` - MCPサーバー起動（ポート9004）
- `xdebug-mcp` - コアMCPサーバー 
- `xdebug-trace` - 実行トレース生成
- `xdebug-profile` - パフォーマンスプロファイル  
- `xdebug-coverage` - コードカバレッジ分析
- `xdebug-phpunit` - PHPUnit選択的Xdebug分析

### xdebug-phpunit 使用方法

PHPUnitテストで自動Xdebugトレース・プロファイルを実行：

```bash
# 特定テストメソッドのトレース（デフォルトモード）
./bin/xdebug-phpunit tests/UserTest.php::testLogin

# テストファイル全体のプロファイル
./bin/xdebug-phpunit --profile tests/UserTest.php

# フィルター条件でのトレース
./bin/xdebug-phpunit --filter=testUserAuth

# 遅いテストのプロファイル
./bin/xdebug-phpunit --profile --filter=testSlow
```

**出力:**
- トレースモード: `/tmp/trace_*.xt` (実行トレース)
- プロファイルモード: `/tmp/cachegrind.out.*` (パフォーマンスデータ)

**設定要件:**
`phpunit.xml`に追加：
```xml
<extensions>
    <bootstrap class="Koriym\XdebugMcp\TraceExtension"/>
</extensions>
```

## 使用例

### 1. 実行トレース
```bash
claude --print "test/debug_test.phpを実行して実行パターンを分析して"
# AIが自動的に./bin/xdebug-traceを選択して分析を提供：
# ✅ トレース完了: /tmp/xdebug_trace_20250821_044930.xt (64行)
# 📊 分析: O(2^n) Fibonacci非効率性、安定メモリ使用、マイクロ秒レベル計測
```

### 2. パフォーマンスプロファイル
```bash
claude --print "test/debug_test.phpのパフォーマンスをプロファイルして"
# AIが自動的に./bin/xdebug-profileを使用：
# ✅ プロファイル完了: /tmp/cachegrind.out.1755719364
# 📊 サイズ: 1.9K、関数: 29、呼び出し: 28、ボトルネック特定
```

### 3. コードカバレッジ分析
```bash
claude --print "test/debug_test.phpのコードカバレッジを分析して"
# AIが自動的に./bin/xdebug-coverageを使用：
# ✅ カバレッジ完了: HTMLレポート生成
# 📊 カバレッジ: 85.2% 行、92.1% 関数、未テストコードパス特定
```

### 4. ステップデバッグ
```bash
claude --print "test/debug_test.phpをデバッグして、15行目でbreakして変数値を表示して"
# AIがブレークポイントを設定してデバッグセッションを提供：
# ✅ ブレークポイント設定: test/debug_test.php:15
# 📊 ブレークポイントでの変数値:
# | 変数     | 型     | 値                       |
# |----------|--------|--------------------------|
# | $n       | int    | 6                        |
# | $result  | int    | 8                        |
# | $user    | array  | ['name'=>'John','age'=>30] |
```

### 5. PHPUnitテスト
```bash
# PHPUnitテストのデバッグ（phpunit.xmlにTraceExtension追加後）
./bin/xdebug-phpunit tests/Unit/McpServerTest.php::testConnect
```

## 利用できる42ツール

### Debug (11)
- **xdebug_connect**: Xdebugセッションに接続
- **xdebug_disconnect**: Xdebugセッションから切断
- **xdebug_set_breakpoint**: 指定したファイルと行にブレークポイントを設定
- **xdebug_remove_breakpoint**: IDでブレークポイントを削除
- **xdebug_step_into**: 次の関数呼び出しにステップイン
- **xdebug_step_over**: 現在行をステップオーバー
- **xdebug_step_out**: 現在関数からステップアウト
- **xdebug_continue**: 次のブレークポイントまで実行継続
- **xdebug_get_stack**: 現在のスタックトレースを取得
- **xdebug_get_variables**: 現在コンテキストの変数を取得
- **xdebug_eval**: 現在コンテキストでPHP式を評価

### Profile (4)
- **xdebug_start_profiling**: プロファイリング実行開始
- **xdebug_stop_profiling**: プロファイリング停止と結果返却
- **xdebug_get_profile_info**: 現在のプロファイリング情報を取得
- **xdebug_analyze_profile**: ファイルからプロファイリングデータを分析

### Coverage (6)
- **xdebug_start_coverage**: コードカバレッジ追跡開始
- **xdebug_stop_coverage**: コードカバレッジ追跡停止
- **xdebug_get_coverage**: コードカバレッジデータを取得
- **xdebug_analyze_coverage**: カバレッジデータを分析しレポート生成
- **xdebug_coverage_summary**: カバレッジ概要統計を取得

### Extended (21)
- **xdebug_get_memory_usage**: 現在のメモリ使用量情報を取得
- **xdebug_get_peak_memory_usage**: ピークメモリ使用量情報を取得
- **xdebug_get_stack_depth**: 現在のスタック深度レベルを取得
- **xdebug_get_time_index**: スクリプト開始からの時間インデックスを取得
- **xdebug_info**: 詳細なXdebug設定と診断情報を取得
- **xdebug_start_error_collection**: PHPエラー、通知、警告の収集開始
- **xdebug_stop_error_collection**: エラー収集停止と収集データ返却
- **xdebug_get_collected_errors**: 現在収集されているエラーメッセージを取得
- **xdebug_start_trace**: 関数呼び出しトレース開始
- **xdebug_stop_trace**: 関数呼び出しトレース停止とトレースデータ返却
- **xdebug_get_tracefile_name**: 現在のトレースファイル名を取得
- **xdebug_start_function_monitor**: 特定関数の監視開始
- **xdebug_stop_function_monitor**: 関数監視停止と監視呼び出し返却
- **xdebug_list_breakpoints**: アクティブなブレークポイント一覧
- **xdebug_set_exception_breakpoint**: 例外にブレークポイント設定
- **xdebug_set_watch_breakpoint**: ウォッチ/条件付きブレークポイント設定
- **xdebug_get_function_stack**: 引数と変数を含む詳細関数スタック取得
- **xdebug_print_function_stack**: フォーマットされた関数スタックトレース印刷
- **xdebug_call_info**: 呼び出しコンテキストの情報取得
- **xdebug_get_features**: 利用可能なXdebug機能とその値を全取得
- **xdebug_set_feature**: 特定のXdebug機能値を設定
- **xdebug_get_feature**: 特定のXdebug機能値を取得

## 使用例

Claudeに自然言語で依頼:

**デバッグ:**
- 「PHPスクリプトのデバッグを開始してください」
- 「user.phpの25行目にブレークポイントを設定して」
- 「現在のスタックトレースを表示して」
- 「$user_data変数の値は何ですか？」
- 「次の行にステップオーバーして」

**パフォーマンス分析:**  
- 「このPHPスクリプトのパフォーマンスをプロファイルして」
- 「メモリ使用量の統計を表示して」
- 「最も時間がかかっている関数はどれ？」

**コードカバレッジ:**
- 「コードのどの部分がテストでカバーされていない？」
- 「src/User.phpのカバーされていない行を表示して」
- 「テストが必要な箇所を見るためのHTMLカバレッジレポートを生成して」
- 「全体のテストカバレッジ率は？」

## トラブルシューティング

- `claude mcp list` で確認
- Xdebugインストール確認: `php -m | grep xdebug`
- ポート競合: xdebug-mcpは9004、IDEは9003を使用

## リンク

- [Xdebug ドキュメント](https://xdebug.org/docs/)
- [MCP仕様](https://modelcontextprotocol.io/)
- [Claude Desktop MCP](https://docs.anthropic.com/claude/docs/mcp)
