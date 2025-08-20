# PHP Xdebug MCP Server

> Enable AI to develop using Xdebug like we do

<img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

MCP server enabling AI control of PHP Xdebug debugging, profiling, and coverage analysis.

## Features

- **42 Xdebug Tools**: Complete debugging, profiling, and coverage automation
- **Trace-based Debugging**: AI analyzes runtime execution data (no var_dump needed)
- **IDE Compatible**: Port 9004 avoids conflicts with PhpStorm/VS Code (9003)
- **Command Line Tools**: 5 standalone debugging utilities

## Tool Categories

- **Debugging**: Session management, breakpoints, step execution, variable inspection
- **Profiling**: Performance analysis, function timing, Cachegrind output
- **Coverage**: Line/function coverage, HTML/XML reports, PHPUnit integration
- **Extended**: Memory stats, error collection, tracing, advanced breakpoints

## Quick Start

```bash
# Install
composer install

# Start server
./bin/xdebug-server

# Test tools
./bin/xdebug-trace test/debug_test.php
./bin/xdebug-profile test/debug_test.php  
./bin/xdebug-coverage test/debug_test.php

# Claude Desktop integration
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp"
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

## Usage

```bash
# Verify
claude mcp list

# Test tools
echo '{"method":"tools/list"}' | php bin/xdebug-mcp

# Run tests
vendor/bin/phpunit
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

## Usage Examples

Ask Claude in natural language:

**Debugging:**
- "Start debugging my PHP script"
- "Set a breakpoint at line 25 in user.php"
- "Show me the current stack trace"
- "What's the value of $user_data variable?"
- "Step over to the next line"

**Performance Analysis:**  
- "Profile this PHP script's performance"
- "Show me memory usage statistics"
- "Which functions are taking the most time?"

**Code Coverage:**
- "Which parts of my code aren't covered by tests?"
- "Show me the uncovered lines in src/User.php"
- "Generate HTML coverage report to see what needs testing"
- "What's the overall test coverage percentage?"

## Troubleshooting

- Check `claude mcp list` 
- Verify Xdebug installation: `php -m | grep xdebug`
- Port conflicts: xdebug-mcp uses 9004, IDEs use 9003

## Links

- [Xdebug Docs](https://xdebug.org/docs/)
- [MCP Specification](https://modelcontextprotocol.io/)
- [Claude Desktop MCP](https://docs.anthropic.com/claude/docs/mcp)
