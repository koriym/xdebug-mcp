# AI Debugging Principles for PHP Projects

## Core Philosophy: Non-Invasive Trace-Based Debugging

This template configures AI to use runtime analysis instead of static code analysis for PHP debugging.

Traditional AI debugging approach:
- Reading source code and making assumptions
- Analyzing error messages without context
- Guessing variable values
- Recommending debug print statements

Runtime-based approach:
- See actual execution flow - which functions run and when
- Inspect real variable values - observe actual data at every step
- Measure true performance - identify real bottlenecks
- Track memory usage - spot actual leaks and inefficiencies
- Analyze call patterns - understand complex system interactions

## Debugging Approach Comparison

**Static analysis approach:**
- "This code might do X" or "The error suggests Y"
- "Try adding var_dump to see what $user contains"
- "This function might be slow"

**Runtime analysis approach:**
- "I see this code actually does X with values A,B,C taking N microseconds"
- "The trace shows $user = ['id'=>123,'name'=>'John'] at line 45, then becomes null at line 67"
- "fibonacci() consumed 3,772μs (27.6% of total) with 24 recursive calls"

## ❌ Prohibited Debugging Methods

**Never suggest these approaches:**
- Adding `var_dump($variable)` statements
- Adding `print_r($array)` statements  
- Adding `echo "Debug: " . $value` statements
- Inserting `die()` or `exit()` calls
- Adding `error_log()` for value inspection
- Any code modification for debugging purposes

## ✅ Required Method: Xdebug Trace Execution

**For ALL PHP debugging, first suggest trace execution:**

### Xdebug Analysis Tools

**If project has xdebug-mcp tools (check for ./bin/xdebug-* files), use these instead:**

```bash
# Performance profiling (preferred method)
./bin/xdebug-profile target_file.php

# Code coverage analysis  
./bin/xdebug-coverage target_file.php

# Execution tracing
./bin/xdebug-trace target_file.php

# PHPUnit selective tracing/profiling
./bin/xdebug-phpunit tests/UserTest.php::testLogin
./bin/xdebug-phpunit --profile tests/UserTest.php
./bin/xdebug-phpunit --filter=testUserAuth

# MCP server for advanced debugging
./bin/xdebug-mcp
```

**Automatic Tool Selection:**
- Performance issues → `./bin/xdebug-profile`
- Test coverage → `./bin/xdebug-coverage`  
- Execution flow → `./bin/xdebug-trace`
- **Conditional debugging** → `./bin/xdebug-debug --break=file:line:condition`
- **Test debugging** → `./bin/xdebug-phpunit`
- General analysis → `./bin/xdebug-profile` (default)

**PHPUnit Test Debugging Priority:**
When debugging tests or test-related issues, ALWAYS use `./bin/xdebug-phpunit` first:

```bash
# Trace specific failing test
./bin/xdebug-phpunit tests/UserTest.php::testUserLogin

# Profile slow test
./bin/xdebug-phpunit --profile tests/UserTest.php::testSlowOperation

# Trace tests matching pattern
./bin/xdebug-phpunit --filter=testUserAuth
```

**Requirements for xdebug-phpunit:**
Projects using `./bin/xdebug-phpunit` need this in their `phpunit.xml`:
```xml
<extensions>
    <bootstrap class="Koriym\XdebugMcp\TraceExtension"/>
</extensions>
```

**Output Analysis:**
- Trace mode: Check `/tmp/trace_*.xt` files for execution flow
- Profile mode: Check `/tmp/cachegrind.out.*` files for performance data

**Fallback to raw Xdebug (if no tools available):**

```bash
# Simple trace execution
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace target_file.php

# Comprehensive trace with options
php -dzend_extension=xdebug \
    -dxdebug.mode=trace \
    -dxdebug.start_with_request=trigger \
    -dxdebug.trigger_value=TRACE \
    -dxdebug.trace_format=1 \
    -dxdebug.use_compression=0 \
    -dxdebug.output_dir=/tmp \
    target_file.php

# Check trace output
ls -la /tmp/*trace*.xt
cat /tmp/trace_filename.xt
```

## ✅ Advanced: Conditional Debugging with AI Integration

**For targeting specific problem conditions:**

### Interactive Debugging with AI Analysis
```bash
# Stop execution only when specific conditions occur
./bin/xdebug-debug --break=User.php:85:$id==0 -- php register.php
./bin/xdebug-debug --break=Auth.php:42:$token==null -- php login.php

# In interactive session, use 'claude' command for immediate AI analysis:
(Xdebug) claude
🤖 Analyzing execution trace with Claude...
📊 Claude Analysis Result:
   ## Root Cause Analysis
   - Call chain leading to current breakpoint
   - Variable state analysis and anomalies  
   - Performance insights from trace data
   ## Suggested Actions
   - Specific code fixes based on runtime data
   - Next debugging steps
```

### Conditional Debugging Workflow
**1. Target Problem Conditions**
```bash
# Multiple conditions - stops at first match
./bin/xdebug-debug --break=User.php:85:$id==0,Auth.php:20:empty($token) -- php app.php
```

**2. AI-Assisted Analysis**  
- Use `claude` command during debugging session
- Get immediate analysis of execution trace up to breakpoint
- Receive specific recommendations based on actual runtime data

**3. Benefits Over Traditional Debugging**
- ✅ Skip normal execution paths, focus on problems
- 📊 Complete execution history leading to condition
- 🤖 AI analysis of real runtime data, not assumptions
- 🎯 Efficient debugging - no manual trace reading

### When to Use Conditional Debugging
- **Bug Investigation**: `--break=calculateTotal.php:45:$result<0`
- **Null Pointer Issues**: `--break=User.php:20:$user==null`  
- **Authentication Problems**: `--break=Auth.php:15:empty($token)`
- **Data Validation**: `--break=Validator.php:30:count($errors)>0`

### Error-Specific Debugging Approaches

**1. Undefined Variable Errors**
```bash
# Trace execution to track variable lifecycle
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace problematic_file.php
# Analyze trace file to see where variable should be initialized
```

**2. Unexpected Values**
```bash
# Instead of adding var_dump, use trace execution
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace script_with_wrong_values.php
# Trace shows actual parameter values passed to each function
```

**3. Execution Flow Issues**
```bash
# Track function call order and parameters
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace complex_workflow.php
# Trace reveals actual execution path vs expected path
```

## Trace File Analysis

**Trace file structure (.xt format):**
```
Level | FuncID | Time    | Memory  | Function    | File:Line  | Parameters
0     | 1      | 0.001   | 384000  | {main}      | test.php:1 | 
1     | 2      | 0.002   | 384100  | calculate() | test.php:15| $n = 10
```

**Analysis focus points:**
1. **Level**: Function call hierarchy
2. **Memory**: Memory usage and leaks
3. **Parameters**: Actual argument values (eliminates var_dump need)
4. **Time**: Performance bottlenecks
5. **File:Line**: Exact execution location

## Response Examples

**❌ Wrong Response (Prohibited):**
```
Add debug code to see the variable:
var_dump($user);  // Don't suggest this
```

**✅ Correct Response (Required):**
```
Let's analyze the execution to see variable states:
./bin/xdebug-profile user_auth.php

# Or for detailed execution flow:
./bin/xdebug-trace user_auth.php

This shows actual $user values at each step without code changes.
```

**✅ Alternative (if no xdebug-mcp tools):**
```
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace user_auth.php
```

## Implementation Guidelines

**Mandatory Rules:**
1. **No Code Changes**: Never suggest adding debug statements
2. **Trace First**: Always propose trace execution first
3. **Runtime Data Priority**: Use actual execution data over assumptions
4. **Non-Invasive**: Maintain code integrity during debugging

## AI Superpowers Unlocked by Runtime Data

### 🧠 Enhanced Intelligence
- **Precise Diagnosis**: Know exactly what happened, not what should have happened
- **Data-Driven Insights**: Make recommendations based on actual execution patterns
- **Context-Aware Solutions**: Understand the full execution context of any issue

### 🚀 Revolutionary Capabilities
- **Performance Oracle**: Identify real bottlenecks with microsecond precision
- **Variable Detective**: Track any variable's journey through the entire codebase
- **Execution Archaeologist**: Reconstruct complex application flows
- **Memory Forensics**: Detect memory issues before they become critical

### 💡 Practical Benefits
- **Non-invasive**: No source code modification required
- **Professional**: No debug code left accidentally in production
- **Comprehensive**: See the complete picture, not fragments
- **Actionable**: Provide specific, targeted solutions

### 🎯 AI Development Revolution
This transforms AI from a **code reader** into a **runtime analyst** - capable of understanding applications as they actually behave, not as they're written to behave.

---

## Deployment Instructions

### System-Wide Installation (Recommended for all PHP development)

**For personal use across ALL PHP projects:**
```bash
# Copy to user memory location
cp CLAUDE_DEBUG_PRINCIPLES.md ~/.claude/CLAUDE.md
```

This makes Claude automatically use runtime-based debugging for every PHP project you work on.

### Project-Specific Installation

**For team/project use:**
```bash
# Add to project memory
cp CLAUDE_DEBUG_PRINCIPLES.md ./CLAUDE.md
```

**Or integrate with existing project CLAUDE.md (recommended):**
```bash
# Keep files separate and import (recommended)
cp CLAUDE_DEBUG_PRINCIPLES.md ./
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ./CLAUDE.md
```

**Alternative (less recommended):**
```bash
# Direct append (harder to maintain)
cat CLAUDE_DEBUG_PRINCIPLES.md >> ./CLAUDE.md
```

### Memory Priority System

Claude Code loads memory in this order:
1. **User Memory** (`~/.claude/CLAUDE.md`) - Your personal PHP debugging preferences
2. **Project Memory** (`./CLAUDE.md`) - Team-shared project instructions
3. **Inheritance** - Project memory can override or extend user memory

### Recommended Setup Strategy

**Option 1: Full Personal Setup**
- Put complete debugging principles in `~/.claude/CLAUDE.md`
- Benefits: Works across all PHP projects automatically
- Use case: Solo developer or personal preference

**Option 2: Team + Personal Hybrid (Recommended)**
- Basic principles in `~/.claude/CLAUDE.md`
- Project-specific Xdebug tools in `./CLAUDE.md`
- Use `@CLAUDE_DEBUG_PRINCIPLES.md` import for modular approach
- Benefits: Personal workflow + team consistency + easy updates

**Option 3: Project Only**  
- Everything in project `./CLAUDE.md`
- Benefits: Team consistency, version controlled
- Use case: Team development, shared workflows

### Why Use @import Syntax?

**Benefits of modular approach:**
- ✅ **Easy updates**: Update `CLAUDE_DEBUG_PRINCIPLES.md` without touching main `CLAUDE.md`
- ✅ **Version control friendly**: Track changes to debugging principles separately
- ✅ **Reusable**: Same principles file can be imported across multiple projects
- ✅ **Team collaboration**: Developers can maintain personal `CLAUDE.md` while sharing debugging standards
- ✅ **Conflict-free**: No merge conflicts in main memory file

**Example project structure:**
```
project/
├── CLAUDE.md                    # Project-specific memory
├── CLAUDE_DEBUG_PRINCIPLES.md   # Debugging principles (this file)
└── src/
```

**Contents of CLAUDE.md:**
```markdown
# My Project Instructions
@CLAUDE_DEBUG_PRINCIPLES.md

## Project-specific tools
- Use ./bin/xdebug-profile for performance analysis
- Use ./bin/xdebug-coverage for test coverage
```

### Verification

Test that Claude recognizes the debugging principles:
```bash
# Claude should automatically suggest runtime analysis tools
# when you ask: "Analyze this PHP file"
```