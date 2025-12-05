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

Debug buggy code with step debugging. **Returns JSON for AI analysis.**

バグのあるコードをステップデバッグします。**AI分析用にJSON形式で出力されます。**

**Ask AI / AIに依頼:**
> "demo/sample_buggy.php をステップ実行してバグを見つけて"
> "Debug demo/sample_buggy.php and find the bug"

**CLI:**
```bash
# JSON output mode (recommended for AI) / JSON出力モード（AI向け推奨）
./bin/xstep --exit-on-break -- php demo/sample_buggy.php

# With breakpoint and context / ブレークポイントとコンテキスト付き
./bin/xstep --exit-on-break --break=demo/sample_buggy.php:20 \
  --context="Debug sum calculation bug" -- php demo/sample_buggy.php

# Interactive mode (for manual debugging) / インタラクティブモード（手動デバッグ用）
./bin/xstep -- php demo/sample_buggy.php
```

**Output Format / 出力形式:**
- `--exit-on-break`: JSON output with execution trace and variable states
- Without option: Interactive terminal session
- `--exit-on-break`: 実行トレースと変数状態を含むJSON出力
- オプションなし: インタラクティブなターミナルセッション

**What to look for / 確認ポイント:**
- Line 20: `$a - $b` should be `$a + $b`
- Line 28: Loop misses last element

---

## 2. Execution Tracing with xtrace / xtrace で実行トレース

Trace the execution flow without stopping.

実行フローを停止せずにトレースします。

**Ask AI / AIに依頼:**
> "demo/sample_buggy.php の実行フローをトレースして"
> "Trace execution flow of demo/sample_buggy.php"

**CLI:**
```bash
# Basic trace / 基本的なトレース
./bin/xtrace -- php demo/sample_buggy.php

# With context for AI / AI用のコンテキスト付き
./bin/xtrace --context="Trace buggy calculation flow" -- php demo/sample_buggy.php
```

**Output / 出力:**
- Function call hierarchy
- Parameter values at each call
- 関数呼び出しの階層と各呼び出し時のパラメータ値

---

## 3. Performance Profiling with xprofile / xprofile でパフォーマンス分析

Find performance bottlenecks in slow code.

遅いコードのパフォーマンスボトルネックを見つけます。

**Ask AI / AIに依頼:**
> "demo/sample_slow.php のパフォーマンスを分析して"
> "Profile demo/sample_slow.php and find bottlenecks"

**CLI:**
```bash
# Profile the slow script / 遅いスクリプトをプロファイル
./bin/xprofile -- php demo/sample_slow.php
```

**What to look for / 確認ポイント:**
- `inefficientSort()`: O(n³) complexity with unnecessary inner loop
- `slowStringProcess()`: Redundant string operations
- `fibonacci()`: Exponential recursive calls

---

## 4. Code Coverage with xcoverage / xcoverage でコードカバレッジ

Analyze which lines of code are executed.

どの行が実行されたかを分析します。

**Ask AI / AIに依頼:**
> "demo/sample_coverage.php のカバレッジを分析して"
> "Analyze code coverage of demo/sample_coverage.php"

**CLI:**
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

| Tool | Output | Use Case | 用途 |
|------|--------|----------|------|
| `xstep --exit-on-break` | JSON | Step debugging for AI | AI用ステップデバッグ |
| `xstep` | Interactive | Manual step debugging | 手動ステップデバッグ |
| `xtrace` | JSON | Execution flow analysis | 実行フロー分析 |
| `xprofile` | JSON | Performance analysis | パフォーマンス分析 |
| `xcoverage` | JSON | Code coverage analysis | カバレッジ分析 |

---

## Next Steps / 次のステップ

1. Try these demos on your own PHP files
2. Read the main [README.md](../README.md) for full documentation
3. Configure Claude Desktop with the MCP server

1. 自分の PHP ファイルでこれらのデモを試す
2. メインの [README.md](../README.md) で詳細なドキュメントを読む
3. MCP サーバーで Claude Desktop を設定する
