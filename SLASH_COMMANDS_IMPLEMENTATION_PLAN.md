# MCP Slash Commands Implementation Plan

## Overview / 概要

Implement AI-friendly slash commands for xdebug-mcp to provide better Claude Code integration and user experience.

MCPスラッシュコマンドを実装してClaude Code統合とユーザー体験を向上させる。

## Proposed Slash Commands / 提案するスラッシュコマンド

### Core Commands / コアコマンド
- `/x-debug <script>` - Interactive step debugging / インタラクティブデバッグ
- `/x-trace <script>` - Execution tracing and analysis / 実行トレースと分析  
- `/x-profile <script>` - Performance profiling / パフォーマンス分析
- `/x-coverage <script>` - Code coverage analysis / コードカバレッジ分析
- `/x-phpunit <tests>` - PHPUnit test execution / PHPUnitテスト実行

### Key Features / 主要機能

#### 1. AI-First Design / AI優先設計
- **Default behavior**: AI analysis enabled by default / デフォルトでAI分析有効
- **Automatic JSON**: Always return structured data / 常に構造化データを返す  
- **No manual flags**: `--json --exit-on-break` automatically applied / 手動フラグ不要

#### 2. Context Memory / コンテキスト記憶
```bash
/x-debug script.php --break=script.php:10 --steps=5
# Settings are remembered / 設定を記憶

/x-debug last
# Repeat with same settings / 同じ設定で再実行

/x-debug last --steps=10  
# Modify specific parameters / 特定パラメータのみ変更
```

#### 3. JSON Export Option / JSONエクスポートオプション
```bash
/x-trace script.php           # AI analysis in Claude Code / Claude Code内でAI分析
/x-trace script.php --json    # JSON output for external tools / 外部ツール用JSON出力
```

#### 4. Argument Handling / 引数処理
```bash
/x-debug --break=file.php:10 -- php script.php arg1 arg2
# Maintains -- separation for script arguments / スクリプト引数の--分離を維持
```

## Benefits / メリット

### 1. Enhanced UX / UX向上
- **Tab completion**: `/x[TAB]` shows all xdebug commands / タブ補完でxdebugコマンド一覧
- **Consistent interface**: All tools use same pattern / 全ツールが同じパターン
- **AI integration**: Seamless Claude Code experience / シームレスなClaude Code体験

### 2. Simplified Usage / 使用法の簡素化
- **No manual flags**: `--json --exit-on-break` automatic / 手動フラグ不要
- **Smart defaults**: AI analysis enabled by default / AI分析がデフォルト
- **Context awareness**: Remember previous settings / 前回設定を記憶

### 3. Better AI Workflow / より良いAIワークフロー
```bash
# Traditional workflow / 従来のワークフロー
./bin/xdebug-trace --json -- php script.php
# → Find trace file in /tmp
# → Read and analyze manually

# New MCP workflow / 新しいMCPワークフロー  
/x-trace script.php
# → Immediate AI analysis and insights
# → Structured data ready for follow-up questions
```

## Implementation Plan / 実装計画

### Phase 1: Core Infrastructure / フェーズ1: コアインフラ
- [ ] Add slash command handler to McpServer.php
- [ ] Implement context persistence for `last` functionality
- [ ] Design command parsing and validation

### Phase 2: Command Implementation / フェーズ2: コマンド実装
- [ ] `/x-debug` - Wrap bin/xdebug-debug with AI enhancements
- [ ] `/x-trace` - Wrap bin/xdebug-trace with immediate analysis  
- [ ] `/x-profile` - Wrap bin/xdebug-profile with insights
- [ ] `/x-coverage` - Wrap bin/xdebug-coverage with summary

### Phase 3: Advanced Features / フェーズ3: 高度な機能
- [ ] Command history: `/x-debug history`
- [ ] Numbered recall: `/x-debug #2`
- [ ] Smart suggestions based on context

## Technical Considerations / 技術的検討事項

### 1. Context Storage / コンテキストストレージ
- Store last command settings in memory or temp file
- メモリまたは一時ファイルに前回コマンド設定を保存

### 2. Error Handling / エラー処理  
- Graceful fallback when `last` has no previous context
- `last`に前回コンテキストがない場合の適切なフォールバック

### 3. Security / セキュリティ
- Validate all command parameters before execution
- 実行前の全コマンドパラメータ検証

## Expected Impact / 期待される効果

### Developer Experience / 開発者体験
- **Faster debugging**: Less typing, more analysis / より高速なデバッグ
- **Better insights**: AI analysis by default / デフォルトでAI分析
- **Consistent workflow**: Same commands across all tools / 全ツールで一貫したワークフロー

### AI Integration / AI統合
- **Seamless Claude Code usage**: Native slash command support / ネイティブスラッシュコマンドサポート
- **Better conversation flow**: Results ready for follow-up questions / フォローアップ質問に対応した結果
- **External tool compatibility**: JSON export for other AIs / 他のAI向けJSONエクスポート

## Next Steps / 次のステップ

1. Create GitHub issue with this plan / この計画でGitHubイシューを作成
2. Implement basic slash command infrastructure / 基本スラッシュコマンドインフラを実装
3. Start with `/x-trace` as proof of concept / 概念実証として`/x-trace`から開始
4. Gather feedback and iterate / フィードバックを収集し反復改善

---

This enhancement will significantly improve the AI-driven debugging experience and make xdebug-mcp more intuitive for Claude Code users.

この拡張により、AI駆動のデバッグ体験が大幅に向上し、Claude Codeユーザーにとってxdebug-mcpがより直感的になります。