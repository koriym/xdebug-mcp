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
./vendor/bin/xdebug-debug --break=test.php:10:$result==null -- php test.php
# You get: Complete trace showing HOW $result became null + state WHEN it happened

# 3. Enable AI autonomous debugging
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"
claude --print "Find why $result becomes null in test.php"
```

Watch as AI debugs with superhuman thoroughness—analyzing every execution path, every variable state, every possibility.

## Key Innovation: Journey + Destination

Unlike IDEs where you guess where to set breakpoints, our conditional debugging provides:

```bash
./vendor/bin/xdebug-debug --break=Cart.php:89:$total<0 -- php checkout.php
```

**You get both:**
- 🛤️ **The Journey**: Complete execution trace showing HOW the total became negative
- 📍 **The Destination**: All variable states WHEN it happened

This is intelligence no IDE provides in one shot, enabling AI to debug more effectively than humans.

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
```bash
# Stop when specific condition occurs, with full trace to that point
./vendor/bin/xdebug-debug --break=User.php:85:$id==0 -- php register.php

# Multiple conditions
./vendor/bin/xdebug-debug --break=Auth.php:42:$token==null,User.php:85:$id==0 -- php app.php

# With AI analysis
./vendor/bin/xdebug-debug --break=Cart.php:89:$total<0 --claude -- php app.php
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

## Real Example: From var_dump() to Victory

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
./vendor/bin/xdebug-debug --break=api.php:*:$response==null -- php test.php
# Captures: Complete trace to first null response
```

### Finding Race Conditions
```bash
# Catch timing-sensitive bugs
./vendor/bin/xdebug-debug --break=session.php:45:$timestamp<time() -- php app.php
# Result: Exact sequence of events leading to race condition
```

### Performance Bottlenecks
```bash
# Stop guessing, start measuring
./vendor/bin/xdebug-profile --claude slow_endpoint.php
# AI: "fetchUser() called 847 times (72% of execution time), add caching"
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
