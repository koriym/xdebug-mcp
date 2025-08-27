# PHP Xdebug MCP Server

  <img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

**Error message is the crime photo. Trace is the crime footage.**

> *Don't just see the crime. Watch how it happened.*

[![Debugging](https://img.shields.io/badge/AI_Native-YES-green)](https://github.com/koriym/xdebug-mcp)
[![Runtime](https://img.shields.io/badge/Runtime_Data-YES-green)](https://github.com/koriym/xdebug-mcp)
[![var_dump](https://img.shields.io/badge/var__dump()-NO-red)](https://github.com/koriym/xdebug-mcp)
[![Guesswork](https://img.shields.io/badge/Guesswork-NO-red)](https://github.com/koriym/xdebug-mcp)

---

## 🕵️ Debug Like a Detective

**Traditional debugging gives you a crime photo:**
```
Fatal error: Call to undefined method User::getName() 
in /app/controller.php on line 42
```
📸 *You see the victim, the weapon, the location. But who's the killer? What's the motive?*

**Trace-driven debugging gives you crime footage:**
```json
{
  "breakpoints": [
    {"location": "auth.php:15", "variables": {"$token": "invalid"}},
    {"location": "user.php:23", "variables": {"$user": null}}
  ],
  "trace_tail": [
    "validateToken() -> return false",
    "getUser(null) -> return null", 
    "null->getName() -> CRASH"
  ]
}
```
🎬 *Now you see the whole story. The crime, the motive, the evidence.*

## The Problem with Traditional Debugging

When AI helps debug PHP today, it adds `var_dump()` to your code—the same technique from 30 years ago. Why? Because **AI is debugging blind**, only able to read static code and guess what happens at runtime.

## The Solution: Crime Scene Investigation for Code

This MCP server turns AI into a digital detective with **crime footage analysis**:

### 🎬 From Snapshots to Full Movies
- **Crime Photos** → **Crime Footage**: Complete execution history, not just error messages
- **One-shot debugging**: Capture breakpoints + execution flow + variable states in a single run
- **Time-travel analysis**: See how variables evolved, not just their final values
- **Multi-scene investigation**: Compare different execution paths (userId=1 vs userId=2)

### 🕵️ AI Detective Capabilities
- **Pattern recognition**: Spot subtle bugs humans miss in complex traces
- **Evidence correlation**: Connect cause-and-effect across thousands of function calls  
- **Programmatic investigation**: Execute complex debugging scenarios in seconds
- **Complete crime scene**: Every variable, every call, every state change recorded

**The result**: AI doesn't just help you debug—it investigates code like a seasoned detective with perfect memory and infinite patience.

## JSON Schema Support

All debug output follows a strict JSON schema for reliable AI analysis:

```bash
./bin/xdebug-debug --break=file.php:10,file.php:20 --exit-on-break --json -- php script.php
```

Output includes schema validation: `"$schema": "https://koriym.github.io/xdebug-mcp/schema/xdebug-debug.json"`

**Schema guarantees:**
- ✅ Predictable structure for AI tools
- ✅ Type validation for variables  
- ✅ Location accuracy (file + line)
- ✅ Complete trace information

## 🎬 Forward Trace: The Debugging Revolution

**Forward Trace** is a revolutionary debugging paradigm that transforms how AI analyzes code by capturing the complete "movie" of program execution, not just snapshots.

### 🔄 Traditional vs Forward Trace Debugging

**Traditional Debugging (Reactive):**
```bash
# See the crash, guess backwards
Fatal error: Call to undefined method User::getName() in controller.php:42
# AI response: "Maybe check if $user is null? Try adding var_dump($user);"
```

**Forward Trace Debugging (Predictive):**
```bash
# Watch the complete execution story unfold
composer ai-test:loop > execution_story.json
# AI response: "I see exactly what happened: $user became null at line 23 when 
# validateToken() returned false, then line 42 tried to call getName() on null"
```

### 🎯 Core Innovation: Self-Explanatory Data

Forward Trace data contains **everything needed for analysis** without external context:

```json
{
  "context": "Testing user authentication with expired tokens",
  "breaks": [
    {"step": 1, "location": {"file": "auth.php", "line": 15}, 
     "variables": {"$token": "string: expired_abc123", "$user": "null: "}},
    {"step": 2, "location": {"file": "auth.php", "line": 23},
     "variables": {"$isValid": "bool: false", "$user": "null: "}},
    {"step": 3, "location": {"file": "controller.php", "line": 42},
     "variables": {"$user": "null: ", "$result": "uninitialized: "}}
  ],
  "trace": {"file": "/tmp/trace.xt", "content": [...]}
}
```

**Any AI can instantly understand:** This is authentication testing where a token expired, validation failed, and the subsequent method call crashed.

### 🌟 Revolutionary Benefits

#### 1. **AI Independence**
- No conversation history required
- Works across different AI systems (Claude ↔ GPT ↔ Gemini)
- Complete context embedded in the data

#### 2. **Time-Travel Debugging**
- See variable evolution over time: `$counter: 0 → 1 → 2 → 3`
- Track state changes: `$flags: {has_error: false} → {has_error: true}`
- Understand causation: Why did this variable become null?

#### 3. **Predictive Analysis**
- Spot problems **before** they cause crashes
- Identify patterns that lead to issues
- Understand complex execution flows instantly

#### 4. **Team Collaboration**
```bash
# Developer A creates debug data
composer ai-test:array > login_issue.json

# Developer B analyzes weeks later (or different AI system)
# All context preserved - no "what was this testing?" questions
```

### 🎪 Use Cases & Applications

#### **🔍 Bug Investigation**
```bash
# Reproduce and capture the complete bug story
composer forward-trace:error > bug_reproduction.json
# AI can see the exact sequence that leads to the error
```

#### **⚡ Performance Optimization**
```bash
# Profile with complete execution context
composer profile:nested > performance_analysis.json
# AI identifies bottlenecks with full variable context
```

#### **🧪 Test Analysis**
```bash
# Capture test execution with business context
composer ai-test:object > test_analysis.json
# AI understands both the technical execution AND the business purpose
```

#### **📚 Code Learning & Documentation**
```bash
# Generate self-documenting execution examples
./bin/xdebug-debug --context="How user registration validation works" --exit-on-break -- php register.php
# Creates executable documentation that shows real code behavior
```

#### **🎓 Educational & Training**
- Show junior developers actual execution flows
- Demonstrate complex algorithms with real data
- Create debugging training materials with concrete examples

### 🚀 Getting Started with Forward Trace

#### **Quick Demo:**
```bash
# Run a comprehensive analysis
composer ai-test:loop > demo.json

# See all available patterns
composer run-script --list | grep -E "(forward-trace|ai-test)"
```

#### **Available Patterns:**
- `ai-test:loop` - Variable progression in loops
- `ai-test:array` - Data structure manipulation  
- `ai-test:object` - Object state evolution
- `forward-trace:conditional` - Boolean logic flows
- `forward-trace:nested` - Complex iteration patterns
- `forward-trace:error` - Error handling scenarios

Each command generates **self-explanatory JSON** that tells the complete story of code execution.

### 💡 Why Forward Trace Changes Everything

**Traditional:** AI guesses what code *might* do  
**Forward Trace:** AI sees what code *actually* does

**Traditional:** "Add some debug prints and see what happens"  
**Forward Trace:** "Here's exactly what happened, step by step"

**Traditional:** Debugging requires conversation context  
**Forward Trace:** Debugging data is completely self-contained

This is not just better debugging—it's a fundamentally different relationship between AI and code analysis.

## Quick Start

```bash
# 1. Install
composer require --dev koriym/xdebug-mcp

# 2. Experience the magic immediately
# Create a simple test file
echo '<?php
$result = "success";
if (rand(0,1)) { $result = null; }
echo $result;
?>' > test.php

# Let AI analyze execution flow
./vendor/bin/xdebug-trace --claude -- php test.php
claude --continue "Find why \$result becomes null in test.php"

# 3. Enable AI autonomous debugging
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# 4. Experience conditional debugging magic
cp vendor/koriym/xdebug-mcp/demo.php .

# Conditional breakpoint: Only break when $id == 0
./vendor/bin/xdebug-debug --break=demo.php:60:'$id==0' --exit-on-break -- php demo.php

# Let AI analyze the trace data
claude --continue "The trace file shows execution up to the conditional breakpoint - analyze why processUser() received ID 0"

# Watch it pinpoint exactly when processUser() receives ID 0!
# ✅ Skips normal execution, stops only at the problematic condition  
# 🎯 Result: "Conditional breakpoint hit!" - found the exact moment

# Or analyze complete execution flow:
./vendor/bin/xdebug-trace -- php demo.php
claude --continue "Analyze this trace: why didn't \$result become null?"
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

#### Var_Dump Age 💃(1995)

```php
var_dump($cart);     // What's in cart?
var_dump($discount); // Check discount  
var_dump($total);    // Why is this negative?!
die("HERE");         // Getting desperate...
// 2 hours later: still guessing...
```

#### AI-Native Age 🤖(2025)

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

# JSON output for AI integration
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' --json --exit-on-break -- php app.php
# Output: {"trace_file":"/tmp/trace.123.xt","lines":15,"size":0.8,"command":"php app.php"}

# AI analysis with structured data
./vendor/bin/xdebug-debug --break='error.php:20:$error!=null' --json -- php app.php | claude --analyze-trace
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

## Installation & Setup

### Basic Installation
```bash
composer require --dev koriym/xdebug-mcp
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
