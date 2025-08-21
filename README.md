# PHP Xdebug MCP Server

> Enable AI to use Xdebug for PHP debugging like we do

<img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

MCP server enabling AI control of PHP Xdebug debugging, profiling, and coverage analysis.

## Features

- **42 Xdebug Tools**: Complete debugging, profiling, and coverage automation
- **Trace-based Debugging**: AI analyzes runtime execution data (no var_dump needed)
- **IDE Compatible**: Port 9004 avoids conflicts with PhpStorm/VS Code (9003)
- **Command Line Tools**: 6 standalone debugging utilities

## Tool Categories

- **Debugging**: Session management, breakpoints, step execution, variable inspection
- **Profiling**: Performance analysis, function timing, Cachegrind output
- **Coverage**: Line/function coverage, HTML/XML reports, PHPUnit integration
- **Extended**: Memory stats, error collection, tracing, advanced breakpoints

## Installation

```bash
# Install as development dependency
composer require --dev koriym/xdebug-mcp:1.x-dev
```

## Setup

### MCP Configuration

```bash
# Claude Desktop
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# Verify
claude mcp list
```

### Xdebug Configuration (Recommended)

#### php.ini: Comment out Xdebug for optimal performance
```ini
# RECOMMENDED: Comment out in php.ini for better performance
;zend_extension=xdebug
# Other Xdebug settings are handled automatically by bin/xdebug-* commands
```

#### Why this is recommended
- Xdebug impacts performance when always enabled and is unnecessary for daily development
- The bin/xdebug-* commands load Xdebug only when needed for debugging/profiling
- Production environments should never have Xdebug permanently enabled

### AI Configuration (Recommended)

Note: The commands below target Claude Desktop. If you use a different AI client, adapt the MCP add/list commands and system prompt location accordingly.

#### Teach AI to use runtime analysis instead of guesswork

```bash
# Project-specific: Copy debugging principles to your project
cp vendor/koriym/xdebug-mcp/templates/CLAUDE_DEBUG_PRINCIPLES.md ./
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ./CLAUDE.md
```

**System-wide (optional):**
```bash
# Apply to ALL PHP projects
cp vendor/koriym/xdebug-mcp/templates/CLAUDE_DEBUG_PRINCIPLES.md ~/.claude/CLAUDE.md
```

**What this does:**
- Stops AI from using `var_dump()` or `echo` for debugging
- Teaches AI to use `./vendor/bin/xdebug-trace` instead
- Enables data-driven analysis from actual execution traces

## Quick Start

**1. Start MCP server:**
```bash
./vendor/bin/xdebug-server
# ✅ Server starts on port 9004, ready for AI commands
```

**2. Ask AI to debug with runtime data:**
```bash
# In another terminal - AI analyzes actual execution instead of guessing
claude --print "Trace test/debug_test.php and identify the performance bottleneck"
# ✅ AI automatically runs xdebug-trace and provides data-driven analysis
```

**3. Zero-config PHPUnit debugging (Game Changer):**
```bash
# AI-assisted test debugging with automatic Xdebug setup - NO phpunit.xml changes needed!
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin
# ✅ TraceExtension auto-injected, traces specific test method, works with ANY PHPUnit project
```

## Verification

**Test AI integration:**
```bash
# Performance profiling
claude --print "Profile test/debug_test.php and show the slowest functions"
# ✅ AI runs xdebug-profile and analyzes cachegrind output

# Coverage analysis  
claude --print "Analyze code coverage for test/debug_test.php"
# ✅ AI runs xdebug-coverage and reports untested code paths
```

**Manual verification (optional):**
```bash
# Direct tool usage
./vendor/bin/xdebug-trace test/debug_test.php
./vendor/bin/xdebug-profile test/debug_test.php  
./vendor/bin/xdebug-coverage test/debug_test.php
```

**Expected results:**
- Trace files showing exact function call sequences and variable values
- Performance data revealing O(2^n) fibonacci inefficiency 
- Coverage reports highlighting untested code paths
- AI providing data-driven analysis instead of static code guessing


## Usage

### Command Line Tools

- `xdebug-server` - Start MCP server (port 9004)
- `xdebug-mcp` - Core MCP server 
- `xdebug-trace` - Generate execution traces
- `xdebug-profile` - Performance profiling  
- `xdebug-coverage` - Code coverage analysis
- `xdebug-phpunit` - PHPUnit with selective Xdebug analysis

### Basic Commands

```bash
# Recommended: Use bin/xdebug-* commands
./vendor/bin/xdebug-trace script.php
./vendor/bin/xdebug-profile script.php
./vendor/bin/xdebug-coverage script.php
```

**Manual approach (step debugging example):**
```bash
# Step debugging example (manual). For traces/profiles/coverage, prefer ./vendor/bin/xdebug-*
# or set the appropriate xdebug.mode values and ini flags manually.
php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_port=9004 script.php
```

### AI-Powered Examples

### 1. Execution Tracing
```bash
claude --print "Run test/debug_test.php and analyze the execution patterns"
# AI automatically chooses ./vendor/bin/xdebug-trace and provides analysis:
# ✅ Trace complete: /tmp/xdebug_trace_20250821_044930.xt (64 lines)
# 📊 Analysis: O(2^n) Fibonacci inefficiency, stable memory usage, microsecond-level metrics
```

### 2. Performance Profiling
```bash
claude --print "Profile the performance of test/debug_test.php"
# AI automatically uses ./vendor/bin/xdebug-profile:
# ✅ Profile complete: /tmp/cachegrind.out.1755719364
# 📊 Size: 1.9K, Functions: 29, Calls: 28, identifies bottlenecks
```

### 3. Code Coverage Analysis
```bash
claude --print "Analyze code coverage for test/debug_test.php"
# AI automatically uses ./vendor/bin/xdebug-coverage:
# ✅ Coverage complete: HTML report generated
# 📊 Coverage: 85.2% lines, 92.1% functions, identifies untested code paths
```

### 4. Step Debugging
```bash
claude --print "Debug test/debug_test.php, break at line 15 and show variable values"
# AI sets breakpoint and provides debugging session:
# ✅ Breakpoint set at test/debug_test.php:15
# 📊 Variables at breakpoint:
# | Variable | Type   | Value                    |
# |----------|--------|--------------------------|
# | $n       | int    | 6                        |
# | $result  | int    | 8                        |
# | $user    | array  | ['name'=>'John','age'=>30] |
```

### 5. PHPUnit Testing
```bash
# Debug PHPUnit tests (zero configuration required)
./vendor/bin/xdebug-phpunit tests/Unit/McpServerTest.php::testConnect
```

### xdebug-phpunit Usage - The Ultimate PHPUnit Debugging Solution

**Zero-configuration** PHPUnit with automatic Xdebug tracing or profiling - **works with ANY existing PHPUnit project without modifying your phpunit.xml**:

```bash
# Trace specific test method (default mode)
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin

# Profile entire test file
./vendor/bin/xdebug-phpunit --profile tests/UserTest.php

# Trace tests matching filter
./vendor/bin/xdebug-phpunit --filter=testUserAuth

# Show effective configuration (transparency)
./vendor/bin/xdebug-phpunit --dry-run tests/UserTest.php

# Verbose logging for debugging
./vendor/bin/xdebug-phpunit --verbose tests/UserTest.php
```

**Auto-injection:** TraceExtension is automatically injected into a temporary phpunit.xml (no manual setup required)

**Output:**
- Trace mode: `/tmp/trace_*.xt` (execution traces)
- Profile mode: `/tmp/cachegrind.out.*` (performance data)

## 42 Available Tools

### Debug (11)
- **xdebug_connect**: Connect to Xdebug session
- **xdebug_disconnect**: Disconnect from Xdebug session
- **xdebug_set_breakpoint**: Set a breakpoint at specified file and line
- **xdebug_remove_breakpoint**: Remove a breakpoint by ID
- **xdebug_step_into**: Step into the next function call
- **xdebug_step_over**: Step over the current line
- **xdebug_step_out**: Step out of the current function
- **xdebug_continue**: Continue execution until next breakpoint
- **xdebug_get_stack**: Get current stack trace
- **xdebug_get_variables**: Get variables in current context
- **xdebug_eval**: Evaluate PHP expression in current context

### Profile (4)
- **xdebug_start_profiling**: Start profiling execution
- **xdebug_stop_profiling**: Stop profiling and return results
- **xdebug_get_profile_info**: Get current profiling information
- **xdebug_analyze_profile**: Analyze profiling data from file

### Coverage (6)
- **xdebug_start_coverage**: Start code coverage tracking
- **xdebug_stop_coverage**: Stop code coverage tracking
- **xdebug_get_coverage**: Get code coverage data
- **xdebug_analyze_coverage**: Analyze coverage data and generate report
- **xdebug_coverage_summary**: Get coverage summary statistics

### Extended (21)
- **xdebug_get_memory_usage**: Get current memory usage information
- **xdebug_get_peak_memory_usage**: Get peak memory usage information
- **xdebug_get_stack_depth**: Get current stack depth level
- **xdebug_get_time_index**: Get time index since script start
- **xdebug_info**: Get detailed Xdebug configuration and diagnostic information
- **xdebug_start_error_collection**: Start collecting PHP errors, notices, and warnings
- **xdebug_stop_error_collection**: Stop collecting errors and return collected data
- **xdebug_get_collected_errors**: Get currently collected error messages
- **xdebug_start_trace**: Start function call tracing
- **xdebug_stop_trace**: Stop function call tracing and return trace data
- **xdebug_get_tracefile_name**: Get the filename of the current trace file
- **xdebug_start_function_monitor**: Start monitoring specific functions
- **xdebug_stop_function_monitor**: Stop function monitoring and return monitored calls
- **xdebug_list_breakpoints**: List all active breakpoints
- **xdebug_set_exception_breakpoint**: Set a breakpoint on exception
- **xdebug_set_watch_breakpoint**: Set a watch/conditional breakpoint
- **xdebug_get_function_stack**: Get detailed function stack with arguments and variables
- **xdebug_print_function_stack**: Print formatted function stack trace
- **xdebug_call_info**: Get information about the calling context
- **xdebug_get_features**: Get all available Xdebug features and their values
- **xdebug_set_feature**: Set a specific Xdebug feature value
- **xdebug_get_feature**: Get a specific Xdebug feature value


## Troubleshooting

- Check `claude mcp list` 
- Verify Xdebug installation: `php -m | grep xdebug`
- Port conflicts: xdebug-mcp uses 9004, IDEs use 9003


## Links

- [Templates & Deployment Guide](templates/README.md)
- [Xdebug Docs](https://xdebug.org/docs/)
- [MCP Specification](https://modelcontextprotocol.io/)
- [Claude Desktop MCP](https://docs.anthropic.com/claude/docs/mcp)
