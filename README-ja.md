# PHP Xdebug MCP Server

PHPのXdebugデバッガーをAIが操作できるMCP (Model Context Protocol) サーバーです。

## 🚀 概要

このMCPサーバーは、Xdebugの **全42機能** を包括的にカバーし、AIがPHPアプリケーションのデバッグ、プロファイリング、コードカバレッジ分析を完全に自動化できます。

**対応仕様:**
- 🔄 **MCP 2025-03-26** (最新仕様)
- 🐛 **Xdebug 3.x** 完全対応
- 🎯 **42のMCPツール** 搭載

## ✨ 主要機能

### 🐛 デバッグ機能 (11ツール)
- **セッション管理**: Xdebugセッションへの接続/切断
- **ブレークポイント制御**: 行・例外・ウォッチブレークポイント
- **ステップ実行**: step into, step over, step out
- **実行制御**: 実行継続、一時停止
- **コード調査**: スタックトレース、変数検査、PHP式評価
- **高度な機能**: 条件付きブレークポイント、例外キャッチ

### ⚡ プロファイリング機能 (4ツール)
- **パフォーマンス計測**: 実行時間・メモリ使用量の詳細分析
- **関数レベル解析**: 関数別実行時間・呼び出し回数
- **Cachegrind対応**: KCacheGrind/QCacheGrindとの連携
- **スタンドアロン動作**: Xdebugセッションなしでも利用可能

### 📊 コードカバレッジ機能 (6ツール)
- **包括的追跡**: 実行行・未実行行の完全な記録
- **多形式レポート**: HTML/XML/JSON/テキスト形式
- **PHPUnit連携**: CI/CDパイプラインでの自動化対応
- **詳細統計**: ファイル別・行別カバレッジ率

### 🔧 拡張機能 (21ツール)
#### 📈 **統計・診断情報**
- メモリ使用量監視（現在値・ピーク値）
- スタック深度追跡
- 実行時間計測
- Xdebug設定情報取得

#### 🚨 **エラー管理**
- PHP エラー・警告・通知の自動収集
- エラー履歴の管理と分析
- 本番環境での問題追跡

#### 🔍 **トレーシング**
- 関数呼び出しの完全なトレース
- 特定関数のモニタリング
- 実行フローの可視化

#### 🎯 **高度なブレークポイント**
- 例外型別ブレークポイント
- 変数ウォッチポイント
- ブレークポイント一覧管理

#### 🗂️ **スタック情報拡張**
- 引数付き詳細スタック表示
- フォーマット済みスタックトレース
- 呼び出し元情報の詳細取得

#### ⚙️ **動的設定管理**
- Xdebugフィーチャーの動的変更
- デバッグセッション中の設定調整
- 最適化パラメータの調整

## セットアップ

### 1. 依存関係のインストール
```bash
composer install
```

### 2. Xdebugの設定
php.iniに以下を追加：
```ini
zend_extension=xdebug
xdebug.mode=debug,profile,coverage  ; デバッグ、プロファイル、カバレッジ機能を有効化
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
; プロファイル設定（オプション）
xdebug.output_dir=/tmp
xdebug.profiler_output_name=cachegrind.out.%p
```

### 3. MCPクライアントでの設定

#### Claude Desktop
Claude Desktopの設定ファイルに以下を追加：

**macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
**Windows**: `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "xdebug": {
      "command": "php",
      "args": ["/path/to/your/xdebug-mcp/bin/xdebug-mcp"],
      "env": {
        "PATH": "/usr/local/bin:/usr/bin:/bin"
      }
    }
  }
}
```

#### その他のMCPクライアント
`mcp.json`ファイルを参考にしてください：
```json
{
  "mcpServers": {
    "xdebug": {
      "command": "php",
      "args": ["/path/to/your/xdebug-mcp/bin/xdebug-mcp"],
      "env": {
        "PHP_PATH": "/usr/bin/php"
      }
    }
  }
}
```

## 使用方法

### AIクライアント（Claude Desktop等）での使用

MCPクライアント設定後、AIに以下のように依頼できます：

```
PHPデバッグを開始して、/path/to/script.phpの10行目にブレークポイントを設定してください
```

```
現在のスタックトレースと変数の値を確認してください
```

```
ステップオーバーで次の行に進んで、$user_data変数の中身を確認してください
```

### 直接JSON-RPCでの使用

1. MCPサーバーを起動
```bash
./bin/xdebug-mcp
```

2. Xdebugセッションに接続
```json
{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": {"name": "xdebug_connect", "arguments": {"host": "127.0.0.1", "port": 9003}}}
```

3. ブレークポイント設定
```json
{"jsonrpc": "2.0", "id": 2, "method": "tools/call", "params": {"name": "xdebug_set_breakpoint", "arguments": {"filename": "/path/to/file.php", "line": 10}}}
```

4. ステップ実行
```json
{"jsonrpc": "2.0", "id": 3, "method": "tools/call", "params": {"name": "xdebug_step_over", "arguments": {}}}
```

## テスト・デモ

### Fakeモードでのデモ
実際のXdebugなしでデモを試すことができます：
```bash
php tests/fake/demo.php
```

### 実際のXdebugテスト
テスト用PHPスクリプトを実行してデバッグをテスト：
```bash
php -dxdebug.mode=debug test/debug_test.php
```

## Claude Desktopでの使用例

MCPサーバーが正しく設定されていれば、Claude Desktopで以下のような自然言語でのやり取りが可能です：

### デバッグセッション開始
```
ユーザー: PHPのデバッグを開始したいです。127.0.0.1:9003で待機してください

Claude: Xdebugセッションに接続します。
[xdebug_connect tool実行]
✅ Xdebugセッションに接続しました。session情報: {...}
```

### ブレークポイント設定
```
ユーザー: /var/www/html/index.phpの25行目にブレークポイントを設定してください

Claude: 指定の場所にブレークポイントを設定します。
[xdebug_set_breakpoint tool実行]
✅ ブレークポイントを設定しました（ID: 1）
```

### ステップ実行とデバッグ
```
ユーザー: 現在の変数を確認して、次の行にステップオーバーしてください

Claude: まず現在のローカル変数を確認します。
[xdebug_get_variables tool実行]
現在の変数:
- $user_id: 123
- $data: ["name" => "John", "age" => 30]

次の行にステップオーバーします。
[xdebug_step_over tool実行]
✅ ステップオーバー完了
```

### 式の評価
```
ユーザー: $data['name']の値を確認してください

Claude: 指定の式を評価します。
[xdebug_eval tool実行]
$data['name']の値: "John"
```

### プロファイリング機能の使用
```
ユーザー: PHPスクリプトのプロファイリングを開始してください

Claude: プロファイリングを開始します。
[xdebug_start_profiling tool実行]
✅ プロファイリングが開始されました

[実行後]
ユーザー: プロファイリングを停止して、結果を解析してください

Claude: プロファイリングを停止し、結果を解析します。
[xdebug_stop_profiling tool実行]
[xdebug_analyze_profile tool実行]
📊 プロファイル解析結果:
- 総実行時間: 0.25秒
- 最も重い関数: calculateTax() (0.15秒, 60%)
- 呼び出し回数が多い関数: formatDate() (1,250回)
```

### コードカバレッジ機能の使用
```
ユーザー: テストのコードカバレッジを測定してください

Claude: コードカバレッジの追跡を開始します。
[xdebug_start_coverage tool実行]
✅ コードカバレッジ追跡が開始されました

[テスト実行後]
ユーザー: カバレッジレポートを生成してください

Claude: カバレッジデータを取得し、HTMLレポートを生成します。
[xdebug_get_coverage tool実行]
[xdebug_analyze_coverage tool実行]
📈 カバレッジサマリー:
- 総カバレッジ: 85.2%
- テスト済み行数: 1,240 / 1,456行
- 未カバレッジファイル: src/Utils.php (45%), src/Cache.php (62%)
HTMLレポートを coverage.html に保存しました
```

## 📋 利用可能なツール（全42機能）

### 🐛 デバッグツール (11)
| ツール名 | 機能 |
|---------|------|
| `xdebug_connect` | Xdebugセッションに接続 |
| `xdebug_disconnect` | Xdebugセッションから切断 |
| `xdebug_set_breakpoint` | 行ブレークポイント設定 |
| `xdebug_remove_breakpoint` | ブレークポイント削除 |
| `xdebug_step_into` | ステップイン実行 |
| `xdebug_step_over` | ステップオーバー実行 |
| `xdebug_step_out` | ステップアウト実行 |
| `xdebug_continue` | 実行継続 |
| `xdebug_get_stack` | スタックトレース取得 |
| `xdebug_get_variables` | 変数一覧取得 |
| `xdebug_eval` | PHP式評価 |

### ⚡ プロファイリングツール (4)
| ツール名 | 機能 |
|---------|------|
| `xdebug_start_profiling` | プロファイリング開始 |
| `xdebug_stop_profiling` | プロファイリング停止 |
| `xdebug_get_profile_info` | プロファイル設定情報取得 |
| `xdebug_analyze_profile` | Cachegrindファイル解析 |

### 📊 コードカバレッジツール (6)
| ツール名 | 機能 |
|---------|------|
| `xdebug_start_coverage` | コードカバレッジ追跡開始 |
| `xdebug_stop_coverage` | コードカバレッジ追跡停止 |
| `xdebug_get_coverage` | カバレッジデータ取得 |
| `xdebug_analyze_coverage` | カバレッジレポート生成（HTML/XML/JSON/text） |
| `xdebug_coverage_summary` | カバレッジ統計サマリー |

### 📈 統計・診断ツール (5)
| ツール名 | 機能 |
|---------|------|
| `xdebug_get_memory_usage` | 現在のメモリ使用量取得 |
| `xdebug_get_peak_memory_usage` | ピークメモリ使用量取得 |
| `xdebug_get_stack_depth` | 現在のスタック深度取得 |
| `xdebug_get_time_index` | スクリプト開始からの経過時間 |
| `xdebug_info` | Xdebug設定と診断情報取得 |

### 🚨 エラー管理ツール (3)
| ツール名 | 機能 |
|---------|------|
| `xdebug_start_error_collection` | PHP エラー収集開始 |
| `xdebug_stop_error_collection` | エラー収集停止 |
| `xdebug_get_collected_errors` | 収集されたエラー一覧取得 |

### 🔍 トレーシングツール (5)
| ツール名 | 機能 |
|---------|------|
| `xdebug_start_trace` | 関数呼び出しトレース開始 |
| `xdebug_stop_trace` | トレース停止 |
| `xdebug_get_tracefile_name` | トレースファイル名取得 |
| `xdebug_start_function_monitor` | 特定関数のモニタリング開始 |
| `xdebug_stop_function_monitor` | 関数モニタリング停止 |

### 🎯 高度なブレークポイントツール (3)
| ツール名 | 機能 |
|---------|------|
| `xdebug_list_breakpoints` | アクティブなブレークポイント一覧 |
| `xdebug_set_exception_breakpoint` | 例外ブレークポイント設定 |
| `xdebug_set_watch_breakpoint` | 変数ウォッチブレークポイント設定 |

### 🗂️ スタック情報拡張ツール (3)
| ツール名 | 機能 |
|---------|------|
| `xdebug_get_function_stack` | 詳細な関数スタック情報取得 |
| `xdebug_print_function_stack` | フォーマット済みスタック表示 |
| `xdebug_call_info` | 呼び出し元詳細情報取得 |

### ⚙️ フィーチャー設定ツール (3)
| ツール名 | 機能 |
|---------|------|
| `xdebug_get_features` | 利用可能なXdebugフィーチャー一覧 |
| `xdebug_set_feature` | Xdebugフィーチャー値設定 |
| `xdebug_get_feature` | 特定フィーチャー値取得 |

## トラブルシューティング

### 接続できない場合
1. Xdebugが正しくインストールされているか確認
2. php.iniの設定を確認
3. ポート9003が使用可能か確認

### MCPサーバーが起動しない場合
1. PHPのパスが正しく設定されているか確認
2. composer installが実行されているか確認
3. ファイルの実行権限を確認