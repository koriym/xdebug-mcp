# Interactive Session Control Example

## セッション状態保持によるインタラクティブ制御

### 基本パターン: ブレークポイント間の変化追跡

```bash
# セッション開始
./bin/xdebug-debug --break="User.php:25,Order.php:150" -- php checkout_process.php

# AIによる指示例:
# "User.phpのブレークポイントで停止中。次のOrder.phpまで実行して、$cartと$userの変化をまとめて"
```

**MCPコマンドシーケンス:**
```json
{
  "step": 1,
  "action": "continue",
  "instruction": "次のブレークポイントまで実行"
}

{
  "step": 2,
  "action": "capture_diff",
  "variables": ["$cart", "$user", "$total"],
  "instruction": "変数変化を差分記録"
}
```

### 高度なパターン: 条件付きステップ実行

```bash
# 条件付きブレークポイントでセッション開始
./bin/xdebug-debug --break="Payment.php:42:\$amount>1000" -- php payment.php

# AIによる指示:
# "$amount>1000のケースで停止。$user->balance変更まで1ステップずつ実行して変化点を特定"
```

**Step-by-step制御:**
```json
{
  "mode": "step_recording",
  "max_steps": 50,
  "watch_variables": ["$user->balance", "$payment_status"],
  "stop_condition": "$user->balance != previous_value"
}
```

### 実用例: デバッグセッション

```bash
# 複雑なワークフロー解析
./bin/xdebug-debug \
  --break="AuthService.php:15,UserController.php:85,Database.php:120" \
  --steps=200 \
  -- php login_flow.php
```

**AIの代理指示例:**
1. **"AuthService.phpで停止中。認証ロジックをstep実行して$tokenの生成過程を追跡"**
2. **"UserController.phpまで飛んで、ユーザーデータ取得の結果を確認"** 
3. **"Database.phpのクエリ実行前後で$queryと$resultを比較"**
4. **"全体の実行フローと変数変化をJSON形式でまとめて出力"**

### MCPツール活用パターン

```bash
# MCP経由での精密制御
{
  "tool": "xdebug_step_into",
  "context": "PaymentGateway::processTransaction内部の詳細解析"
}

{
  "tool": "xdebug_get_variables", 
  "filter": ["payment_*", "user_*"],
  "context": "決済関連変数の状態確認"
}

{
  "tool": "xdebug_eval",
  "expression": "json_encode([$payment, $user, $transaction])",
  "context": "現在の決済状況をJSON出力"
}
```

## セッション状態保持の利点

### 1. **コンテキスト保持**
- 変数履歴の継続追跡
- コールスタック状態の維持
- ブレークポイント設定の永続化

### 2. **効率的なデバッグ**
- 問題箇所までcontinue→詳細解析のピンポイント調査
- 重要な変化点での自動停止
- 段階的な原因分析

### 3. **人間らしい制御**
- "ここまで実行して結果確認"
- "この条件になったら詳しく調べて"
- "変化があった部分だけ詳細表示"

### 4. **AIとの協調**
- セッション状態をAIが理解
- 人間の意図を反映した制御
- 効率的な情報収集と分析

## 従来の制限からの脱却

**Before (セッションレス):**
```bash
# 毎回最初から実行、状態リセット
./bin/xdebug-trace script.php  # 全データ取得、解析困難
```

**After (セッション保持):**
```bash
# インタラクティブ制御、効率的解析
./bin/xdebug-debug --break="key_points" -- php script.php
# → 重要箇所で停止
# → 必要な部分のみ詳細解析
# → 人間の判断でフロー制御
```

これにより、AIが人間の代理として**効率的で実用的なデバッグセッション**を実現できます。