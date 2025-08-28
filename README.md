# PHP Xdebug MCP Server

<img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

> **Error message is the crime photo. Trace is the crime footage.**  
> Don't just see the crime. Watch how it happened.

**Enable AI to Debug PHP Autonomously, Beyond Human IDE Capabilities**

[![Debugging](https://img.shields.io/badge/AI_Native-YES-green)](https://github.com/koriym/xdebug-mcp)
[![Runtime](https://img.shields.io/badge/Runtime_Data-YES-green)](https://github.com/koriym/xdebug-mcp)
[![var_dump](https://img.shields.io/badge/var__dump()-NO-red)](https://github.com/koriym/xdebug-mcp)
[![Guesswork](https://img.shields.io/badge/Guesswork-NO-red)](https://github.com/koriym/xdebug-mcp)

---

## The Problem: AI is Debugging Blind

When you ask AI to debug PHP today, it adds `var_dump()` to your code—the same technique from 30 years ago.

Why? Because **AI is debugging blind**, only able to read static code and guess what happens at runtime.

## The Solution: Give AI Eyes

This MCP server enables AI to debug PHP with superhuman capabilities:

- **See the complete crime footage**: Not just where it crashed, but HOW it got there
- **Track variable evolution**: Watch every variable change step-by-step with `--steps`
- **Set intelligent traps**: Conditional breakpoints that capture the exact moment things go wrong
- **Share debug sessions**: Schema-validated JSON that any AI can analyze
- **Process unlimited data**: Analyze patterns humans would miss
- **Debug without touching code**: Zero var_dumps, zero pollution

## Quick Start: Experience the Magic in 2 Minutes

```bash
# 1. Install
composer require --dev koriym/xdebug-mcp

# 2. Create a test bug
echo '<?php
$result = "success";
if (rand(0,1)) { $result = null; }
echo $result;
?>' > test.php

# 3. Traditional way (blind guessing)
# You'd add: var_dump($result); var_dump(rand(0,1)); die();

# 4. AI-Native way (complete visibility)
./vendor/bin/xdebug-trace --claude -- php test.php
claude --continue "Why does \$result sometimes become null?"

# 5. Forward Trace™ (two powerful modes)

# Mode 1: Conditional - catch bugs red-handed
./vendor/bin/xdebug-debug --break='test.php:3:$result==null' --exit-on-break -- php test.php
# Output: Trace showing EXACTLY when and why $result became null

# Mode 2: Step Recording - watch variables evolve
./vendor/bin/xdebug-debug --break='test.php:2' --steps=10 --json -- php test.php
# Output: JSON with variable state at each of 10 steps

# 6. Enable AI autonomous debugging
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# 7. Use AI-friendly slash commands (in Claude Code)
/x-debug "test.php" "test.php:3:$result==null" "" "Investigate null result bug"
/x-trace script="test.php" context="Trace execution flow"
/x-profile script="test.php" context="Performance analysis"
```

## 🔄 The Paradigm Shift: Backward → Forward

### Traditional Backtrace (Looking Backward)
```
Error occurred ← Stack unwinding ← Guessing root cause
"Something died. Where did it come from?"
```

### Forward Trace (Moving Forward)
```
Execution starts → Variable evolution → Problem detection
"Watch the story unfold until something goes wrong"
```

## Why Forward Trace Changes Everything

### The Fundamental Difference

| Aspect | Backtrace | Forward Trace |
|--------|-----------|--------------|
| **Time Direction** | Past tense: "What happened?" | Present progressive: "What's happening?" |
| **Analysis Type** | Post-mortem autopsy | Live documentary |
| **Problem Discovery** | After crash investigation | During execution monitoring |
| **Debugging Philosophy** | Criminal investigation | Preventive medicine |

### What is Forward Trace?

```bash
# Backtrace: Start from the corpse, work backward
Fatal error at line 89
Stack trace: main() → processCart() → calculateTotal()
# "The patient is dead. How did they die?"

# Forward Trace: Two powerful modes

# 1. Conditional: Record until problem occurs
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' --exit-on-break -- php app.php
# "Film until symptoms appear"

# 2. Step Recording: Watch variable evolution step-by-step
./vendor/bin/xdebug-debug --break='Cart.php:89' --steps=100 --json -- php app.php
# "Record next 100 steps of variable changes"
```

### The Innovation: Narrative Debugging

Forward Trace creates the **story** of your execution:

```json
{
  "chapter_1": "Initialization: All variables healthy",
  "chapter_2": "Processing loop: Memory growing",
  "chapter_3": "Conditional branch: State mutation detected",
  "chapter_4": "Critical moment: $total becomes negative"
}
```

### Real Example: Predictive vs Reactive

```bash
# Problem: Memory leak in data processing

# Backtrace approach (reactive):
Fatal error: Allowed memory size exhausted at DataProcessor.php:234
# Now you know you're dead, but not how you got sick

# Forward Trace approach (predictive):
./vendor/bin/xdebug-debug --break='DataProcessor.php:*:memory_get_usage()>50000000' --exit-on-break -- php app.php
# Watch memory grow in real-time, catch the leak as it develops
# Result: "Array never cleared in loop at line 145, growing by 1MB per iteration"
```

### The Technical Impact: From Forensics to Prevention

**Variable Health Monitoring**
```json
{"step": 1, "variables": {"$users": "array(0)", "$memory": "2MB"}},
{"step": 50, "variables": {"$users": "array(1250)", "$memory": "15MB"}},
{"step": 100, "variables": {"$users": "array(2500)", "$memory": "31MB"}}
// Pattern detected: Linear memory growth → Leak identified before crash
```

**AI-Powered Pattern Recognition**
- Backtrace: "Tell me why it died"
- Forward Trace: "Watch for symptoms and predict the disease"

### The Four Innovations

1. **Non-Destructive Observation**: Record without modifying code
2. **Conditional Recording**: Stop recording at the exact moment of interest
3. **Step-by-Step Evolution**: Track variable changes through specified steps
4. **Schema-Validated Output**: Structured JSON that any AI can analyze

### Step Recording: Watch Variables Evolve

Forward Trace's `--steps` mode creates a **variable evolution timeline**:

```bash
# Record 50 steps of execution from line 17
./vendor/bin/xdebug-debug --break='loop.php:17' --steps=50 --json -- php app.php
```

**Output: Complete variable state at EACH step**
```json
{
  "$schema": "https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json",
  "breaks": [
    {
      "step": 1,
      "location": {"file": "loop.php", "line": 17},
      "variables": {
        "$total": "int: 0",
        "$items": "array: []"
      }
    },
    {
      "step": 2,
      "location": {"file": "loop.php", "line": 21},
      "variables": {
        "$total": "int: 15",
        "$items": "array: [0: 'apple']"
      }
    }
  ],
  "trace": {
    "file": "/tmp/trace.1034012359.xt",
    "content": ["Complete execution trace..."]
  }
}
```

**Why This Matters**:
- **Schema-Validated**: Any AI can understand the structure
- **Session-Independent**: Share debug data across teams/AIs
- **Time-Travel Debugging**: Replay exact execution state
- **Pattern Detection**: AI can spot trends in variable evolution

### The Power of Schema: Debug Once, Analyze Everywhere

```bash
# Generate schema-validated debug session
./vendor/bin/xdebug-debug --break='bug.php:42' --steps=100 --json > debug.json

# Now this debug session is portable:
cat debug.json | claude --analyze      # Analyze with Claude
cat debug.json | chatgpt --debug       # Get second opinion from ChatGPT  
cat debug.json | python analyze.py     # Custom analysis scripts
```

**Every output follows**: [`https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json`](https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json)

This means:
- **No vendor lock-in**: Use any AI or tool
- **Team collaboration**: Share exact debug state
- **Reproducible analysis**: Same data, consistent insights
- **Historical debugging**: Analyze past issues with new AI capabilities

## Usage Examples

### For AI Assistants (Claude Code)

**MCP Slash Commands** (Recommended for AI workflows):
```bash
# Variable debugging - replace var_dump() completely
/x-debug "user.php" "user.php:42:$user==null" "" "Check user validation"

# Performance analysis - identify bottlenecks instantly  
/x-profile script="slow-endpoint.php" context="API performance investigation"

# Execution tracing - understand complex logic flows
/x-trace script="authentication.php" context="Login flow analysis"

# Test coverage analysis
/x-coverage script="vendor/bin/phpunit UserTest.php" context="Coverage verification"

# Context memory - repeat with same settings
/x-debug "test.php" "test.php:3" "" "Updated context"  # Standard positional format
```

### For Developers (CLI)

**Direct CLI Commands** (Recommended for scripting/CI):
```bash
# Variable debugging with conditional breakpoints
./bin/xdebug-debug --break='user.php:42:$user==null' --exit-on-break -- php user.php

# Performance profiling with JSON output
./bin/xdebug-profile --json -- php slow-endpoint.php

# Execution tracing for AI analysis
./bin/xdebug-trace --json -- php authentication.php

# Code coverage generation
./bin/xdebug-coverage -- php vendor/bin/phpunit UserTest.php
```

### Protocol-Level Access (Advanced)

**Direct MCP JSON-RPC** (For custom integrations):
```bash
# List available commands
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/list"}' | php bin/xdebug-mcp

# Execute trace command (structured parameters)
echo '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"test.php","context":"Debug session"}}}' | php bin/xdebug-mcp

# Execute with CLI-style normalization
echo '{"jsonrpc":"2.0","id":3,"method":"prompts/get","params":{"name":"x-trace","arguments":{"cli":"--json:bool=true -- php test.php"}}}' | php bin/xdebug-mcp
```

### CLI Argument Normalization

**Convert CLI-style arguments to MCP parameters** using strict normalization rules:
```bash
# Input: CLI string
--json:bool=true --context:str="Debug session" -- php script.php

# Output: MCP params
{
  "json": true,
  "context": "Debug session", 
  "args": ["php", "script.php"]
}
```

**Normalization Rules:**
- Long options only: `--key=value`
- Type annotations: `--key:type=value` (str/int/float/bool/json)
- Position args after `--`: stored in `args` array
- No ambiguity: explicit types required for non-strings

## The Revolutionary Concept: Journey + Destination

Traditional debugging gives you a **crime photo**:
```
Fatal error: Undefined variable $total at checkout.php line 89
```
You see the body, but not the killer.

Forward Trace gives you **crime footage** in two ways:

### Mode 1: Conditional Recording
```bash
./vendor/bin/xdebug-debug --break='checkout.php:89:$total<0' -- php app.php
```
Records execution UNTIL condition is met.

### Mode 2: Step Recording
```bash
./vendor/bin/xdebug-debug --break='checkout.php:89' --steps=100 --json -- php app.php
```
Records next 100 steps of variable evolution from breakpoint.

**You get BOTH**:
- 🛤️ **The Journey**: Complete execution path or step-by-step variable changes
- 📍 **The Destination**: All variable states at critical moments
- 📊 **The Analysis**: Schema-validated JSON for any AI to process

This is intelligence no IDE provides—enabling AI to debug more effectively than humans.

## Real World Impact: 30 Years vs 30 Seconds

### The var_dump() Era (1995-2024)
```php
var_dump($cart);     // What's in cart?
var_dump($discount); // Check discount  
var_dump($total);    // Why is this negative?!
die("HERE");         // Getting desperate...
// 2 hours later: still guessing...
```

### The AI-Native Era (2025+)
```bash
./vendor/bin/xdebug-debug --break='checkout.php:89:$total<0' --json --exit-on-break -- php app.php

# AI analyzes and reports in 30 seconds:
"Found it: At checkout.php:89, $50 discount applied to $30 cart = -$20 total
 Root cause: removeItem() at line 67 doesn't trigger recalculateDiscount()
 Fix: Add $this->recalculateDiscount() after line 67"
```

**The difference**: From hours of guesswork to seconds of evidence-based analysis.

## Command Line Arsenal

### For Human Analysis (Interactive Debugging)
```bash
# Interactive step debugger with REPL interface
./vendor/bin/xdebug-debug -- php app.php
# Commands: s(tep), o(ver), c(ontinue), p <var>, l(ist), q(uit)
# Watch variables change in real-time as you step through code
```

<details>
<summary>🖥️ See Interactive Debugger in Action</summary>

![Interactive Step Debugging](https://koriym.github.io/xdebug-mcp/images/interactive-debugger.png)

</details>

```bash
# Generate traces for manual inspection
./vendor/bin/xdebug-trace -- php app.php
cat /tmp/xdebug_trace_*.xt | less
```

### For AI Analysis (Automated with Forward Trace)
```bash
# Conditional debugging with auto-exit
./vendor/bin/xdebug-debug --break='Auth.php:42:$token==null' --exit-on-break -- php app.php

# Step recording: Watch 100 steps of variable evolution
./vendor/bin/xdebug-debug --break='DataProcessor.php:145' --steps=100 --json -- php app.php
# Output: JSON with variable state at each step + execution trace

# Multiple conditions (catch first occurrence)
./vendor/bin/xdebug-debug --break='Auth.php:42:$token==null,User.php:85:$id==0' --exit-on-break -- php app.php

# Combine steps with conditions
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' --steps=50 --json -- php app.php
# Record 50 steps OR until condition met, whichever comes first

# Schema-validated output for cross-AI analysis
./vendor/bin/xdebug-debug --break='error.php:20' --steps=200 --json > debug-session.json
# Share debug-session.json with any AI for analysis

# Direct AI integration
./vendor/bin/xdebug-trace --claude -- php slow_endpoint.php
./vendor/bin/xdebug-profile --claude -- php api.php
```

## The 28 MCP Tools

- **Performance Profiling** (4): Microsecond-precision timing
- **Code Coverage** (5): Exact test coverage mapping
- **Execution Tracing** (4): Complete function flow with Forward Trace™ & Step Recording
- **Memory & Diagnostics** (5): Usage patterns and leaks
- **Error Collection** (3): Comprehensive error tracking
- **Function Monitoring** (2): Specific function analysis
- **Stack Analysis** (3): Call hierarchy inspection
- **Configuration** (2): Dynamic Xdebug control

## What AI Can Do That You Can't

| Task | Human with IDE | AI with Xdebug MCP |
|------|----------------|-------------------|
| **Find intermittent bugs** | Hope to catch it | Forward Trace™ guarantees capture |
| **Track variable evolution** | Manual stepping, lose track | Step Recording captures every change |
| **Analyze complex execution** | Get lost in details | Process entire flow instantly |
| **Share debug sessions** | Screenshots and descriptions | Schema-validated JSON for any AI |
| **Spot race conditions** | Nearly impossible | Detect timing patterns automatically |
| **Debug without code changes** | Must add var_dumps | Zero code pollution |

## Installation & Setup

```bash
# Basic installation
composer require --dev koriym/xdebug-mcp

# Enable AI autonomous debugging
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# Test Forward Trace with conditional breakpoint
echo '<?php for($i=0; $i<5; $i++) echo $i;' > test.php
./vendor/bin/xdebug-debug --break='test.php:1:$i==3' --exit-on-break -- php test.php
# Should output: Conditional breakpoint hit! Trace file generated: /tmp/trace.*.xt

# Test Step Recording
./vendor/bin/xdebug-debug --break='test.php:1' --steps=5 --json -- php test.php
# Should output: JSON with 5 steps of variable evolution
```

## Common Debugging Patterns with Forward Trace

### Catching Null Values (The #1 PHP Bug)
```bash
# Instead of var_dump everywhere
./vendor/bin/xdebug-debug --break='User.php:85:$user==null' --exit-on-break -- php app.php
# Get: Complete execution path to the null assignment
```

### Variable Evolution Analysis
```bash
# Watch how array grows in a loop
./vendor/bin/xdebug-debug --break='DataProcessor.php:45' --steps=100 --json -- php app.php
# Get: JSON showing array size at each iteration
# AI can detect: "Array grows exponentially, not linearly - memory leak!"
```

### Finding Performance Bottlenecks
```bash
# Catch slow queries
./vendor/bin/xdebug-debug --break='DB.php:45:$executionTime>1.0' --exit-on-break -- php app.php
# Get: Full trace of what led to the slow query
```

### Memory Leak Detection
```bash
# Record memory growth step-by-step
./vendor/bin/xdebug-debug --break='app.php:100' --steps=200 --json -- php app.php
# AI analyzes: "Memory increases 2MB per step, array never cleared"
```

### Race Condition Detection
```bash
# Catch timing issues
./vendor/bin/xdebug-debug --break='Lock.php:23:$isLocked==true' --exit-on-break -- php concurrent.php
# Get: Precise sequence of events leading to deadlock
```

### Cross-AI Debugging Sessions
```bash
# Generate schema-validated debug data
./vendor/bin/xdebug-debug --break='complex.php:200' --steps=500 --json > session.json

# Now ANY AI can analyze it:
# - Share with ChatGPT
# - Send to Claude
# - Process with custom scripts
# All understand the same schema: https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json
```

## Reading Forward Traces Like a Detective

```
Level FuncID Time     Memory   Function   UserDef  Filename:Line  Args/Return
1     0      0.000123 395840   {main}     1        app.php:1      
2     1      0.000329 396784   calculate  1        app.php:15     30 50
2     1      0.000337 396848                                      R -20
1     0      0.000342 396784   BREAKPOINT                        $total<0
```

The trace shows:
- **The journey**: calculate(30, 50) was called
- **The problem**: Returned -20 (R -20)
- **The trigger**: BREAKPOINT when $total<0

## Why Forward Trace Changes Everything

Every bug has a story. Traditional debugging shows you the ending. Forward Trace shows you the plot.

| Debugging Evolution | Capability |
|--------------------|------------|
| **var_dump() Era** (1995) | See variable state |
| **IDE Debugger Era** (2000) | Step through code |
| **Trace Era** (2010) | Record everything |
| **Forward Trace Era** (2025) | Record until problem |

## The Paradigm Shift

| Old World | New World with Forward Trace |
|-----------|------------------------------|
| Debug after the fact | Catch problems as they happen |
| Guess where to look | Know exactly where to look |
| Manual stepping through IDE | Automatic step recording with `--steps` |
| Hours of investigation | Seconds of AI analysis |
| Debug session dies with IDE | Schema-validated JSON lives forever |
| One developer, one debugger | Any AI, any time, same debug data |
| Reproduce bugs manually | Capture bugs automatically |

## Troubleshooting

Having issues? Check our comprehensive troubleshooting guide:

📋 **[TROUBLESHOOTING.md](TROUBLESHOOTING.md)** - Common issues and solutions

**Quick Diagnostics:**
```bash
./bin/check-env          # Verify Xdebug installation
composer test-json       # Test all functionality
MCP_DEBUG=1 php bin/xdebug-mcp  # Enable debug mode
```

**Common Issues:**
- **Context Memory**: Clear with `rm /tmp/xdebug-mcp-context.json`
- **Breakpoint Format**: Use `file.php:line` or `file.php:line:condition`
- **File Not Found**: Use absolute paths or check working directory
- **Permission Denied**: Check file/directory permissions

## Resources

**Essential Reading:**
- 🎯 **[docs/debug-guidelines.md](docs/debug-guidelines.md)** - **READ FIRST** - Forward Trace methodology and best practices
- 📖 [**MOTIVATION.md**](MOTIVATION.md) - Why we built this
- 🔧 [**TROUBLESHOOTING.md**](TROUBLESHOOTING.md) - Common issues and solutions

**Additional Resources:**
- 🎬 [Forward Trace Demo](https://github.com/koriym/xdebug-mcp/demo) - See it in action
- 📚 [Xdebug Documentation](https://xdebug.org/docs/)
- 🔧 [MCP Specification](https://modelcontextprotocol.io/)

---

**Stop debugging blind. Give AI the power of Forward Trace™.**

*Forward Trace combines conditional breakpoints with step-by-step variable recording, producing schema-validated JSON that any AI can analyze. Debug once, analyze anywhere.*
