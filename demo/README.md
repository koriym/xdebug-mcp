# Demo - Quick Start Guide / クイックスタートガイド

Try the Xdebug MCP tools with these sample scripts.

このサンプルスクリプトで Xdebug MCP ツールを試してみましょう。

## Prerequisites / 前提条件

```bash
# Install dependencies / 依存関係のインストール
composer install

# Verify Xdebug is installed / Xdebugがインストールされているか確認
./bin/check-env
```

---

## 1. Debugging with xstep / xstep でデバッグ

Debug buggy code with interactive step debugging.

バグのあるコードをインタラクティブにステップデバッグします。

```bash
# Run debugging session / デバッグセッションを実行
./bin/xstep demo/sample_buggy.php

# With breakpoint at specific line / 特定の行にブレークポイントを設定
./bin/xstep --break=demo/sample_buggy.php:20 demo/sample_buggy.php

# Auto-exit mode (for AI analysis) / 自動終了モード（AI分析用）
./bin/xstep --exit-on-break --context="Debug sum calculation bug" demo/sample_buggy.php
```

**What to look for / 確認ポイント:**
- Line 20: `$a - $b` should be `$a + $b`
- Line 28: Loop misses last element

---

## 2. Execution Tracing with xtrace / xtrace で実行トレース

Trace the execution flow without stopping.

実行フローを停止せずにトレースします。

```bash
# Basic trace / 基本的なトレース
./bin/xtrace demo/sample_buggy.php

# With context for AI / AI用のコンテキスト付き
./bin/xtrace --context="Trace buggy calculation flow" demo/sample_buggy.php
```

**Output / 出力:**
- Function call hierarchy
- Parameter values at each call
- 関数呼び出しの階層と各呼び出し時のパラメータ値

---

## 3. Performance Profiling with xprofile / xprofile でパフォーマンス分析

Find performance bottlenecks in slow code.

遅いコードのパフォーマンスボトルネックを見つけます。

```bash
# Profile the slow script / 遅いスクリプトをプロファイル
./bin/xprofile demo/sample_slow.php
```

**What to look for / 確認ポイント:**
- `inefficientSort()`: O(n³) complexity with unnecessary inner loop
- `slowStringProcess()`: Redundant string operations
- `fibonacci()`: Exponential recursive calls

---

## 4. Code Coverage with xcoverage / xcoverage でコードカバレッジ

Analyze which lines of code are executed.

どの行が実行されたかを分析します。

```bash
# Collect coverage data / カバレッジデータを収集
./bin/xcoverage -- php demo/sample_coverage.php
```

**What to look for / 確認ポイント:**
- Lines with `1`: Executed (green)
- Lines with `-1`: Not executed (red)
- 実行された行（緑）と実行されなかった行（赤）

**Uncovered branches in this demo / このデモで未実行のブランチ:**
- Age < 0 (invalid)
- Age >= 65 (senior)
- Empty email
- Email without @
- Short password, no uppercase, no number

---

## 5. Using MCP Server / MCP サーバーの使用

For integration with AI assistants (Claude, etc.):

AI アシスタント（Claude 等）との統合用：

```bash
# Start MCP server / MCP サーバーを起動
./bin/xdebug-mcp

# Test with JSON-RPC / JSON-RPC でテスト
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | ./bin/xdebug-mcp
```

---

## Sample Files / サンプルファイル

| File | Purpose | 目的 |
|------|---------|------|
| `sample_buggy.php` | Debug demo with intentional bugs | バグを含むデバッグデモ |
| `sample_slow.php` | Performance profiling demo | パフォーマンスプロファイリングデモ |
| `sample_coverage.php` | Code coverage demo | コードカバレッジデモ |

---

## Quick Command Reference / コマンド早見表

| Tool | Use Case | 用途 |
|------|----------|------|
| `xstep` | Interactive debugging, breakpoints | インタラクティブデバッグ |
| `xtrace` | Execution flow analysis | 実行フロー分析 |
| `xprofile` | Performance bottleneck detection | パフォーマンス分析 |
| `xcoverage` | Test coverage analysis | テストカバレッジ分析 |

---

## Next Steps / 次のステップ

1. Try these demos on your own PHP files
2. Read the main [README.md](../README.md) for full documentation
3. Configure Claude Desktop with the MCP server

1. 自分の PHP ファイルでこれらのデモを試す
2. メインの [README.md](../README.md) で詳細なドキュメントを読む
3. MCP サーバーで Claude Desktop を設定する
