# PHP Xdebug Trace Analysis Guidelines

## Overview

This document describes how to generate and analyze Xdebug trace data for PHP debugging using runtime analysis. During development, Forward Trace (conditional breakpoints with step recording) should be the primary debugging method, eliminating the need for var_dump() and code modifications.

## AI Assistant Usage (MCP Slash Commands)

**For AI Assistants using Claude Code or MCP protocol**, Forward Trace debugging is available through AI-friendly slash commands that provide the same functionality with improved usability.

### MCP Slash Commands vs CLI

**AI-Recommended: MCP Slash Commands**
```bash
# Variable State Inspection
/x-debug "file.php" "file.php:42" "" "Variable inspection"

# Performance Analysis
/x-profile script="slow-script.php" context="Bottleneck identification"

# Execution Tracing
/x-trace script="complex-flow.php" context="Logic flow analysis"

# Code Coverage
/x-coverage script="vendor/bin/phpunit UserTest.php" context="Test coverage check"
```

**CLI Alternative (same functionality)**
```bash
# Variable State Inspection  
./bin/xdebug-debug --break='file.php:42' --context="Variable inspection" --json -- php file.php

# Performance Analysis
./bin/xdebug-profile --context="Bottleneck identification" --json -- php slow-script.php

# Execution Tracing
./bin/xdebug-trace --context="Logic flow analysis" --json -- php complex-flow.php

# Code Coverage
./bin/xdebug-coverage -- php vendor/bin/phpunit UserTest.php
```

### AI-Specific Features

#### Context Memory ('last' functionality)
```bash
# First execution
/x-debug "problematic.php" "problematic.php:42" "" "Bug investigation"

# Repeat with same settings (using updated context)
/x-debug "problematic.php" "problematic.php:42" "" "Updated investigation"
```

#### Tab Completion
```bash
/x[TAB]              # Shows: x-debug, x-trace, x-profile, x-coverage
```

#### Structured Output for AI Analysis
All MCP commands return schema-validated JSON optimized for AI consumption, including:
- Execution metadata
- Variable states
- Performance metrics
- Error information
- Context preservation

### When to Use Each Interface

**Use MCP Slash Commands when:**
- Working with AI assistants (Claude Code, etc.)
- Need context memory and 'last' functionality
- Prefer structured parameter input
- Want integrated Claude Code workflow

**Use CLI directly when:**
- Writing shell scripts
- CI/CD integration
- Custom automation
- Terminal-only environments

## Forward Trace for Development

During development, Forward Trace should be the primary debugging method. It captures execution until problems occur, eliminating the need for var_dump() and code modifications.

### Development Workflow with Forward Trace

#### 1. Variable State Inspection (Replaces var_dump)
```bash
# Instead of: var_dump($user); die();

# MCP Slash Command (AI-Recommended)
/x-debug "script.php" "file.php:line" "" "Variable inspection"

# CLI Alternative
./bin/xdebug-debug --break='file.php:line' --steps=1 --json -- php script.php
# Shows exact variable state at that line without code changes
```

#### 2. Loop Debugging (Replaces multiple var_dumps)
```bash
# Instead of: foreach($items as $item) { var_dump($item); }
./vendor/bin/xdebug-debug --break='loop.php:45' --steps=100 --json -- php script.php
# Records each iteration's variable state
```

#### 3. Conditional Problem Detection
```bash
# Instead of: if ($total < 0) { var_dump($cart); die("negative!"); }
./vendor/bin/xdebug-debug --break='cart.php:89:$total<0' --exit-on-break -- php script.php
# Captures exact state when condition occurs
```

#### 4. Intermittent Bug Capture
```bash
# For bugs that happen sometimes
./vendor/bin/xdebug-debug --break='api.php:*:$response==null' --exit-on-break -- php script.php
# Runs until the bug occurs, then captures complete trace
```

### Why Forward Trace During Development

| Traditional Debugging | Forward Trace |
|----------------------|---------------|
| Add var_dump(), run, remove | Set breakpoint, run, analyze |
| Modify code repeatedly | Never modify code |
| Guess where to look | Capture when problem occurs |
| Lose debug code in commits | Nothing to commit |
| See one variable at a time | See all variables in scope |

## Trace Generation Methods

### Basic Trace Generation

```bash
# Using xdebug-mcp tools
./vendor/bin/xdebug-trace -- php target.php
./vendor/bin/xdebug-profile -- php target.php

# Using native Xdebug
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace target.php
XDEBUG_TRIGGER=PROFILE php -dxdebug.mode=profile target.php
```

## Performance Profiling

### Profile Generation

```bash
# Generate cachegrind format profile
./vendor/bin/xdebug-profile -- php app.php
# Output: /tmp/cachegrind.out.*

# Profile specific test
./vendor/bin/xdebug-phpunit --profile tests/SlowTest.php
```

### Profile Analysis

#### Reading Cachegrind Files
```bash
# Find profile output
ls -la /tmp/cachegrind.out.*

# Basic analysis with grep
grep "fn=" /tmp/cachegrind.out.* | sort | uniq -c | sort -rn

# Extract function costs
awk '/^fn=/{fn=$0} /^[0-9]/{print fn, $1}' /tmp/cachegrind.out.* | sort -k2 -rn | head -20
```

#### Key Metrics in Profile Data
```
fl=/path/to/file.php
fn=functionName
15 100          # line 15, cost 100
cfn=calledFunc  # called function
calls=10        # called 10 times
```

### Common Profiling Patterns

#### Finding Bottlenecks
```bash
# Functions consuming most time
grep "^fn=" /tmp/cachegrind.out.* | sort | uniq -c | sort -rn | head -10

# Most expensive single lines
awk '/^[0-9]+ [0-9]+/{print $2, FILENAME":"NR}' /tmp/cachegrind.out.* | sort -rn | head -20
```

#### Call Frequency Analysis
```bash
# Functions called most frequently
grep "calls=" /tmp/cachegrind.out.* | sed 's/calls=//' | awk '{sum+=$1} END{print sum}'
```

#### Memory Profiling
```bash
# When using memory profiling
XDEBUG_TRIGGER=PROFILE php -dxdebug.mode=profile -dxdebug.profiler_output_name=cachegrind.out.%p.mem app.php
```

### Profiling Strategies for Development

#### Comparative Profiling
```bash
# Before optimization
./vendor/bin/xdebug-profile -- php app.php
mv /tmp/cachegrind.out.* /tmp/before.out

# After optimization
./vendor/bin/xdebug-profile -- php app.php
mv /tmp/cachegrind.out.* /tmp/after.out

# Compare results
diff <(grep "^fn=" /tmp/before.out | sort) <(grep "^fn=" /tmp/after.out | sort)
```

#### Targeted Profiling
```bash
# Profile only specific requests
if [ "$DEBUG_PROFILE" = "1" ]; then
  ./vendor/bin/xdebug-profile -- php api.php
else
  php api.php
fi
```

### Conditional Breakpoints

Set breakpoints that trigger on specific conditions:

```bash
# Break when variable equals specific value
./vendor/bin/xdebug-debug --break='file.php:line:condition' -- php script.php

# Examples
./vendor/bin/xdebug-debug --break='User.php:85:$id==0' -- php app.php
./vendor/bin/xdebug-debug --break='Auth.php:42:$token==null' -- php app.php
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' -- php app.php

# Exit immediately when condition is met
./vendor/bin/xdebug-debug --break='file.php:line:condition' --exit-on-break -- php script.php
```

### Strategic Breakpoint Placement

Place multiple breakpoints at critical execution points to capture complete application flow:

#### MVC Application Breakpoints
```bash
# Capture entire request lifecycle
./vendor/bin/xdebug-debug \
  --break='Controller.php:10,Model.php:50,View.php:5' \
  --steps=5 \
  --json -- php index.php

# Result: Variable state at controller entry, model processing, and view rendering
```

#### Before/After Critical Operations
```bash
# Database transaction monitoring
./vendor/bin/xdebug-debug \
  --break='DB.php:100,DB.php:120,DB.php:140' \
  --json -- php app.php
# Breakpoints: beginTransaction(), query(), commit()

# Template rendering analysis
./vendor/bin/xdebug-debug \
  --break='Template.php:45,Template.php:89' \
  --steps=10 \
  --json -- php render.php
# Breakpoints: Before compile(), after render()
```

#### API Request Processing
```bash
# Full API pipeline debugging
./vendor/bin/xdebug-debug \
  --break='auth.php:10,validate.php:30,process.php:50,response.php:70' \
  --json -- php api.php
# Captures: Authentication, validation, processing, response generation
```

#### Loop Boundary Debugging
```bash
# Monitor loop entry, iteration, and exit
./vendor/bin/xdebug-debug \
  --break='processor.php:40,processor.php:45,processor.php:60' \
  --steps=3 \
  --json -- php batch.php
# Lines: Before loop, inside loop body, after loop
```

#### State Transition Points
```bash
# Track state machine changes
./vendor/bin/xdebug-debug \
  --break='StateMachine.php:*:$state=="init",StateMachine.php:*:$state=="processing",StateMachine.php:*:$state=="complete"' \
  --exit-on-break -- php workflow.php
# Captures first transition to each state
```

### Common Development Scenarios

#### Debugging Function Returns
```bash
# See what a function actually returns
./vendor/bin/xdebug-debug --break='utils.php:34' --steps=2 --json -- php app.php
# Step 1: Before return statement
# Step 2: After return with actual value
```

#### Tracking Variable Mutations
```bash
# Find where variable changes unexpectedly
./vendor/bin/xdebug-debug --break='process.php:*:$state!="initial"' --exit-on-break -- php app.php
# Stops exactly when $state changes from "initial"
```

#### API Response Debugging
```bash
# Capture failed API calls
./vendor/bin/xdebug-debug --break='api.php:*:$httpCode!=200' --exit-on-break -- php app.php
# Complete trace when non-200 response occurs
```

#### Database Query Analysis
```bash
# Catch slow queries during development
./vendor/bin/xdebug-debug --break='db.php:*:$queryTime>0.5' --exit-on-break -- php app.php
# Identifies queries taking over 500ms
```

#### Memory Usage During Development
```bash
# Monitor memory growth in data processing
./vendor/bin/xdebug-debug --break='import.php:150' --steps=50 --json -- php import.php
# Track memory usage across 50 steps
```

### Step Tracing

Record variable evolution step-by-step from a breakpoint:

```bash
# Record next N steps from breakpoint
./vendor/bin/xdebug-debug --break='file.php:line' --steps=N --json -- php script.php

# Example: Record 100 steps of execution
./vendor/bin/xdebug-debug --break='loop.php:17' --steps=100 --json -- php app.php

# Combine with conditions: Record until condition OR steps limit
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' --steps=50 --json -- php app.php
```

### Multiple Breakpoint Conditions

```bash
# Stop at first matching condition
./vendor/bin/xdebug-debug --break='Auth.php:42:$token==null,User.php:85:$id==0' --exit-on-break -- php app.php
```

## JSON Output Format

When using `--json` flag, output follows schema `https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json`:

```json
{
  "$schema": "https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json",
  "breaks": [
    {
      "step": 1,
      "location": {"file": "file.php", "line": 17},
      "variables": {
        "$total": "int: 0",
        "$items": "array: []"
      }
    }
  ],
  "trace": {
    "file": "/tmp/trace.1034012359.xt",
    "lines": 11,
    "size": 0.3
  }
}
```

## Trace File Format (.xt)

### Structure

```
Level   FuncID  Time    Memory  Function  UserDef  File:Line  Params/Return
0       1       0.001   384000  {main}    1        test.php:1  
1       2       0.002   384100  calc()    1        test.php:15 $n = 10
1       2       0.003   384200                              R 100
```

### Key Elements

| Element | Description |
|---------|-------------|
| Level | Call stack depth |
| Time | Seconds since start |
| Memory | Bytes used |
| Function | Function name |
| UserDef | 1=user code, 0=built-in |
| Params/Return | Arguments or return (R prefix) |

## Analysis Strategies

### File Size-Based Approach

| File Size | Analysis Method |
|-----------|-----------------|
| < 1KB | Read entire file |
| 1-100KB | grep for patterns, then context |
| > 100KB | tail for final execution |

### Pattern Detection Commands

#### Recursion Detection
```bash
# Count function repetitions
grep -c "functionName" trace.xt

# Check nesting depth
awk '{print gsub(/  /, "", $0), $0}' trace.xt | sort -rn | head
```

#### N+1 Query Detection
```bash
# Find repeated database queries
grep "PDO->query\|mysqli_query" trace.xt | sort | uniq -c | sort -rn
```

#### Memory Leak Detection
```bash
# Track memory growth
awk '{print $2}' trace.xt | grep -E '^[0-9]+$' | awk 'NR>1{print $1-p} {p=$1}' | grep -v '^-'

# Find memory spikes
awk '{if($2>100000000) print NR, $0}' trace.xt
```

#### Performance Analysis
```bash
# Find slow operations (entry/exit pairs)
awk '/->/{start[$3]=substr($1,1,10)} /<-/{if($3 in start) print substr($1,1,10)-start[$3], $3}' trace.xt | sort -rn | head
```

## Common Debugging Scenarios

### Null Value Debugging
```bash
# Catch when variable becomes null
./vendor/bin/xdebug-debug --break='User.php:85:$user==null' --exit-on-break -- php app.php
```
**Analysis Focus:** Trace backwards from breakpoint to find last assignment

### Variable Evolution Analysis
```bash
# Watch array growth in loop
./vendor/bin/xdebug-debug --break='DataProcessor.php:45' --steps=100 --json -- php app.php
```
**Analysis Focus:** Check variable size at each step for growth patterns

### Performance Bottleneck Detection
```bash
# Catch slow operations
./vendor/bin/xdebug-debug --break='DB.php:45:microtime(true)-$start>1.0' --exit-on-break -- php app.php
```
**Analysis Focus:** Examine function calls leading to slow execution

### Memory Exhaustion Prevention
```bash
# Monitor memory usage
./vendor/bin/xdebug-debug --break='app.php:*:memory_get_usage()>50000000' --exit-on-break -- php app.php
```
**Analysis Focus:** Identify memory accumulation points

## Forward Trace Analysis

Forward Trace captures execution from start until a problem occurs, unlike traditional backtraces that work backwards from errors.

### Key Concepts

1. **Conditional Recording**: Record until specific condition is met
2. **Step Recording**: Record N steps from a breakpoint
3. **Variable Evolution**: Track how variables change over time
4. **Schema-Validated Output**: Portable JSON for cross-tool analysis

### Practical Examples

```bash
# Record until error condition
./vendor/bin/xdebug-debug --break='checkout.php:89:$total<0' --exit-on-break -- php app.php

# Record 50 steps OR until condition
./vendor/bin/xdebug-debug --break='Cart.php:89:$total<0' --steps=50 --json -- php app.php

# Generate portable debug session
./vendor/bin/xdebug-debug --break='bug.php:42' --steps=200 --json > debug-session.json
```

## Emergency Analysis Commands

### Last Error
```bash
tail -1000 trace.xt | grep -E "Fatal|Error|Exception" | tail -1
```

### Execution Time Analysis
```bash
# Find operations over 1 second
grep -E "^[0-9]+\.[0-9]+" trace.xt | awk '{print $1}' | \
    awk 'NR>1{if($1-p>1) print NR, $1-p; p=$1}'
```

### Function Call Frequency
```bash
# Most called functions
grep -o "-> [^(]*" trace.xt | sort | uniq -c | sort -rn | head -20
```

### Total Execution Metrics
```bash
# Total time
tail -1 trace.xt | awk '{print $1}'

# Maximum memory
awk '{if($2>max) max=$2} END{print max/1048576 " MB"}' trace.xt

# Total function calls
grep -c "^[[:space:]]*->" trace.xt
```

## Performance Thresholds

| Metric | Warning | Critical |
|--------|---------|----------|
| Stack depth | > 50 | > 100 |
| Memory usage | > 128MB | > 256MB |
| Function duration | > 2s | > 5s |
| DB queries/request | > 50 | > 100 |
| Function calls | > 5000 | > 10000 |

## Reading Traces for Specific Issues

### Issue: Infinite Recursion
**Pattern:** Same function repeating with increasing level
```
10  -> calculate()
11    -> calculate()
12      -> calculate()
```
**Command:** `grep -c "calculate" trace.xt`

### Issue: N+1 Queries
**Pattern:** Query inside loop
```
-> foreach
  -> PDO->query() user_id=1
  -> PDO->query() user_id=2
```
**Command:** `grep "PDO->query" trace.xt | wc -l`

### Issue: Memory Leak
**Pattern:** Memory increases without decrease
```
2097152  -> processData()
10485760   -> loadFile()    # +8MB
20971520   -> parseData()   # +10MB
```
**Command:** `awk '{print $2}' trace.xt | tail -20`

## Best Practices for Trace Analysis

1. **Start with conditional breakpoints** to capture specific problem states
2. **Use step recording** to understand variable evolution
3. **Generate JSON output** for complex analysis requiring AI assistance
4. **Check file size first** to determine appropriate analysis strategy
5. **Focus on patterns** rather than individual lines
6. **Use exit-on-break** to capture first occurrence of problems
7. **Combine multiple conditions** to narrow down complex issues

## Environment Setup

### Prerequisites
```bash
# Xdebug should be commented out in php.ini for optimal performance
;zend_extension=xdebug

# Xdebug extension should be available for dynamic loading
php -dzend_extension=xdebug.so -m | grep xdebug
```

### Recommended Configuration
```ini
# php.ini - Only enable specific modes when needed
;zend_extension=xdebug
xdebug.mode=off                    # Default: disabled for performance
xdebug.client_host=127.0.0.1
xdebug.client_port=9004            # Different from IDE port (9003)
xdebug.output_dir=/tmp             # Or your preferred directory
xdebug.use_compression=0           # Disable for faster processing
```

### Testing Setup
```bash
# Install dependencies
composer install

# Check environment
./bin/check-xdebug-status

# Test MCP tools functionality
./bin/test-all.sh
```

## Troubleshooting

### Common Issues

#### 1. Xdebug Extension Not Available
```bash
php -m | grep xdebug
# If empty, install Xdebug
pecl install xdebug

# Or on macOS with Homebrew
brew install php-xdebug
```

#### 2. Trace Files Not Generated
```bash
# Check Xdebug output directory permissions
ls -la /tmp/trace*
chmod 755 /tmp

# Verify Xdebug configuration
php -dzend_extension=xdebug -dxdebug.mode=trace -i | grep xdebug
```

#### 3. MCP Server Not Responding
```bash
# Test basic connectivity
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php -dzend_extension=xdebug.so bin/xdebug-mcp

# Should return list of available tools
```

#### 4. Performance Issues
```bash
# Only load Xdebug when needed
php script.php                                    # Normal execution
php -dzend_extension=xdebug.so script.php        # With Xdebug

# Use specific modes only
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace script.php
```

#### 5. Memory Exhaustion During Tracing
```bash
# Increase memory limit for large applications
php -dmemory_limit=512M -dzend_extension=xdebug.so script.php

# Use conditional breakpoints to limit trace scope
./bin/xdebug-debug --break='file.php:line:condition' --exit-on-break -- php script.php
```

### Debugging the Debugger

#### Xdebug Log Analysis
```bash
# Enable Xdebug logging
php -dxdebug.log=/tmp/xdebug.log -dxdebug.log_level=7 script.php

# Check log for issues
tail -f /tmp/xdebug.log
```

#### Connection Issues
```bash
# Check if port is available
lsof -i :9004

# Test socket connectivity
telnet 127.0.0.1 9004
```

### Environment Validation

#### Quick Health Check
```bash
# 1. PHP version and extensions
php -v && php -m | grep xdebug

# 2. MCP server functionality
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php bin/xdebug-mcp | head -5

# 3. Trace generation test
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace -c /dev/null bin/xdebug-mcp --help

# 4. File permissions
ls -la /tmp/trace* /tmp/cachegrind* 2>/dev/null || echo "No trace files found"
```
