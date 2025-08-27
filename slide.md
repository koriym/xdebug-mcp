# 🍽️ Feeding the Beast

## Why AI Needs Runtime Data

---

## The Moment of Clarity 💡

I watched Claude Code debug PHP:

```php
var_dump($variable);  // AI adds this
// Run tests, analyze output
var_dump($other);     // AI adds another
// Remove first, add third...
var_dump($result);    // Repeat...
```

> "This sophisticated AI was debugging like it was 1995."

---

## The Real Problem 🎯

### It wasn't the AI's fault.

We were asking a detective to solve a crime using only **photographs** of the crime scene.

We give AI:
- Static code structure
- Function signatures  
- Comments and documentation

But we **never** show AI what actually happens when code runs.

---

## 🍽️ Feed the Beast

> "AI is always hungry for data, and we've been giving it scraps."

What AI really needs:
- The exact sequence of function calls
- Variable states at each step
- Memory snapshots and performance metrics
- Complete runtime behavior, not guesswork

---

## The Transformation 🚀

| Before (Static Analysis) | After (Runtime Intelligence) |
|---------------------------|-------------------------------|
| `var_dump()` | `xdebug_start_trace()` |
| AI examines code structure | AI analyzes execution traces |
| → Makes educated guesses | → Identifies exact failure point |
| → Suggests print debugging | → Provides precise fix |

---

## See the Magic ✨

### Old Way (2 hours of guessing)
```php
var_dump($cart);     // What's in cart?
var_dump($discount); // Check discount  
var_dump($total);    // Why is this negative?!
die("HERE");         // Getting desperate...
// 2 hours later: still guessing...
```

### New Way (30 seconds of evidence)
```bash
# Conditional breakpoint: Stop when problem occurs
./vendor/bin/xdebug-debug --break=checkout.php:89:$total<0 --exit-on-break

# AI analyzes and reports:
"Found it: $50 discount applied to $30 cart = -$20 total
 Fix: Add $this->recalculateDiscount() after removeItem()"
```

---

## Try It Right Now 🏃‍♂️

```bash
# 1. Install
composer require --dev koriym/xdebug-mcp

# 2. Create a simple test
echo '<?php
$result = "success";
if (rand(0,1)) { $result = null; }
echo $result;
?>' > test.php

# 3. Let AI analyze execution
./vendor/bin/xdebug-trace --claude -- php test.php
claude --continue "Find why \$result becomes null"

# 4. Enable full AI integration  
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"
```

---

## AI Superhuman Capabilities 🦾

| Challenge | Human with IDE | AI with Runtime Data |
|-----------|----------------|---------------------|
| **Intermittent bugs** | Set breakpoint, hope to catch it | Set conditional breakpoint, capture every occurrence |
| **Complex flows** | Step through manually, miss details | Process entire execution trace, identify all patterns |
| **Race conditions** | Nearly impossible to catch | Set time-based conditionals, capture exact timing |

---

## おすすめの使い方 🎯

### 1. 間欠的なバグを確実に捕捉
```bash
# 「たまに起きる」バグを逃さない
./vendor/bin/xdebug-debug --break='api.php:123:$response==null' \
  --exit-on-break -- php test.php
```

### 2. パフォーマンス分析をAIに任せる
```bash
# プロファイリング → AI分析 → 最適化提案
./vendor/bin/xdebug-profile --claude slow_endpoint.php
# "fetchUser()が847回呼ばれています (実行時間の72%) - キャッシュを追加してください"
```

### 3. テスト失敗の原因を一発で特定
```bash
# テスト実行 → トレース → 原因分析
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin
claude --continue "テストが失敗した原因を分析してください"
```

---

## 日常的なワークフロー 🔄

### 🌅 朝の習慣
```bash
# 夜間に発生したエラーを分析
./vendor/bin/xdebug-trace --claude -- php problematic_cron.php
claude --continue "昨夜のcronジョブのエラーを調査"
```

### 🐛 バグ報告が来たとき
```bash
# 条件を再現 → トレース → 修正
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' \
  --json --exit-on-break -- php reproduce_bug.php
claude --continue "なぜ合計がマイナスになるのか原因を特定"
```

### 🚀 リリース前チェック
```bash
# カバレッジ確認 → 未テスト箇所の特定
./vendor/bin/xdebug-coverage tests/
claude --continue "カバレッジレポートを分析し、追加すべきテストを提案"
```

---

## The 43 Tools Arsenal 🛠️

- **Execution Tracing (4):** Complete function flow with all data
- **Conditional Debugging (11):** Intelligent breakpoints with full traces
- **Performance Profiling (4):** Microsecond-precision timing analysis
- **Code Coverage (6):** Exact test coverage mapping
- **Diagnostics (22):** Memory, errors, stack, configuration

**Combined: AI with superhuman debugging capabilities**

---

## Beyond Debugging 🌟

This isn't just about fixing bugs faster.

**It's about transforming human-AI collaboration.**

- While humans get overwhelmed by thousands of function calls...
- AI instantly identifies patterns in massive trace files
- We're giving AI the rich, structured data it excels at

---

## The Vision 🔮

### A world where:
- Debugging becomes systematic and data-driven
- Print statements feel as archaic as punch cards
- Every developer has professional debugging workflows
- AI assistants routinely use execution traces

> "Every time AI uses xdebug_start_trace() instead of var_dump(), we're elevating the entire profession."

---

## The Inflection Point ⚡

We can continue treating AI as a sophisticated search engine...

**Or we can build true partnership through radical transparency.**

Not just sharing our code, but our *entire development context*.

**This is proof that when we stop asking AI to guess and start giving it real data, we unlock capabilities we never imagined.**

---

## デバッグのコツ：条件付きトレースの威力 🎯

### シナリオ：「ユーザーIDが0のときだけエラーになる」

**従来の方法（つらい）:**
```php
// コードを汚染する必要がある
if ($userId === 0) {
    var_dump($userData, $permissions, $sessionData);
    error_log("Debug: User ID 0 detected");
    // 本番環境で消し忘れリスク...
}
```

**xdebug-mcp の条件付きトレース（スマート）:**
```bash
# 条件が満たされるまで静かに待機、条件に一致した瞬間からトレース開始
./vendor/bin/xdebug-debug --break='User.php:85:$userId==0' --exit-on-break -- php app.php

# 完全な実行履歴を取得：どうやって$userId=0に至ったか
claude --continue "ユーザーID=0がどこから来て、どの処理で問題が起きているか分析して"
```

**結果:** 
- コードを一切変更せずに問題を捕捉
- 問題発生時の完全なコンテキストを取得  
- 本番環境でも安全に使用可能

---

## もう一つの例：「配列が空のときだけクラッシュ」

```bash
# 特定条件でのみ発生する問題を確実に捕捉
./vendor/bin/xdebug-debug --break='DataProcessor.php:42:empty($items)' --exit-on-break -- php batch.php

# AI分析で根本原因を特定
claude --continue "配列が空になる経路と、空配列でクラッシュする理由を分析"
```

**なぜこれが革新的か:**
- ❌ 通常のXdebug: 毎回ブレークポイントで止まる（効率悪い）
- ✅ 条件付きトレース: 問題条件まで高速実行、その瞬間だけ詳細記録

---

## Let AI See How Your Code Truly Runs 👁️

**GitHub:** [github.com/koriym/xdebug-mcp](https://github.com/koriym/xdebug-mcp)

**Install:** `composer require --dev koriym/xdebug-mcp`

**Badges:**
- 🟢 AI Native: YES
- 🟢 Runtime Data: YES  
- 🔴 var_dump(): NO
- 🔴 Guesswork: NO

*— Akihito Koriyama*