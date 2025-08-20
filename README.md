# PHP Xdebug MCP Server

> Enable AI to develop using Xdebug like we do

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

## Quick Start

```bash
composer install
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp"
./bin/xdebug-profile test/debug_test.php
```

## Setup

```bash
composer install
```

**Recommended: Use bin/xdebug-* commands**
```bash
# Best approach - tools handle Xdebug automatically
./bin/xdebug-trace script.php
./bin/xdebug-profile script.php
./bin/xdebug-coverage script.php
```

**Manual approach (equivalent to above)**
```bash
# Same as bin commands but manual
php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_port=9004 script.php
```

**php.ini: Comment out Xdebug (better performance)**
```ini
# Comment out in php.ini for better performance
;zend_extension=xdebug
# Other Xdebug settings are handled automatically by bin/xdebug-* commands
```

### MCP Configuration

```bash
# Claude Desktop
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp"

# Verify
claude mcp list
```

## Command Line Tools

- `xdebug-server` - Start MCP server (port 9004)
- `xdebug-mcp` - Core MCP server 
- `xdebug-trace` - Generate execution traces
- `xdebug-profile` - Performance profiling  
- `xdebug-coverage` - Code coverage analysis
- `xdebug-phpunit` - PHPUnit with selective Xdebug analysis

### xdebug-phpunit Usage

Run PHPUnit tests with automatic Xdebug tracing or profiling:

```bash
# Trace specific test method (default mode)
./bin/xdebug-phpunit tests/UserTest.php::testLogin

# Profile entire test file
./bin/xdebug-phpunit --profile tests/UserTest.php

# Trace tests matching filter
./bin/xdebug-phpunit --filter=testUserAuth

# Profile slow tests
./bin/xdebug-phpunit --profile --filter=testSlow
```

**Output:**
- Trace mode: `/tmp/trace_*.xt` (execution traces)
- Profile mode: `/tmp/cachegrind.out.*` (performance data)

**Requirements:**
Add to your `phpunit.xml`:
```xml
<extensions>
    <bootstrap class="Koriym\XdebugMcp\TraceExtension"/>
</extensions>
```

## Usage Examples

### 1. Execution Tracing
```bash
claude --print "Run test/debug_test.php and analyze the execution patterns"
# AI automatically chooses ./bin/xdebug-trace and provides analysis:
# ✅ Trace complete: /tmp/xdebug_trace_20250821_044930.xt (64 lines)
# 📊 Analysis: O(2^n) Fibonacci inefficiency, stable memory usage, microsecond-level metrics
```

### 2. Performance Profiling
```bash
claude --print "Profile the performance of test/debug_test.php"
# AI automatically uses ./bin/xdebug-profile:
# ✅ Profile complete: /tmp/cachegrind.out.1755719364
# 📊 Size: 1.9K, Functions: 29, Calls: 28, identifies bottlenecks
```

### 3. Code Coverage Analysis
```bash
claude --print "Analyze code coverage for test/debug_test.php"
# AI automatically uses ./bin/xdebug-coverage:
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
# Debug PHPUnit tests (after adding TraceExtension to phpunit.xml)
./bin/xdebug-phpunit tests/Unit/McpServerTest.php::testConnect
```

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

## Tell AI to Use Runtime Analysis Instead of Guesswork

**Problem**: AI development traditionally relies on static code analysis and error messages. AI can only guess what might be happening in your PHP application.

**Solution**: These templates teach Claude to use actual execution data from Xdebug profiling and tracing instead of making assumptions.

**[Templates Directory](templates/README.md)** - Complete configuration guide

### System-Wide Configuration
```bash
# Teach Claude to use runtime analysis for ALL PHP projects
cp templates/CLAUDE_DEBUG_PRINCIPLES.md ~/.claude/CLAUDE.md
```

### Project-Specific Configuration
```bash
# Teach Claude to use runtime analysis for this project
cp templates/CLAUDE_DEBUG_PRINCIPLES.md ./
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ./CLAUDE.md
```

**Result**: Transform development from code+error guessing to runtime data analysis:

- **Before**: "This code might be slow" (AI guessing)
- **After**: "fibonacci() consumed 3,772μs (27.6% of total) with 24 recursive calls" (AI analyzing real data)

## Links

- [Templates & Deployment Guide](templates/README.md)
- [Xdebug Docs](https://xdebug.org/docs/)
- [MCP Specification](https://modelcontextprotocol.io/)
- [Claude Desktop MCP](https://docs.anthropic.com/claude/docs/mcp)
