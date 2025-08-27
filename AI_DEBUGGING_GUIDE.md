# AI Debugging Guide for xdebug-mcp

## 重要：AIによるインタラクティブデバッグの正しいワークフロー

### 基本理解
- `xdebug-debug`はデバッグセッションを開始し、**既にXdebugに接続済み**
- MCPツールは**既存のセッションに対してコマンドを送信**
- `xdebug_connect`は不要（既に接続済みのため）

### 正しい手順

#### 1. デバッグセッション開始（バックグラウンド）
```bash
./bin/xdebug-debug target_script.php &
```
**結果**: 
- Xdebugセッション確立済み
- 9004ポートでMCPコマンド待機中
- シェルでインタラクティブコマンド待機中

#### 2. AIがMCPツール経由でステップ実行制御
```bash
# AIが直接実行可能
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_step_into","arguments":{}}}' | ./bin/xdebug-mcp

echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_get_variables","arguments":{}}}' | ./bin/xdebug-mcp

echo '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"xdebug_step_over","arguments":{}}}' | ./bin/xdebug-mcp
```

#### 3. セッション終了
```bash
echo '{"jsonrpc":"2.0","id":99,"method":"tools/call","params":{"name":"xdebug_disconnect","arguments":{}}}' | ./bin/xdebug-mcp
```
**結果**: バックグラウンドプロセス（&）も自動終了

### AIが使用できるステップデバッグコマンド

| MCPツール | 機能 |
|-----------|------|
| `xdebug_step_into` | 関数内部にステップイン |
| `xdebug_step_over` | 現在行をステップオーバー |
| `xdebug_step_out` | 現在関数からステップアウト |
| `xdebug_continue` | 次のブレークポイントまで継続 |
| `xdebug_get_variables` | 現在コンテキストの変数取得 |
| `xdebug_get_stack` | スタックトレース取得 |
| `xdebug_eval` | 式を評価 |
| `xdebug_set_breakpoint` | ブレークポイント設定 |

### 典型的なAIデバッグセッション例

```bash
# 1. セッション開始
./bin/xdebug-debug complex_algorithm.php &

# 2. AIが段階的にデバッグ
# 最初の行で変数確認
xdebug_get_variables

# 関数内部に進む
xdebug_step_into

# 変数の状態を確認
xdebug_get_variables

# 条件分岐をステップオーバー
xdebug_step_over

# 特定の変数の値を評価
xdebug_eval("$result")

# セッション終了
xdebug_disconnect
```

### 重要な注意点
1. **xdebug_connect不要** - セッションは既に確立済み
2. **バックグラウンド実行必須** - `&`でバックグラウンド化
3. **MCPツールは既存セッションを制御** - 新しい接続ではない
4. **disconnect で完全終了** - バックグラウンドプロセスも終了

### トラブルシューティング
- セッションが応答しない → プロセス確認 `ps aux | grep xdebug`
- ポート競合 → `lsof -i :9004` でポート使用状況確認
- 強制終了が必要な場合 → `pkill -f xdebug-debug`

この理解が、AIによる真のインタラクティブデバッグ制御の基盤です。