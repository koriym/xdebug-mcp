# AI Debugging Principles for PHP Projects

## Core Philosophy: Non-Invasive Trace-Based Debugging

This template provides AI debugging guidelines for PHP projects. Use Xdebug trace analysis instead of modifying source code with debug statements.

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

### Basic Xdebug Trace Commands

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
Let's trace the execution to see variable states:
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace user_auth.php

This shows actual $user values at each step without code changes.
```

## Implementation Guidelines

**Mandatory Rules:**
1. **No Code Changes**: Never suggest adding debug statements
2. **Trace First**: Always propose trace execution first
3. **Runtime Data Priority**: Use actual execution data over assumptions
4. **Non-Invasive**: Maintain code integrity during debugging

## Benefits of Trace-Based Debugging

- **Non-invasive**: No source code modification
- **Comprehensive**: Complete execution flow and states
- **Professional**: No debug code left accidentally
- **Accurate**: Actual runtime data, not guesswork

---

## Usage Instructions

**To add to existing project:**
1. Copy this file to project root as `CLAUDE_DEBUG_PRINCIPLES.md`
2. Reference in existing `CLAUDE.md` or rename to `CLAUDE.md`
3. Ensure AI reads these principles before debugging

**To integrate with existing CLAUDE.md:**
```bash
cat CLAUDE_DEBUG_PRINCIPLES.md >> CLAUDE.md
```