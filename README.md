# PHP Xdebug MCP Server

  <img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

**Enable AI to Debug PHP Autonomously, Beyond Human IDE Capabilities**

[![Debugging](https://img.shields.io/badge/AI_Native-YES-green)](https://github.com/koriym/xdebug-mcp)
[![Runtime](https://img.shields.io/badge/Runtime_Data-YES-green)](https://github.com/koriym/xdebug-mcp)
[![var_dump](https://img.shields.io/badge/var__dump()-NO-red)](https://github.com/koriym/xdebug-mcp)
[![Guesswork](https://img.shields.io/badge/Guesswork-NO-red)](https://github.com/koriym/xdebug-mcp)

---

## The Vision: AI That Debugs Better Than Humans

Imagine AI that doesn't just help you debug, but debugs autonomously—setting intelligent breakpoints, analyzing execution paths, and finding issues faster than any human with an IDE ever could.

## The Problem

When you ask AI to debug PHP today, it adds `var_dump()` to your code—the same technique from 30 years ago. Why? Because **AI is debugging blind**, only able to read static code and guess what happens at runtime.

## The Solution

This MCP server enables AI to debug PHP autonomously with capabilities beyond human limitations:

- **Conditional breakpoints with full trace**: Capture complete execution history up to problem points
- **Programmatic control**: Execute thousands of debugging operations in seconds
- **Pattern recognition**: Identify subtle bugs humans would miss
- **Complete visibility**: See every variable, every call, every state change

The result: AI that doesn't just help you debug—it debugs better than you ever could.

## Quick Start

```bash
# 1. Install
composer require --dev koriym/xdebug-mcp:1.x-dev

# 2. Experience the power of conditional debugging
./vendor/bin/xdebug-debug --break='test.php:10:$result==null' -- php test.php
# You get: Complete trace showing HOW $result became null + state WHEN it happened

# 3. Enable AI autonomous debugging
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# Create a test file to debug
echo '<?php
$result = "success";
if (rand(0,1)) { $result = null; }
echo $result;
?>' > test.php

# Analyze execution with AI
./vendor/bin/xdebug-trace --claude -- php test.php
claude --continue "Find why \$result becomes null in test.php"

# 4. Experience conditional debugging magic
cp vendor/koriym/xdebug-mcp/demo.php .

# Conditional breakpoint: Only break when $id == 0
./vendor/bin/xdebug-debug --break=demo.php:60:'$id==0' --exit-on-break -- php demo.php

# 🔍 First, examine the trace file yourself:
# Output: "📊 Trace file generated up to conditional breakpoint: /tmp/trace.1034012359.xt (11 lines, 0.3KB)"
cat /tmp/trace.1034012359.xt | head -20
# Look for: Line 60, $id parameter, call stack leading to the issue

# 🤖 Then let AI analyze the same data:
# AI can see file size and choose appropriate reading strategy (full read vs tail/head)
claude --continue "The trace file shows execution up to the conditional breakpoint - analyze why processUser() received ID 0"

# Watch it pinpoint exactly when processUser() receives ID 0!
# ✅ Skips normal execution, stops only at the problematic condition  
# 🎯 Result: "Conditional breakpoint hit!" - found the exact moment

# For interactive step debugging:
./vendor/bin/xdebug-debug demo.php

# Traditional trace analysis:
./vendor/bin/xdebug-trace -- php demo.php

# 👀 Inspect trace manually first:  
cat /tmp/trace.*.xt | head -10
# Example trace reading:
# Level 2: rand() called with args (0, 1) → returned 0
# Level 1: if (0) is false → $result stays "success" 
# Result: Human can trace the exact execution path!

# 🤖 Compare with AI analysis:
claude --continue "Analyze this trace: why didn't \$result become null?"
# AI instantly identifies: rand() returned 0, condition false, no assignment
```

**The magic**: Skip normal execution, catch bugs red-handed with full context.

## Key Innovation: Journey + Destination

Unlike IDEs where you guess where to set breakpoints, our conditional debugging provides:

```bash
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' -- php checkout.php
```

**You get both:**
- 🛤️ **The Journey**: Complete execution trace showing HOW the total became negative
- 📍 **The Destination**: All variable states WHEN it happened

This is intelligence no IDE provides in one shot, enabling AI to debug more effectively than humans.

## Real Example: From Guesswork to Evidence

### 1995 vs 2025: From Guesswork Driven to Technology Driven

**Before (1995: Guesswork Driven 💃)**
```php
var_dump($cart);     // What's in cart?
var_dump($discount); // Check discount  
var_dump($total);    // Why is this negative?!
die("HERE");         // Getting desperate...
// 2 hours later: still guessing...
```

**After (2025: Technology Driven 🤖)**
```bash
./vendor/bin/xdebug-debug --break=checkout.php:89:$total<0 --json --exit-on-break -- php app.php

# AI analyzes trace and reports in 30 seconds:
"Found it: At checkout.php:89, $50 discount applied to $30 cart = -$20 total
 Trace shows: removeItem() at line 67 doesn't trigger recalculateDiscount()
 Fix: Add $this->recalculateDiscount() after line 67"
```

**The Difference:**
- **Guesswork Driven**: Hours of assumptions, code pollution, manual trial-and-error
- **Technology Driven**: 30 seconds, zero code changes, AI-powered evidence-based analysis

## What AI Can Do That IDEs Can't

| Capability | Human with IDE | AI with Xdebug MCP |
|------------|---------------|-------------------|
| **Finding intermittent bugs** | Set breakpoint, hope to catch it | Set conditional breakpoint, capture every occurrence with full trace |
| **Analyzing complex flows** | Step through manually, miss details | Process entire execution trace, identify all patterns |
| **Performance analysis** | Profile once, check obvious bottlenecks | Analyze thousands of call paths, find hidden inefficiencies |
| **Coverage blind spots** | Run coverage, check report | Identify untested edge cases and generate test cases |
| **Race conditions** | Nearly impossible to catch | Set time-based conditionals, capture exact timing issues |

## Command Line Tools

### `xdebug-trace` - Complete Execution Flow
```bash
# Generate trace file
./vendor/bin/xdebug-trace -- php app.php
# Output: /tmp/xdebug_trace_*.xt (every function call, param, return value)

# With immediate AI analysis
./vendor/bin/xdebug-trace --claude -- php app.php
```

### `xdebug-debug` - Intelligent Conditional Debugging

**Breakpoint Syntax: `file.php:line:condition`**
```bash
# Stop when specific condition occurs, with full trace to that point
./vendor/bin/xdebug-debug --break='User.php:85:$id==0' -- php register.php

# Multiple conditions (comma-separated)
./vendor/bin/xdebug-debug --break='Auth.php:42:$token==null,User.php:85:$id==0' -- php app.php

# Auto-exit with trace file output
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' --exit-on-break -- php app.php
claude --continue "Analyze why total became negative"
```

### `xdebug-profile` - Performance Analysis
```bash
# Microsecond-precision profiling
./vendor/bin/xdebug-profile api_endpoint.php
# Output: /tmp/cachegrind.out.*

# AI-powered optimization suggestions
./vendor/bin/xdebug-profile --claude api_endpoint.php
```

### `xdebug-coverage` - Test Coverage
```bash
# See exactly what code is tested
./vendor/bin/xdebug-coverage tests/
# Output: coverage/index.html
```

### `xdebug-phpunit` - Test Debugging
```bash
# Trace test execution
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin

# Profile test performance
./vendor/bin/xdebug-phpunit --profile tests/
```

## Real Example: From Guesswork to Evidence

### Before (The var_dump() Dance)
```php
var_dump($cart);     // What's in cart?
var_dump($discount); // Check discount
var_dump($total);    // Why is this negative?!
die("HERE");         // Getting desperate...
// 2 hours later: still guessing
```

### After (Intelligent Debugging)
```bash
./vendor/bin/xdebug-debug --break=checkout.php:89:$total<0 --claude -- php app.php

# AI reports in 30 seconds:
"Found it: At checkout.php:89, $50 discount applied to $30 cart = -$20 total
 Trace shows: removeItem() at line 67 doesn't trigger recalculateDiscount()
 Fix: Add $this->recalculateDiscount() after line 67"
```

## Installation & Setup

### Basic Installation
```bash
composer require --dev koriym/xdebug-mcp:1.x-dev
```

### AI Integration (Recommended)
```bash
# Enable MCP for Claude Desktop
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# Teach AI to prefer traces over var_dump
cp vendor/koriym/xdebug-mcp/templates/CLAUDE_DEBUG_PRINCIPLES.md ./
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ./CLAUDE.md
```

## Two Modes of Operation

### 1. Direct CLI Usage
```bash
# Generate debug data
./vendor/bin/xdebug-trace -- php script.php

# Feed to any AI
cat /tmp/xdebug_trace_*.xt | your-ai-tool
# Works with ChatGPT, Claude, Gemini, any LLM
```

### 2. Autonomous AI Debugging (via MCP)
```bash
# AI hunts bugs independently
claude --print "Find why users get logged out randomly"

# AI autonomously:
# - Identifies relevant code paths
# - Sets conditional breakpoints
# - Captures execution traces
# - Analyzes patterns
# - Reports root cause with fix
```

## Common Debugging Patterns

### Catching Intermittent Bugs
```bash
# Instead of hoping to catch it, guarantee you will
./vendor/bin/xdebug-debug --break='api.php:123:$response==null' -- php test.php
# Captures: Complete trace to first null response
# Note: Line wildcards (*) are not yet supported - use specific line numbers
```

### Finding Race Conditions
```bash
# Catch timing-sensitive bugs
./vendor/bin/xdebug-debug --break='session.php:45:$timestamp<time()' -- php app.php
# Result: Exact sequence of events leading to race condition
```

### Performance Bottlenecks
```bash
# Stop guessing, start measuring
./vendor/bin/xdebug-profile --claude slow_endpoint.php
# AI: "fetchUser() called 847 times (72% of execution time), add caching"
```

## Reading Trace Files Like a Detective

**Trace File Format (.xt):**
```
Level FuncID Time     Memory   Function   UserDef  Filename:Line  Args/Return
2     1      0.000329 396784   rand       0        test.php:3     0  1
2     1      1        0.000337 396848                              R  0
```

**What Each Column Means:**
- **Level**: Call stack depth (1=main, 2=nested function)
- **FuncID**: Unique function call identifier  
- **Time**: Execution timestamp (microseconds)
- **Memory**: Current memory usage (bytes)
- **Function**: Function name being called/returned
- **UserDef**: 1=your code, 0=built-in PHP function
- **Args/Return**: Function parameters or return value (R)

**Human Reading Strategy:**
1. **Follow the Level**: Track call hierarchy depth
2. **Watch Memory**: Spot memory leaks or excessive usage
3. **Time Gaps**: Identify slow operations
4. **Return Values**: See what functions actually returned
5. **Arguments**: Verify correct parameters were passed

**Example Detective Work:**
```bash
# Find when variable became null
cat trace.xt | grep -C3 "null"

# Track specific function calls  
cat trace.xt | grep "calculateTotal"

# Memory usage progression
cat trace.xt | awk '{print $4}' | head -20
```

## The 43 Tools Arsenal

- **Execution Tracing** (4): Complete function flow with all data
- **Conditional Debugging** (11): Intelligent breakpoints with full traces
- **Performance Profiling** (4): Microsecond-precision timing analysis
- **Code Coverage** (6): Exact test coverage mapping
- **Diagnostics** (22): Memory, errors, stack, configuration

Each tool provides a different lens into runtime behavior. Combined, they give AI superhuman debugging capabilities.

## Working with Debug Data

Trace files are portable and universal:
```bash
# Generated files (plain text)
/tmp/xdebug_trace_*.xt     # Execution traces
/tmp/cachegrind.out.*       # Performance profiles
coverage/index.html         # Coverage reports

# Use with any tool or AI
cat /tmp/xdebug_trace_*.xt | analyze
# Compatible with ChatGPT, Claude, Gemini, custom scripts
```

## Why This Matters

Every time AI uses `xdebug_start_trace()` instead of suggesting `var_dump()`, we're not just solving bugs faster—we're elevating the entire profession. We're moving from guesswork to detective work, from assumptions to facts.

## The Paradigm Shift

| Traditional Debugging | AI-Native Debugging |
|----------------------|-------------------|
| Human guesses where to look | AI analyzes everything |
| Manual stepping through code | Automatic pattern recognition |
| Limited by attention span | Processes unlimited data |
| Misses subtle patterns | Finds hidden correlations |
| var_dump() and pray | Trace and know |

## Resources

- 📖 [**MOTIVATION.md**](MOTIVATION.md) - The story and philosophy behind this project
- 📚 [Xdebug Documentation](https://xdebug.org/docs/)
- 🔧 [MCP Specification](https://modelcontextprotocol.io/)
- 💡 [Templates & Examples](templates/)
