# AIネイティブな開発とデバッグ

<img src="https://koriym.github.io/xdebug-mcp/logo.jpeg" alt="Xdebug MCP" style="width: 200px; border-radius: 20px;">

## PHP Xdebug MCP Server

Enable AI to use Xdebug for PHP debugging like we do

---

## 🤔 まさか今でも...

--

### var_dumpデバッグしてませんか？

--

### AIが... <!-- .element: class="highlight" -->

---

## 😱 AIの実際の行動パターン

--

### Step 1: 勝手にvar_dumpを追加

```php
function register($user_id) {
    var_dump($user_id); // ← AIが勝手に追加
    if ($user_id === 0) {
        // 問題発見！
    }
}
```

--

### Step 2: 分析後に削除

```php
function register($user_id) {
    // var_dump削除済み
    if ($user_id === 0) {
        // 修正済み
    }
}
```

--

### これで満足してませんか？🤔 <!-- .element: class="highlight" -->

---

## 💡 「侵襲的」とは？

--

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
<div style="background: rgba(255,107,107,0.2); padding: 1.5rem; border-radius: 10px;">

#### 😱 侵襲的
コードを変更して調べる
```php
var_dump($user); // ← 追加
```

</div>
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### ✨ 非侵襲的
コード変更なしで調べる
```bash
./bin/xdebug-trace app.php
```

</div>
</div>

--

### なるほど〜 <!-- .element: class="highlight" -->

---

## 😭 現実はもっと深刻でした

--

### AIはソースコードと実行結果だけでデバッグしてるんです！ <!-- .element: class="highlight" -->

--

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
<div style="background: rgba(255,107,107,0.2); padding: 1.5rem; border-radius: 10px;">

#### AIが見ているもの
- 📄 ソースコード（静的）
- ❌ エラーメッセージ
- 🤔 推測と憶測

</div>
<div style="background: rgba(255,107,107,0.2); padding: 1.5rem; border-radius: 10px;">

#### AIが見えないもの
- 🚫 実際の実行フロー
- 🚫 リアルタイム変数値
- 🚫 パフォーマンス実測

</div>
</div>

--

### これで2024年のAI開発と言えるでしょうか？ <!-- .element: class="warning" -->

---

## 😔 つまり...

--

<div style="background: rgba(255,107,107,0.2); padding: 1.5rem; border-radius: 10px; margin: 0 auto; max-width: 600px;">

### AIの典型的な思考プロセス

「エラーメッセージを読んで...」

「ソースコードを見て...」

「多分この変数が原因でしょう」

「var_dump()を追加してみてください」

</div>

--

### 推測に推測を重ねているだけ <!-- .element: class="highlight" -->

---

## 🚀 AIネイティブなデバッグ

--

### 実行時データでAIが真実を知る <!-- .element: class="success" -->

--

var_dump追加 → **実行追跡** <!-- .element: class="highlight" -->

推測デバッグ → **データ分析** <!-- .element: class="highlight" -->

コード汚染 → **非侵襲観察** <!-- .element: class="highlight" -->

--

### 2024年にふさわしいAI開発へ <!-- .element: class="highlight" -->

---

## ✨ 新しいアプローチ

--

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### 実行時データ分析
- 実際の実行フローを追跡
- 真の変数値を取得
- パフォーマンス実測

</div>
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### 非侵襲デバッグ
- ソースコード変更なし
- プロダクション安全
- 完全自動化

</div>
</div>

---

## 🔧 47の完全動作ツール

--

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
<div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 10px;">

#### 📊 Profile (4 tools)
パフォーマンス分析

</div>
<div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 10px;">

#### 🎯 Debug (11 tools)
ステップ実行・変数検査

</div>
<div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 10px;">

#### 📈 Trace (4 tools)
実行フロー追跡

</div>
<div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 10px;">

#### 🔍 Coverage (6 tools)
コードカバレッジ分析

</div>
</div>

---

## 🎮 Interactive Step Debugging

--

```bash
# $idが0のときの登録処理を調べる
./bin/xdebug-debug --break=User.php:85:$id==0 -- php register.php
```

--

```
🎮 Starting interactive debugging session
Available commands: s(tep), c(ontinue), p <var>, claude, q(uit)
(Xdebug) claude
🤖 Analyzing execution trace...
📊 Root Cause: $id initialized as empty string, cast to 0
```

---

## ⚡ Performance Profiling

--

```bash
# パフォーマンス分析
./bin/xdebug-profile slow_function.php
```

--

```
✅ Profile complete: /tmp/cachegrind.out.1755719364
📊 Analysis Results:
  - fibonacci(): 1,247μs (34% of total)
  - 46 recursive calls detected
  - 🤖 AI Suggestion: Implement memoization
```

---

## 📈 Execution Tracing

--

```bash
# 実行フロー追跡
./bin/xdebug-trace complex_workflow.php
```

--

```
✅ Trace complete: /tmp/trace-workflow-20250825.xt
📊 Execution Analysis:
  Level | Function    | Time    | Memory  | Parameters
  0     | {main}      | 0.001ms | 384KB   |
  1     | validate()  | 0.002ms | 385KB   | $user=null
  🤖 AI Found: Null user at validation step
```

---

## 🔍 Code Coverage

--

```bash
# カバレッジ分析
./bin/xdebug-coverage test/UserTest.php
```

--

```
✅ Coverage complete: HTML report generated
📊 Coverage Results:
  - Lines: 85.2% (23/27 covered)
  - Functions: 92.1% (47/51 covered)
  - 🤖 AI Identified: Untested error handling paths
```

---

## 🛠️ Command Line Tools

--

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
<div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 10px;">

#### Standalone Commands
```bash
xdebug-debug
xdebug-profile  
xdebug-trace
xdebug-coverage
xdebug-phpunit
```

</div>
<div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 10px;">

#### Auto-Configuration
- Zero setup required
- Conflict-free ports
- Xdebug auto-loading

</div>
</div>

---

## 🔌 MCP Integration

--

```bash
# Claude Desktop setup
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"
```

--

```
✅ MCP Server: 47 tools available
🤖 AI can now:
  - Start debugging sessions
  - Analyze performance data
  - Generate coverage reports
  - Execute conditional debugging
```

---

## 🐛 Use Case: Bug Investigation

--

#### Traditional Way:
```php
// 推測ベース
var_dump($user_id);  // どんな値？
echo "Debug point 1";
if ($user_id == 0) {
    die("Found the problem!");  // 侵襲的
}
```

--

#### AI + Xdebug Way:
```bash
# ユーザID=0での登録エラーを調べる - ソースコード変更なし
./bin/xdebug-debug --break=register.php:45:$user_id==0 -- php app.php
# 🤖 AI が実行データから根本原因を特定
```

---

## 🚀 Use Case: Performance Optimization

--

#### Traditional Way:
```php
// 推測ベース
$start = microtime(true);
slowFunction();
$end = microtime(true);
echo "Time: " . ($end - $start);  // 手動計測
```

--

#### AI + Xdebug Way:
```bash
# 完全自動計測
./bin/xdebug-profile app.php
# 🤖 AI が全関数の実行時間とボトルネックを特定
```

---

## 🧪 Use Case: Test Coverage Analysis

--

#### Traditional Way:
「このコードはテストされているはず...」
手動でテスト範囲を推測

--

#### AI + Xdebug Way:
```bash
# 完全可視化
./bin/xdebug-coverage tests/UserTest.php
# 🤖 AI が未テストコードパスを正確に特定
```

---

## 🎯 Conditional Debugging Demo

--

```bash
# 認証失敗とID=0問題を同時に調べる
./bin/xdebug-debug --break=Auth.php:42:$token==null,User.php:85:$id==0
```

--

```
(Xdebug) claude analyze authentication failure
🤖 Root Cause Analysis
- Call chain: login() → validateToken() → $token=null
- Token expires at validate.php:23
- Missing renewal logic in Auth.php:18
Suggested Fix: Add token refresh before validation
```

---

## 🤖 Real-time AI Analysis

--

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### AI が実行時に知ること
- 実際の変数値
- 真の実行パス
- 正確なパフォーマンス
- メモリ使用状況

</div>
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### 提供される洞察
- 根本原因特定
- 具体的修正案
- 最適化提案
- テスト改善点

</div>
</div>

---

## 📦 Installation

--

```bash
# Composer installation
composer require --dev koriym/xdebug-mcp:1.x-dev

# Claude Desktop setup  
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# Ready to go!
./bin/xdebug-profile your-script.php
```

--

### Zero configuration. Works immediately. <!-- .element: class="success" -->

---

## 🌟 AIネイティブ開発の実現

--

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### From var_dump汚染
コードに傷をつける

</div>
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### To 実行データ分析
コードを守って観察

</div>
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### From 推測ベース
「多分こうでしょう」

</div>
<div style="background: rgba(81,207,102,0.2); padding: 1.5rem; border-radius: 10px;">

#### To 実測ベース
「実際にこうでした」

</div>
</div>

---

## 🚀 AIネイティブ開発を始めよう！

--

### AI + Xdebug = AIネイティブ開発 <!-- .element: class="success" -->

--

GitHub: **koriym/xdebug-mcp**
47 working tools, 100% tested

--

### 推測デバッグから実測デバッグへ <!-- .element: class="highlight" -->
**Stop guessing. Start knowing.** <!-- .element: class="success" -->

---

# ありがとうございました！

<img src="https://koriym.github.io/xdebug-mcp/logo.jpeg" alt="Xdebug MCP" style="width: 200px; border-radius: 20px;">

**PHP Xdebug MCP Server**

Enable AI to debug like we do

--

🤖 Questions? Let Claude analyze your debugging workflow!