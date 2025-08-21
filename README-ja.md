# PHP Xdebug MCP サーバー

> AIが私たちのようにXdebugを使ってPHPデバッグ

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

## インストール

```bash
# 開発依存としてインストール
composer require --dev koriym/xdebug-mcp:1.x-dev
```

## セットアップ

### MCP設定

```bash
# Claude Desktop
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# 確認
claude mcp list
```

### Xdebug設定（推奨）

**php.ini: 最適なパフォーマンスのためXdebugをコメントアウト**
```ini
# 推奨: php.ini でパフォーマンス向上のためコメントアウト
;zend_extension=xdebug
# その他のXdebug設定はvendor/bin/xdebug-*コマンドで自動処理されます
```

**なぜ推奨されるか:**
- Xdebugを常時有効にするとPHP実行速度が50-80%低下します
- vendor/bin/xdebug-*コマンドは必要時のみXdebugを読み込みます
- 本番環境では決してXdebugを永続的に有効にすべきではありません

### AI設定（推奨）

**AIに推測ではなく実行時解析を使わせる:**

```bash
# プロジェクト固有: デバッグ原則をプロジェクトにコピー
cp vendor/koriym/xdebug-mcp/templates/CLAUDE_DEBUG_PRINCIPLES.md ./
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ./CLAUDE.md
```

**システム全体（オプション）:**
```bash
# 全PHPプロジェクトに適用
cp vendor/koriym/xdebug-mcp/templates/CLAUDE_DEBUG_PRINCIPLES.md ~/.claude/CLAUDE.md
```

**これによりできること:**
- AIが`var_dump()`や`echo`をデバッグに使わなくなる
- AIが代わりに`./vendor/bin/xdebug-trace`を使うようになる
- 実際の実行トレースからデータドリブン分析が可能

## クイックスタート

**1. MCPサーバー起動:**
```bash
./vendor/bin/xdebug-server
# ✅ サーバーがポート9004で起動し、AIコマンドを受付可能
```

**2. AIにランタイムデータ分析を依頼:**
```bash
# 別ターミナルで - AIに推測ではなく実際の実行データを分析させる
claude --print "test/debug_test.phpをトレースしてパフォーマンスボトルネックを特定して"
# ✅ AIが自動的にxdebug-traceを実行してデータドリブン分析を提供
```

**3. ゼロ設定PHPUnitデバッグ（ゲームチェンジャー）:**
```bash
# AI支援テストデバッグ - phpunit.xml変更不要！
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin
# ✅ TraceExtension自動注入、特定テストメソッドをトレース、全PHPUnitプロジェクトで動作
```

## 検証

**AI統合をテスト:**
```bash
# パフォーマンスプロファイリング
claude --print "test/debug_test.phpをプロファイルして最も遅い関数を表示して"
# ✅ AIがxdebug-profileを実行してcachegrind出力を分析

# カバレッジ分析  
claude --print "test/debug_test.phpのコードカバレッジを分析して"
# ✅ AIがxdebug-coverageを実行してテストされていないコードパスを報告
```

**手動検証（オプション）:**
```bash
# 必要に応じて直接ツールを実行することも可能
./vendor/bin/xdebug-trace test/debug_test.php
./vendor/bin/xdebug-profile test/debug_test.php  
./vendor/bin/xdebug-coverage test/debug_test.php
```

**確認すべき内容:**
- Trace files showing exact function call sequences and variable values
- Performance data revealing O(2^n) fibonacci inefficiency 
- Coverage reports highlighting untested code paths
- AI providing data-driven analysis instead of static code guessing

## 使用方法

### コマンドラインツール

- `xdebug-server` - MCPサーバー起動（ポート9004）
- `xdebug-mcp` - コアMCPサーバー 
- `xdebug-trace` - 実行トレース生成
- `xdebug-profile` - パフォーマンスプロファイル  
- `xdebug-coverage` - コードカバレッジ分析
- `xdebug-phpunit` - PHPUnit選択的Xdebug分析

### 基本コマンド

```bash
# 推奨: vendor/bin/xdebug-* コマンドを使用
./vendor/bin/xdebug-trace script.php
./vendor/bin/xdebug-profile script.php
./vendor/bin/xdebug-coverage script.php
```

**手動アプローチ（上記と同等）:**
```bash
# 同じ処理だが手動
php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_port=9004 script.php
```

### AI活用例

### 1. 実行トレース
```bash
claude --print "test/debug_test.phpを実行して実行パターンを分析して"
# AIが自動的に./vendor/bin/xdebug-traceを選択して分析を提供：
# ✅ Trace complete: /tmp/trace_20250821_044930.xt (64 lines)
# 📊 Analysis: O(2^n) Fibonacci inefficiency, stable memory usage, microsecond-level metrics
```

### 2. パフォーマンスプロファイル
```bash
claude --print "test/debug_test.phpのパフォーマンスをプロファイルして"
# AIが自動的に./vendor/bin/xdebug-profileを使用：
# ✅ Profile complete: /tmp/cachegrind.out.1755719364
# 📊 Size: 1.9K, Functions: 29, Calls: 28, identifies bottlenecks
```

### 3. コードカバレッジ分析
```bash
claude --print "test/debug_test.phpのコードカバレッジを分析して"
# AIが自動的に./vendor/bin/xdebug-coverageを使用：
# ✅ Coverage complete: HTML report generated
# 📊 Coverage: 85.2% lines, 92.1% functions, identifies untested code paths
```

### 4. ステップデバッグ
```bash
claude --print "test/debug_test.phpをデバッグして、15行目でbreakして変数値を表示して"
# AIがブレークポイントを設定してデバッグセッションを提供：
# ✅ Breakpoint set at test/debug_test.php:15
# 📊 Variables at breakpoint:
# | Variable | Type   | Value                    |
# |----------|--------|--------------------------|
# | $n       | int    | 6                        |
# | $result  | int    | 8                        |
# | $user    | array  | ['name'=>'John','age'=>30] |
```

### 5. PHPUnitテスト
```bash
# PHPUnitテストのデバッグ（ゼロ設定で即座に実行）
./vendor/bin/xdebug-phpunit tests/Unit/McpServerTest.php::testConnect
```

### xdebug-phpunit 使用方法 - 究極のPHPUnitデバッグソリューション

**ゼロ設定** でPHPUnitの自動Xdebugトレース・プロファイルを実行 - **phpunit.xmlを変更せずに既存のPHPUnitプロジェクトで動作**：

```bash
# 特定テストメソッドのトレース（デフォルトモード）
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin

# テストファイル全体のプロファイル
./vendor/bin/xdebug-phpunit --profile tests/UserTest.php

# フィルター条件でのトレース
./vendor/bin/xdebug-phpunit --filter=testUserAuth

# 有効設定の表示（透明性確保）
./vendor/bin/xdebug-phpunit --dry-run tests/UserTest.php

# 詳細ログ表示（デバッグ用）
./vendor/bin/xdebug-phpunit --verbose tests/UserTest.php
```

**自動注入:** TraceExtensionが一時phpunit.xmlに自動注入されます（手動設定不要）

**出力:**
- トレースモード: `/tmp/trace_*.xt` (実行トレース)
- プロファイルモード: `/tmp/cachegrind.out.*` (パフォーマンスデータ)

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


## トラブルシューティング

- `claude mcp list` で確認
- Xdebugインストール確認: `php -m | grep xdebug`
- ポート競合: xdebug-mcpは9004、IDEは9003を使用

## リンク

- [Xdebug ドキュメント](https://xdebug.org/docs/)
- [MCP仕様](https://modelcontextprotocol.io/)
- [Claude Desktop MCP](https://docs.anthropic.com/claude/docs/mcp)
