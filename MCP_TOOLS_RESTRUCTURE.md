# MCP Tools Restructure Plan

## 🎯 **設計方針**

**明確な役割分担:**
- **MCP (AI用)**: 実行後の分析専門
- **Direct Tools (人間用)**: リアルタイムインタラクティブデバッグ

## 🗑️ **削除対象: Interactive Debugging Tools**

以下のMCPツールは削除（人間は直接ツールを使用）:

### Connection Management
- `xdebug_connect`
- `xdebug_disconnect`

### Step Debugging
- `xdebug_step_into`
- `xdebug_step_over` 
- `xdebug_step_out`
- `xdebug_continue`

### Breakpoint Management
- `xdebug_set_breakpoint`
- `xdebug_remove_breakpoint`
- `xdebug_list_breakpoints`
- `xdebug_set_exception_breakpoint`
- `xdebug_set_watch_breakpoint`

### Runtime Inspection
- `xdebug_get_stack`
- `xdebug_get_variables`
- `xdebug_eval`
- `xdebug_get_function_stack`
- `xdebug_print_function_stack`

### Session Management
- `xdebug_list_sessions`
- `xdebug_start_http_debug_session`

## ✅ **保持対象: Analysis Tools (AI向け)**

### Trace Analysis
- `xdebug_start_trace`
- `xdebug_stop_trace`
- `xdebug_analyze_trace`

### Performance Profiling  
- `xdebug_start_profiling`
- `xdebug_stop_profiling`
- `xdebug_get_profile_info`
- `xdebug_analyze_profile`

### Code Coverage
- `xdebug_start_coverage`
- `xdebug_stop_coverage`
- `xdebug_get_coverage`
- `xdebug_analyze_coverage`
- `xdebug_coverage_summary`

### System Information
- `xdebug_info`
- `xdebug_get_memory_usage`
- `xdebug_get_peak_memory_usage`
- `xdebug_get_time_index`
- `xdebug_get_features`
- `xdebug_set_feature`
- `xdebug_get_feature`

### Error & Function Monitoring
- `xdebug_start_error_collection`
- `xdebug_stop_error_collection`
- `xdebug_get_collected_errors`
- `xdebug_start_function_monitor`
- `xdebug_stop_function_monitor`

### Stack Information (Non-interactive)
- `xdebug_get_stack_depth`
- `xdebug_call_info`

## 🛠️ **人間用ツール (そのまま保持)**

以下の直接ツールは変更なし:

```bash
./bin/xdebug-debug script.php    # インタラクティブデバッグ
./bin/xdebug-trace script.php    # トレース実行
./bin/xdebug-profile script.php  # パフォーマンス分析
./bin/xdebug-coverage script.php # コードカバレッジ
```

## 📊 **変更前後の比較**

| カテゴリ | 変更前 | 変更後 | 削減数 |
|----------|--------|--------|--------|
| 全MCPツール | ~43個 | 28個 | 15個削除 |
| Interactive | 15個 | 0個 | 15個削除 |
| Analysis | 28個 | 28個 | 変更なし |

## 🚀 **期待される効果**

1. **明確な責任分離**: AI = 分析, Human = インタラクション
2. **複雑性削減**: MCPツールが35%削減 (43→28個)
3. **保守性向上**: インタラクティブ機能はツール側で管理
4. **信頼性向上**: 確実に動作する機能のみMCPで提供

## ⚡ **実装手順**

1. ✅ `src/McpServer.php`から削除対象ツールを除去
2. ✅ 対応するメソッドを削除  
3. ✅ テスト実行で動作確認
4. ✅ ドキュメント更新

---

## ✅ **実装完了**

**削除されたインタラクティブツール (15個):**
- xdebug_connect / xdebug_disconnect
- xdebug_step_into / xdebug_step_over / xdebug_step_out / xdebug_continue
- xdebug_set_breakpoint / xdebug_remove_breakpoint / xdebug_list_breakpoints
- xdebug_set_exception_breakpoint / xdebug_set_watch_breakpoint
- xdebug_get_stack / xdebug_get_variables / xdebug_eval
- xdebug_list_sessions / xdebug_start_http_debug_session

**保持された分析ツール (28個):**
- プロファイリング: xdebug_start_profiling, xdebug_analyze_profile など
- コードカバレッジ: xdebug_start_coverage, xdebug_analyze_coverage など  
- トレース: xdebug_start_trace, xdebug_stop_trace など
- システム情報: xdebug_info, xdebug_get_memory_usage など
- エラー収集: xdebug_start_error_collection など
- 関数監視: xdebug_start_function_monitor など

**テスト結果:** 
- MCPサーバー正常起動 ✅
- 分析ツール動作確認 ✅  
- 削除ツールが適切にエラー ✅
- ツール数: 43個 → 28個 (35%削減) ✅