# おすすめの使い方 🎯

## 1. 間欠的なバグを確実に捕捉

「たまに起きる」バグを逃さない：

```bash
# 特定の条件でのみ発生するバグを捕捉
./vendor/bin/xdebug-debug --break='api.php:123:$response==null' --exit-on-break -- php test.php

# 複数条件での監視
./vendor/bin/xdebug-debug --break='Auth.php:42:$token==null,User.php:85:$id==0' -- php app.php
```

**効果：** 再現困難なバグも確実に捕捉し、発生時の完全なコンテキストを取得

## 2. パフォーマンス分析をAIに任せる

プロファイリング → AI分析 → 最適化提案：

```bash
# マイクロ秒精度のプロファイリング
./vendor/bin/xdebug-profile --claude slow_endpoint.php

# AI分析例：
# "fetchUser()が847回呼ばれています (実行時間の72%) - キャッシュを追加してください"
```

**効果：** 人間では見落としがちなパフォーマンスボトルネックを AI が瞬時に特定

## 3. テスト失敗の原因を一発で特定

テスト実行 → トレース → 原因分析：

```bash
# 特定のテストメソッドをトレース
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin

# AI に原因分析を依頼
claude --continue "テストが失敗した原因を分析してください"
```

**効果：** テスト失敗時の推測作業を排除し、実行フローベースで原因を特定

## 日常的なワークフロー 🔄

### 🌅 朝の習慣

```bash
# 夜間に発生したエラーを分析
./vendor/bin/xdebug-trace --claude -- php problematic_cron.php
claude --continue "昨夜のcronジョブのエラーを調査"
```

### 🐛 バグ報告が来たとき

```bash
# 1. 条件を再現
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' --json --exit-on-break -- php reproduce_bug.php

# 2. AI に原因分析を依頼  
claude --continue "なぜ合計がマイナスになるのか原因を特定"

# 3. 修正後の動作確認
./vendor/bin/xdebug-trace -- php fixed_version.php
claude --continue "修正が正しく動作しているか確認"
```

### 🚀 リリース前チェック

```bash
# カバレッジ確認
./vendor/bin/xdebug-coverage tests/

# AI に追加テストを提案させる
claude --continue "カバレッジレポートを分析し、追加すべきテストを提案"
```

## 高度な使用パターン 🚀

### レースコンディションの発見

```bash
# タイミング依存のバグを捕捉
./vendor/bin/xdebug-debug --break='session.php:45:$timestamp<time()' -- php concurrent_app.php
```

### メモリリークの特定

```bash
# メモリ使用量の監視
./vendor/bin/xdebug-trace -- php memory_intensive.php
claude --continue "メモリ使用量の推移を分析し、リークしている箇所を特定"
```

### API レスポンス時間の最適化

```bash
# API エンドポイントのプロファイリング
./vendor/bin/xdebug-profile api/users/list.php

# AI による最適化提案
claude --continue "APIのレスポンス時間を改善する具体的な方法を提案"
```

## チーム開発での活用 👥

### コードレビューでの活用

```bash
# プルリクエストの影響を分析
./vendor/bin/xdebug-trace -- php pr_branch_test.php
./vendor/bin/xdebug-trace -- php main_branch_test.php

claude --continue "2つのトレースファイルを比較し、パフォーマンスへの影響を分析"
```

### 新人研修での活用

```bash
# コードの動作を視覚化
./vendor/bin/xdebug-trace -- php beginner_code.php
claude --continue "このコードがどのような順序で実行されるか、初心者にも分かりやすく説明"
```

## トラブルシューティング Tips 💡

### 1. トレースファイルが大きすぎる場合

```bash
# 特定の関数のみをトレース（条件付きブレークポイント使用）
./vendor/bin/xdebug-debug --break='YourClass.php:*:method_exists($this,"problematicMethod")' -- php app.php
```

### 2. 本番環境での使用

```bash
# JSON出力で自動化
./vendor/bin/xdebug-debug --break='error.php:*:$error_level=="CRITICAL"' --json --exit-on-break -- php production.php
```

### 3. CI/CD パイプラインでの使用

```bash
# テストカバレッジの自動チェック
./vendor/bin/xdebug-coverage tests/ > coverage_report.html
# カバレッジが閾値を下回った場合にビルド失敗
```

## まとめ

**従来のデバッグ手法からの脱却：**
- ❌ `var_dump()` + 推測 → ✅ トレース + AI分析
- ❌ 手動ステップ実行 → ✅ 条件付きブレークポイント
- ❌ 主観的なパフォーマンス判断 → ✅ データ駆動の最適化

**結果：**
- デバッグ時間の大幅短縮
- より正確な問題特定
- チーム全体のスキルレベル向上
- プロダクションでのトラブル対応力強化

毎日の開発作業で AI と協力し、より効率的で品質の高いソフトウェア開発を実現しましょう。