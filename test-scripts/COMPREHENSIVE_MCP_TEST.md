# 🤖 AI自律テスト：全47機能の包括的検証

## タスク
XdebugMCPサーバーの全機能（42 MCP tools + 5 CLI tools = 47機能）を自律的にテストし、各機能の動作状況を報告してください。

## 重要な指示
- **自分で全ての機能をテスト実行**してください
- **実際の動作結果に基づいて判定**してください  
- **推測や仮定での判定は禁止**です
- 各機能について「✅成功」「❌失敗」「⚠️部分成功」「🚫未実装」で報告してください

## テスト対象機能リスト

### 📊 Profiling & Performance (4 tools)
1. **xdebug_start_profiling** - プロファイリング開始
2. **xdebug_stop_profiling** - プロファイリング停止  
3. **xdebug_get_profile_info** - プロファイル情報取得
4. **xdebug_analyze_profile** - Cachegrindファイル解析

### 📈 Code Coverage (6 tools)  
5. **xdebug_start_coverage** - カバレッジ計測開始
6. **xdebug_stop_coverage** - カバレッジ計測停止
7. **xdebug_get_coverage** - カバレッジデータ取得
8. **xdebug_analyze_coverage** - カバレッジレポート生成
9. **xdebug_coverage_summary** - カバレッジサマリー
10. **xdebug_get_coverage_info** - カバレッジ設定情報

### 🐛 Interactive Debugging (11 tools)
11. **xdebug_connect** - デバッグセッション接続
12. **xdebug_disconnect** - デバッグセッション切断
13. **xdebug_set_breakpoint** - ブレークポイント設置
14. **xdebug_remove_breakpoint** - ブレークポイント削除
15. **xdebug_step_into** - ステップイン実行
16. **xdebug_step_over** - ステップオーバー実行  
17. **xdebug_step_out** - ステップアウト実行
18. **xdebug_continue** - 継続実行
19. **xdebug_get_stack** - スタックトレース取得
20. **xdebug_get_variables** - 変数値取得
21. **xdebug_eval** - 式評価

### 🔍 Trace Analysis (4 tools)
22. **xdebug_start_trace** - トレース開始
23. **xdebug_stop_trace** - トレース停止
24. **xdebug_get_trace_info** - トレース設定情報
25. **xdebug_analyze_trace** - トレースファイル解析

### ⚙️ Configuration & Diagnostics (17 tools)
26. **xdebug_get_status** - サーバー状態取得
27. **xdebug_get_error_info** - エラー情報取得
28. **xdebug_get_memory_usage** - メモリ使用量取得
29. **xdebug_get_execution_time** - 実行時間取得
30. **xdebug_get_stack_depth** - スタック深度取得
31. **xdebug_list_breakpoints** - ブレークポイント一覧
32. **xdebug_set_exception_breakpoint** - 例外ブレークポイント
33. **xdebug_set_watch_breakpoint** - ウォッチブレークポイント
34. **xdebug_get_features** - 機能一覧取得
35. **xdebug_set_feature** - 機能設定
36. **xdebug_get_feature** - 機能設定取得
37. **xdebug_collect_errors** - エラー収集
38. **xdebug_get_collected_errors** - 収集エラー取得
39. **xdebug_clear_collected_errors** - エラーリストクリア
40. **xdebug_get_function_trace** - 関数トレース取得
41. **xdebug_call_info** - 呼び出し情報取得
42. **xdebug_print_function_stack** - 関数スタック出力

### 🛠️ CLI Tools (5 tools)
43. **./bin/xdebug-debug** - インタラクティブデバッグ
44. **./bin/xdebug-profile** - プロファイリング実行
45. **./bin/xdebug-coverage** - カバレッジ解析
46. **./bin/xdebug-trace** - トレース実行
47. **./bin/xdebug-phpunit** - PHPUnit統合

## テスト手順

### Phase 1: 基本機能テスト

**⚠️ 重要な制限事項**
- **JSON-RPCコマンド直接実行は禁止**です（例: `echo '{"jsonrpc":...}' | php bin/xdebug-mcp`）
- これは「コマンド送信成功」であって「機能動作成功」ではありません
- **MCPツールが利用可能な場合のみ**、MCPツールを使用してください
- MCPツールが利用できない場合は、代替方法（CLIツール）でテストしてください

**正しいテスト方法:**
```bash
# 1. MCPツールが利用可能かチェック
# もしMCPツールが使える環境なら：
mcp__xdebug__xdebug_start_profiling

# 2. MCPが使えない場合はCLIツールでテスト
./bin/xdebug-profile test/debug_test.php

# 3. 結果の実際の確認（重要！）
ls -la /tmp/*profile*  # プロファイルファイルが生成されたか確認
```

### Phase 2: CLIツールテスト  
```bash
# 43-47: CLI toolsを直接実行
./bin/xdebug-profile test/debug_test.php
./bin/xdebug-coverage test/debug_test.php  
./bin/xdebug-trace test/debug_test.php
# etc...
```

### Phase 3: インタラクティブデバッグテスト

**🚨 重要: Interactive Debugging テストの正しい手順**

Interactive Debugging (11 tools) は特別な接続シーケンスが必要です：

```bash
# ✅ 正しい手順（必須！）
# Step 1: XdebugClient を先に起動（MUST BE FIRST!）
php test/debug_session_test.php &

# Step 2: ポートが待機中であることを確認
lsof -i :9004  # LISTEN状態を確認

# Step 3: ./bin/xdebug-debug を使用してスクリプト実行
./bin/xdebug-debug test-scripts/buggy_calculation_code.php
```

**期待される成功サイン:**
- `✅ Connected to debugging session!`
- `Breakpoint ID: XXXXXXX` (ブレークポイント設定成功)
- `🔧 Variables at breakpoint:` (変数inspect成功)
- `▶️ Continuing to breakpoint...` (continue実行成功)
- `👣 Step over the condition...` (step実行成功)

## 報告フォーマット

各機能について以下の形式で報告：

```
## テスト結果レポート

### 📊 Profiling & Performance (4/4)
✅ xdebug_start_profiling - 成功: プロファイルファイル /tmp/cachegrind.out.12345 生成確認
❌ xdebug_stop_profiling - 失敗: connection timeout error, プロファイルファイル未生成
⚠️ xdebug_get_profile_info - 部分成功: 設定データ取得できるが実際のプロファイル実行は失敗
🚫 xdebug_analyze_profile - 未実装: MCPツール自体が存在しない

**検証方法:** 各機能について実際のファイル生成、データ取得、動作確認を実施

### 📈 Code Coverage (X/6)
...

### 🐛 Interactive Debugging (X/11)  
...

### 🔍 Trace Analysis (X/4)
...

### ⚙️ Configuration & Diagnostics (X/17)
...

### 🛠️ CLI Tools (X/5)
...

## 総合結果
- ✅ 成功: XX/47 (XX%)
- ❌ 失敗: XX/47 (XX%) 
- ⚠️ 部分成功: XX/47 (XX%)
- 🚫 未実装: XX/47 (XX%)

## 重要な発見
- [実際に発見した問題点]
- [推奨される改善点]
- [動作確認された主要機能]
```

## 成功基準
- **完全テスト**: 47機能すべてを実際に実行
- **正確な判定**: 推測でなく実際の結果に基づく評価
- **詳細レポート**: 各機能の具体的な動作結果
- **改善提案**: 発見された問題の解決案

## 🚨 Interactive Debugging テストが失敗した時の対処法

もしInteractive Debugging (11 tools) テストで「部分成功」や「失敗」になった場合、以下を確認してください：

### 問題診断チェックリスト

**❌ よくある間違い:**
```bash
# ❌ 間違い1: MCPツールを直接テスト
mcp__xdebug__xdebug_connect  # これだけでは不十分

# ❌ 間違い2: スクリプトを直接実行
XDEBUG_TRIGGER=1 php -dxdebug.mode=debug test.php  # 接続先がない

# ❌ 間違い3: 順序が逆
./bin/xdebug-debug test.php  # XdebugClient が起動していない
php test_new_xdebug_debug.php &
```

**✅ 正しい診断方法:**
```bash
# 1. 接続確認: XdebugClient が実際に起動しているか？
php test/debug_session_test.php &
sleep 2
lsof -i :9004  # <- 必須: LISTEN が表示されるか？

# 2. 実際のデバッグセッション実行
./bin/xdebug-debug test-scripts/buggy_calculation_code.php

# 3. 成功サインを確認
# - "✅ Connected to debugging session!" が表示されるか？
# - "Breakpoint ID:" が表示されるか？
# - 変数値が表示されるか？
```

### 成功基準の再確認

**Interactive Debugging (11 tools) が ✅ 成功 になる条件:**
1. **xdebug_connect**: "Xdebug connected! Connection established successfully" 表示
2. **xdebug_set_breakpoint**: "Breakpoint ID: XXXXXXX" でブレークポイント設定成功  
3. **xdebug_get_variables**: "$変数名 (型): 値" で変数値表示成功
4. **xdebug_continue**: "Continuing to breakpoint..." で継続実行成功
5. **xdebug_step_over**: "Step over the condition..." でステップ実行成功
6. その他6つの機能も同様に実際の動作確認

**⚠️ 重要**: 
- MCPツール名の存在確認だけでは「部分成功」です
- 実際のデバッグセッション動作確認で「完全成功」になります
- 推測や仮定での判定は禁止です

**重要**: このテストにより、XdebugMCPサーバーの**真の実装完成度**が明らかになります。AIが実際に47の機能を使いこなせるかの最終検証です。

頑張ってください！🚀