# PHP Xdebug MCP Server

<img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

> Enable AI to Debug PHP Autonomously, Beyond Human IDE Capabilities

[![Tools](https://img.shields.io/badge/AI_Native-YES-green)](https://modelcontextprotocol.io/)
[![Tools](https://img.shields.io/badge/VAR_DUMP-No-red)](https://modelcontextprotocol.io/)


---

## The Vision: AI That Debugs Better Than Humans

Imagine AI that doesn't just help you debug, but debugs autonomously—setting intelligent breakpoints, analyzing execution paths, and finding issues faster than any human with an IDE ever could.

## The Problem and The Promise

Today, even the most advanced AI adds `var_dump()` to your code—debugging like it's 1995. Meanwhile, humans click through IDE breakpoints, hoping to catch bugs by luck rather than logic.

**But what if AI could debug autonomously?** Setting intelligent conditional breakpoints, analyzing complete execution traces, finding bugs before humans even know they exist. This isn't just about replacing `var_dump()`—it's about AI that debugs more thoroughly and efficiently than any human with an IDE.

## The Solution

This MCP server enables AI to debug PHP autonomously with capabilities beyond human limitations:

- **Conditional breakpoints with full trace**: Capture complete execution history up to problem points
- **Programmatic control**: Execute thousands of debugging operations in seconds
- **Pattern recognition**: Identify subtle bugs humans would miss
- **Complete visibility**: See every variable, every call, every state change

The result: AI that doesn't just help you debug—it debugs better than you ever could.

### Before: AI Guesses From Static Code
```
Human: "Why does the total calculation fail?"
AI: "Looking at the code, maybe there's a type issue... try adding var_dump()..."
```

### After: AI Sees What Actually Happens
```
Human: "Why does the total calculation fail?"
AI: "Let me trace the execution... I see $item['price'] is '10.50' (string) at line 4, 
     causing concatenation instead of addition. The total becomes '010.50'."
```

**The difference: Speculation vs Observation**

## What AI Can Do That IDEs Can't

| Capability | Human with IDE | AI with Xdebug MCP |
|------------|---------------|-------------------|
| **Finding intermittent bugs** | Set breakpoint, hope to catch it | Set conditional breakpoint, capture every occurrence with full trace |
| **Analyzing complex flows** | Step through manually, miss details | Process entire execution trace, identify all patterns |
| **Performance analysis** | Profile once, check obvious bottlenecks | Analyze thousands of call paths, find hidden inefficiencies |
| **Coverage blind spots** | Run coverage, check report | Identify untested edge cases and generate test cases |
| **Race conditions** | Nearly impossible to catch | Set time-based conditionals, capture exact timing issues |

## How It Works

The breakthrough is **conditional tracing**—capturing everything up to a problem point:

1. **Set intelligent conditions** → Stop only when problems occur
2. **Capture full trace to that point** → See the complete journey
3. **Inspect state at condition** → Understand the exact situation
4. **AI analyzes both journey and destination** → Comprehensive understanding

Example:
```bash
./vendor/bin/xdebug-debug --break=Cart.php:89:$total<0 -- php checkout.php
```
This gives you:
- Complete trace showing how the total became negative
- All variable states when it happened
- Call stack and execution context

This is data no IDE provides in one shot, enabling AI to debug autonomously and more effectively than humans.

## Real Examples: AI Surpassing IDE Debugging

### The Power of Conditional Trace + State
```
Human with IDE: "I'll set a breakpoint at line 85 and hope to catch the bug"

AI with Xdebug MCP: "I'll trace everything until $id becomes 0, then analyze 
                     the complete execution path that led to this state"

./vendor/bin/xdebug-debug --break=User.php:85:$id==0 -- php app.php
# Result: Full trace showing HOW $id became 0, plus the state WHEN it happened
```

### Autonomous Bug Hunting
```
Traditional: Developer manually steps through code, checking variables

With MCP: AI sets intelligent conditional breakpoints, traces execution,
          and identifies issues without human intervention

Example:
claude --print "Find why user registration fails for emails with '+' symbol"
# AI autonomously:
# 1. Sets conditional breakpoint: --break=validate.php:*:strpos($email,'+')!==false
# 2. Traces execution to that point
# 3. Analyzes the complete path and state
# 4. Reports: "The sanitize() function at line 34 strips '+' before validation"
```

## Installation

### 1. Install
```bash
composer require --dev koriym/xdebug-mcp:1.x-dev
```

### 2. For AI Integration
```bash
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"
```

### 3. Configure AI Behavior (Optional)
```bash
# Teach AI to use runtime tracing instead of var_dump
cp vendor/koriym/xdebug-mcp/templates/CLAUDE_DEBUG_PRINCIPLES.md ./
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ./CLAUDE.md
```

## Command Line Tools

Five standalone debugging utilities that can generate debug data or analyze it with Claude:

### `xdebug-trace` - Execution Flow Analysis
```bash
# Generate trace file (saves to /tmp/xdebug_trace_*.xt)
./vendor/bin/xdebug-trace -- php app.php

# Generate trace AND have Claude analyze it immediately
./vendor/bin/xdebug-trace --claude -- php slow_function.php
```

### `xdebug-debug` - Intelligent Conditional Debugging
```bash
# Basic: Stop at first executable line
./vendor/bin/xdebug-debug script.php

# Advanced: Stop only when $id equals 123
./vendor/bin/xdebug-debug --break=User.php:85:$id==123 -- php register.php

# Key feature: Captures COMPLETE TRACE up to the breakpoint condition
# You get both the journey (trace) and destination (state at breakpoint)

# Multiple conditional breakpoints
./vendor/bin/xdebug-debug --break=Auth.php:42:$token==null,User.php:85:$id==0 -- php app.php
```

**Why this matters**: Unlike IDEs where humans guess where to set breakpoints, this captures the entire execution path leading to the problem, plus the exact state when the condition occurs.

### `xdebug-profile` - Performance Profiling
```bash
# Generate cachegrind profile (saves to /tmp/cachegrind.out.*)
./vendor/bin/xdebug-profile slow_script.php

# Generate profile AND have Claude analyze it
./vendor/bin/xdebug-profile --claude api_endpoint.php
```

### `xdebug-coverage` - Test Coverage Analysis
```bash
# Generate HTML coverage report (saves to coverage/ directory)
./vendor/bin/xdebug-coverage tests/UserTest.php

# Generate JSON format for programmatic processing
./vendor/bin/xdebug-coverage --format=json tests/
```

### `xdebug-phpunit` - PHPUnit with Xdebug
```bash
# Run tests with trace (generates trace files for each test)
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin

# Profile test performance
./vendor/bin/xdebug-phpunit --profile tests/

# Filter and trace specific tests
./vendor/bin/xdebug-phpunit --filter=testUserAuth tests/
```

## Two Paths to Autonomous Debugging

### 1. Direct CLI with AI Analysis
```bash
# Set intelligent conditional breakpoints
./vendor/bin/xdebug-debug --break=checkout.php:89:$total<0 -- php app.php
# Get: Full trace to the problem + exact state when it occurs

# Immediate AI analysis
./vendor/bin/xdebug-debug --break=checkout.php:89:$total<0 --claude -- php app.php
# AI analyzes both the journey and the destination
```

### 2. Full AI Autonomy via MCP
```bash
# Once MCP is configured, AI debugs independently
claude --print "Find why checkout totals go negative"

# AI autonomously:
# - Identifies relevant code paths
# - Sets conditional breakpoints ($total<0)  
# - Captures trace to that condition
# - Analyzes complete execution history
# - Reports root cause with fix
```

**The Revolution**: AI doesn't just read your code or guess at problems. It actively hunts bugs with more thoroughness than any human could achieve.

## 43 MCP Tools for Autonomous AI Debugging

Giving AI capabilities beyond human IDE limitations:

- **Conditional Debugging with Trace** (11 tools): Set intelligent breakpoints, capture full execution history
- **Performance Profiling** (4 tools): Analyze every function call with microsecond precision
- **Code Coverage** (6 tools): Know exactly what code paths are tested
- **Execution Tracing** (4 tools): Record complete program flow with all data
- **Configuration & Diagnostics** (22 tools): Memory, errors, stack analysis

**The key differentiator**: These tools work programmatically, allowing AI to perform thousands of debugging operations that would take humans days with an IDE.

## Real-World Examples

### Example 1: Catching Race Conditions
```bash
# Human approach: Nearly impossible to catch timing issues

# AI approach: Set time-sensitive conditional breakpoints
./vendor/bin/xdebug-debug --break=session.php:45:$timestamp<time() -- php app.php

# Result: Complete trace showing the exact sequence leading to timeout
# Plus: All variable states at the moment of failure
```

### Example 2: Finding Memory Leaks
```bash
# Traditional: Notice server crashes, guess at causes

# With AI: Autonomous memory tracking
claude --print "Find memory leaks in image processor"

# AI sets memory-based conditionals, tracks allocation patterns,
# identifies the exact line where memory isn't freed
```

### Example 3: Debugging Complex Business Logic
```bash
# The problem: "Discounts sometimes calculate wrong"

# Give AI autonomy:
claude --print "Debug why discounts are incorrect for premium members"

# AI response after autonomous debugging:
"Found the issue: At Cart.php:234, premium discount applies BEFORE tax calculation
 when it should apply AFTER. This happens only when items mix tax rates.
 Full trace shows 3 different code paths leading to this state..."
```

## Common Debugging Workflows

### When Something Breaks Conditionally
```bash
# Instead of guessing where the problem is, catch it when it happens
./vendor/bin/xdebug-debug --break=checkout.php:145:$total<0 -- php app.php
# Captures: Complete trace leading to negative total + exact state

# For race conditions
./vendor/bin/xdebug-debug --break=session.php:78:$timeout<time() -- php app.php
# Captures: Full execution path to timeout + all variables at that moment
```

### When You Need the Full Story
```bash
# Option 1: Simple trace for complete execution
./vendor/bin/xdebug-trace -- php broken_feature.php

# Option 2: Conditional trace + state inspection
./vendor/bin/xdebug-debug --break=Problem.php:42:$error!=null -- php app.php
# Better than IDE: You get the journey AND the destination
```

### Autonomous Bug Discovery
```bash
# Let AI proactively find bugs you don't even know exist
claude --print "Audit the codebase for potential issues"

# AI autonomously:
# - Traces common user flows
# - Sets intelligent breakpoints for edge cases
# - Identifies unreachable code
# - Finds race conditions
# - Reports security vulnerabilities
# All without human guidance
```

### When Tests Fail
```bash
# Trace specific failing test with full execution history
./vendor/bin/xdebug-phpunit tests/FailingTest.php::testMethod
# Output: /tmp/xdebug_trace_*.xt showing exactly why test fails

# Let AI debug test failures autonomously
claude --print "Debug why UserTest::testLogin fails intermittently"
# AI sets conditional breakpoints, captures failure conditions, identifies root cause

# Coverage analysis with AI insights
./vendor/bin/xdebug-coverage --claude tests/
# AI identifies not just uncovered code, but critical paths that need testing
```

## Tool Selection Guide

| Scenario | Best Tool | What You Get |
|----------|-----------|-------------|
| "Error happens sometimes" | `xdebug-debug --break=file:line:condition` | Full trace to error + state |
| "Need complete flow" | `xdebug-trace` | Every function call logged |
| "It's slow" | `xdebug-profile` | Function timing analysis |
| "Coverage gaps?" | `xdebug-coverage` | Exact line-by-line coverage |
| "Complex conditional bug" | AI via MCP | Autonomous multi-tool analysis |

**The game changer**: `xdebug-debug` with conditions gives you both the journey (complete trace) and destination (state at breakpoint)—something IDEs can't do.

## Working with Debug Data

### Trace Files
Generated by `xdebug-trace` and `xdebug-debug` (up to breakpoint):
```bash
# Simple trace
./vendor/bin/xdebug-trace -- php app.php
# Output: /tmp/xdebug_trace_*.xt

# Conditional debug with trace
./vendor/bin/xdebug-debug --break=app.php:50:$error!=null -- php app.php
# Output: Full trace up to line 50 when $error is set
```

The trace format shows:
- Function entry/exit with timestamps
- Parameters passed to each function
- Return values from functions
- Memory usage at each point
- Call depth and execution order

### Analyze with Any Tool
```bash
# Use with any AI
cat /tmp/xdebug_trace_*.xt | your-ai-tool

# Copy to ChatGPT, Gemini, or any LLM
# Process with custom scripts
# Import into analysis tools
```

**The Unique Value**: Conditional debugging traces show the exact path to problems, not just where they occur. This is intelligence no static analysis can provide.

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

## Verify AI's Autonomous Debugging

Test conditional debugging:
```bash
./vendor/bin/xdebug-debug --break=test.php:15:$x>100 -- php test.php
```

**What you should see**:
> "Breakpoint hit at test.php:15 where $x=101  
> Complete trace shows: $x initialized at line 3, incremented in loop starting line 8,
> reached 101 after 101 iterations taking 0.043 seconds..."

**With MCP, AI goes further**:
```bash
claude --print "Debug why $x exceeds 100 in test.php"
```
AI will autonomously set breakpoints, trace execution, and explain not just what happened, but why it's a problem and how to fix it.

## 🎯 Philosophy: AI-Native Development

Traditional debugging was designed for humans clicking through GUIs. AI-Native debugging is different:

- **Human debugging**: Visual interfaces, watch windows, manual stepping
- **AI-Native debugging**: Direct data access, bulk analysis, automated insights

We're not just giving AI access to debuggers. We're rebuilding debugging for the AI era.

### The AI-Native Principle

> **"Stop guessing. Start knowing."**

Transform AI from a code reader making educated guesses to an active debugger working with real runtime data.

## Resources

- [MOTIVATION.md](MOTIVATION.md) - Why this project exists and the vision behind it
- [Xdebug Documentation](https://xdebug.org/docs/)
- [MCP Specification](https://modelcontextprotocol.io/)
- [Claude MCP Guide](https://docs.anthropic.com/claude/docs/mcp)

## Technical Notes

- **Port 9004**: MCP Server (avoids IDE conflicts on 9003)
- **Protocol**: DBGp over TCP for debugging communication
- **Zero Config**: Tools auto-configure Xdebug settings
- **Non-invasive**: No source code modifications required
- **Innovation**: Conditional breakpoints that capture full execution trace—a capability IDEs don't provide
- **Scalability**: AI can manage hundreds of conditional breakpoints simultaneously

**The Key Innovation**: When `xdebug-debug` hits a conditional breakpoint, it provides both the complete execution trace leading to that point AND the current state. This dual visibility enables AI to understand not just what went wrong, but the entire chain of events that led to it.

---

## Welcome to the Future of Debugging

Where AI doesn't just assist debugging—it debugs better than humans ever could.

From blind guessing to complete runtime visibility.  
From manual IDE clicking to autonomous AI analysis.  
From finding bugs in hours to finding them in seconds.

- [MOTIVATION.md](MREOTIVATION.md) - Project vision and philosophy
- [Documentation](https://xdebug.org/docs/) 
- [MCP Spec](https://modelcontextprotocol.io/) 
- [Issues](https://github.com/koriym/xdebug-mcp/issues)

