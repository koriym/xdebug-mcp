# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **universal PHP Xdebug MCP (Model Context Protocol) Server** that enables AI assistants to perform comprehensive PHP application analysis and debugging. The server acts as a bridge between AI clients and Xdebug, providing a standardized interface for:

### Core Purpose
- **AI-Driven PHP Analysis**: Enable AI assistants to understand PHP application behavior through runtime data rather than static code analysis
- **Universal Debugging Tool**: Work with any PHP application, framework, or codebase without modification
- **Performance Profiling**: Collect and analyze performance metrics to identify bottlenecks
- **Code Coverage Analysis**: Generate comprehensive test coverage reports and identify untested code paths
- **Runtime Debugging**: Step through code execution, inspect variables, and analyze call stacks

### Target Use Cases
1. **Performance Optimization**: AI can analyze profile data to suggest specific performance improvements
2. **Bug Investigation**: Trace execution flow to identify the root cause of issues
3. **Code Quality Assessment**: Use coverage data and execution traces to evaluate code quality
4. **Educational Analysis**: Help developers understand how their PHP applications actually execute
5. **Legacy Code Understanding**: Analyze complex or undocumented PHP applications through runtime behavior

This is a **general-purpose tool** designed to work with any PHP application, not limited to specific frameworks or use cases. The MCP protocol ensures consistent AI interaction regardless of the underlying PHP codebase being analyzed.

## Common Commands

### Development Setup
```bash
composer install               # Install dependencies
```

### Testing
```bash
# PHPUnit Tests (Recommended)
vendor/bin/phpunit                       # Run all PHPUnit tests
vendor/bin/phpunit tests/Unit            # Run only unit tests
vendor/bin/phpunit tests/Integration     # Run only integration tests
vendor/bin/phpunit --coverage-text      # Run tests with coverage report
vendor/bin/phpunit --testdox            # Run tests with readable output

# Single test file
vendor/bin/phpunit tests/Unit/McpServerTest.php          # Run specific test file
vendor/bin/phpunit --filter testConnect                  # Run specific test method

# Legacy Tests (Deprecated)
./simple_test.sh                        # Run basic syntax checks and MCP server tests
php test_mcp.php                        # Test MCP server functionality 
php test_integration.php                # Integration tests
php test_profiling_coverage.php         # Test profiling and coverage features
php tests/fake/demo.php                 # Run fake demo without real Xdebug
php tests/fake/FakeProfilingDemo.php    # Demo profiling and coverage features
```

### Running the MCP Server
```bash
./bin/xdebug-mcp             # MCP server entry point
php bin/xdebug-mcp           # Alternative way to start server
php bin/xdebug-mcp --help    # Show available options

# With debug mode
MCP_DEBUG=1 php bin/xdebug-mcp          # Enable debug logging
```

### Debugging with Xdebug
```bash
php -dxdebug.mode=debug test/debug_test.php    # Run PHP script with Xdebug enabled
```

## Architecture

### Core Components

- **McpServer.php**: Main MCP protocol handler that processes JSON-RPC requests and delegates to XdebugClient
  - Implements 42 MCP tools across debugging, profiling, and coverage categories
  - Handles JSON-RPC 2.0 protocol validation and routing
  - Supports debug mode via MCP_DEBUG environment variable
- **XdebugClient.php**: Xdebug protocol client that communicates directly with Xdebug via sockets
  - Socket-based communication with Xdebug daemon
  - XML response parsing and transaction management
  - Connection lifecycle and error handling
- **bin/xdebug-mcp**: Executable entry point that instantiates and runs McpServer
  - CLI interface with argument parsing
  - Standard input/output handling for MCP protocol

### MCP Tools Available
The server exposes 42 tools via MCP across three main categories:

**Debugging Tools (11 tools)**
- `xdebug_connect/disconnect`: Session management
- `xdebug_set/remove_breakpoint`: Breakpoint control  
- `xdebug_step_into/over/out`: Step debugging
- `xdebug_continue`: Resume execution
- `xdebug_get_stack/variables`: Inspection tools
- `xdebug_eval`: Expression evaluation

**Profiling Tools (4 tools)**
- `xdebug_start_profiling`: Start performance profiling
- `xdebug_stop_profiling`: Stop profiling and collect data
- `xdebug_get_profile_info`: Get profiling configuration
- `xdebug_analyze_profile`: Analyze Cachegrind format profile files

**Code Coverage Tools (6 tools)**
- `xdebug_start_coverage`: Start code coverage tracking
- `xdebug_stop_coverage`: Stop coverage tracking
- `xdebug_get_coverage`: Retrieve coverage data
- `xdebug_analyze_coverage`: Generate coverage reports (HTML/XML/JSON/text)
- `xdebug_coverage_summary`: Get coverage statistics

**Extended Tools (21 additional tools)**
- Statistics & diagnostics: Memory usage, stack depth, timing
- Error management: Error collection and analysis
- Function tracing: Call traces and function monitoring
- Advanced breakpoints: Exception and watch breakpoints
- Stack information: Detailed stack traces with arguments
- Feature configuration: Dynamic Xdebug settings

### Architecture Flow
1. MCP client sends JSON-RPC requests to McpServer
2. McpServer validates and routes tool calls to XdebugClient methods
3. XdebugClient communicates with Xdebug via socket protocol
4. Results are returned through MCP protocol back to client

### Testing Infrastructure
- **tests/fake/**: Contains fake implementations for testing without real Xdebug
- **FakeMcpServer.php**: Mock server for demonstrations
- **demo.php**: Interactive demo showing typical debugging workflow

### Configuration
- **mcp.json**: Example MCP client configuration
- **claude_desktop_config_example.json**: Example Claude Desktop setup
- **phpunit.xml**: PHPUnit configuration with separate Unit/Integration test suites
- Uses composer PSR-4 autoloading with XdebugMcp namespace
- Environment variables: MCP_DEBUG for debug logging

## PHP Requirements
- PHP >= 8.0
- ext-sockets extension for Xdebug communication
- ext-xml extension for parsing Xdebug responses
- Xdebug extension (with debug, profile, and coverage modes enabled)

## Xdebug Configuration

**IMPORTANT: This project uses port 9004 (not 9003) to avoid conflicts with IDEs**

For full functionality, configure php.ini:
```ini
zend_extension=xdebug
xdebug.mode=debug,profile,coverage  ; Enable all modes
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9004             ; Uses 9004 (IDEs use 9003)
xdebug.output_dir=/tmp              ; For profile files
```

**Port Usage:**
- **IDE/Editors (VS Code, PhpStorm)**: Port 9003
- **This Xdebug MCP Server**: Port 9004 (conflict-free)

## Key Features Integration
- **Without Xdebug session**: Profiling and coverage work standalone using Xdebug functions
- **With Xdebug session**: Full debugging capabilities plus profiling/coverage
- **PHPUnit compatibility**: Coverage tools integrate with PHPUnit test workflows
- **Multiple report formats**: HTML, XML, JSON, and text reports for coverage analysis
- **Socket communication**: Direct TCP socket communication with Xdebug daemon
- **Transaction management**: Proper request/response correlation for concurrent operations
- **Error handling**: Comprehensive error reporting and connection recovery

## Development Workflow

### MCP Server Development
1. Run PHPUnit tests to ensure functionality: `vendor/bin/phpunit`
2. Test MCP protocol manually: `echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php bin/xdebug-mcp`
3. Use fake implementations for testing without Xdebug: `php tests/fake/demo.php`
4. Enable debug mode for troubleshooting: `MCP_DEBUG=1 php bin/xdebug-mcp`

### Integration Testing
1. Start a PHP script with Xdebug enabled: `php -dxdebug.mode=debug test/debug_test.php`
2. Connect MCP server to debug session
3. Test debugging workflow through MCP tools
4. Verify profiling and coverage features

## AI Debugging Support with Trace Information

### Overview
The Xdebug trace functionality enables AI assistants to analyze detailed execution flows and variable states, providing more effective debugging assistance by understanding actual runtime behavior rather than static code analysis.

### Trace Information Collection

**Quick Trace Testing**
```bash
# Run comprehensive trace tests
./bin/xdebug-trace

# Individual trace testing methods
php -dzend_extension=xdebug -dxdebug.mode=trace bin/simple-trace-test.php
php -dzend_extension=xdebug -dxdebug.mode=trace bin/mcp-trace-test.php
```

**MCP-based Trace Collection**
```bash
# Start trace via MCP
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_start_trace","arguments":{}}}' | php bin/xdebug-mcp

# Execute target code with trace enabled
XDEBUG_TRIGGER=TRACE php -dzend_extension=xdebug -dxdebug.mode=trace target_script.php

# Stop trace and get file location
echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_stop_trace","arguments":{}}}' | php bin/xdebug-mcp
```

### Trace File Format Analysis

Trace files (`.xt` format) contain structured execution data:

**Column Structure:**
- **Level**: Function call hierarchy depth (0=root, 1=nested, etc.)
- **Function ID**: Unique identifier for function calls
- **Time Index**: Execution timestamp
- **Memory Usage**: Current memory consumption
- **Function Name**: Name of executed function
- **User Defined**: Whether function is user-defined (1) or built-in (0)
- **Include Filename**: File path for included/required files
- **Filename**: Current executing file
- **Line Number**: Source code line number
- **Parameters**: Function arguments and values

**Example Trace Output:**
```
Level  Func ID  Time Index  Memory  Function Name      User Def  Filename         Line  Params
0      1        0.0001      384000  {main}            1         test/debug.php   1     
1      2        0.0002      384100  fibonacci         1         test/debug.php   15    $n = 8
1      3        0.0003      384200  fibonacci         1         test/debug.php   15    $n = 7
```

### AI Analysis Guidelines

**1. Execution Flow Analysis**
- Track function call hierarchies to understand program flow
- Identify recursive patterns and depth
- Detect unexpected execution paths
- Analyze function entry/exit patterns

**2. Variable State Tracking**
- Monitor parameter values across function calls
- Identify value changes between calls
- Detect unexpected null/empty values
- Track variable scope transitions

**3. Performance Diagnosis**
- Analyze execution time differences
- Identify performance bottlenecks in deep call stacks
- Compare memory usage patterns
- Detect memory leaks or excessive allocations

**4. Bug Detection Patterns**
- Look for functions called with wrong parameters
- Identify infinite recursion (excessive call depth)
- Find early returns or missed function calls
- Detect exception points through missing expected calls

**5. Code Quality Assessment**
- Evaluate function call efficiency
- Identify redundant computations
- Analyze algorithmic complexity through call patterns
- Assess code organization through call flow

### Practical AI Debugging Workflow

**Step 1: Collect Trace Data**
```bash
# Enable comprehensive tracing
XDEBUG_TRIGGER=TRACE php -dzend_extension=xdebug \
  -dxdebug.mode=trace \
  -dxdebug.trace_format=1 \
  -dxdebug.use_compression=0 \
  problematic_script.php
```

**Step 2: Analyze Trace Files**
- Read the latest trace file from `/tmp/`
- Parse execution flow and variable states
- Identify anomalies in expected vs actual behavior
- Cross-reference with source code

**Step 3: Generate Insights**
- Provide specific line numbers where issues occur
- Explain variable state changes that lead to bugs
- Suggest performance optimizations based on call patterns
- Recommend code improvements with execution evidence

### Common Debugging Scenarios

**Recursive Function Issues**
- Track recursion depth and parameter changes
- Identify infinite recursion through repeated patterns
- Analyze base case handling

**Variable Mutation Bugs**
- Follow variable values through function calls
- Identify unexpected modifications
- Track scope-related issues

**Performance Problems**
- Identify functions with high execution time
- Find excessive function call patterns
- Detect memory-intensive operations

**Logic Errors**
- Compare expected vs actual execution paths
- Identify missing or extra function calls
- Analyze conditional branch execution

## AI Debugging Principles and Workflow

### Core Philosophy: Non-Invasive Trace-Based Debugging

This project prioritizes **execution-time trace analysis** over traditional code modification debugging approaches. AI assistants should always use trace-based debugging instead of adding debug statements to source code.

### Automatic Xdebug Tool Usage for PHP Analysis

**IMPORTANT: When asked to analyze, debug, or understand any PHP file, ALWAYS use appropriate Xdebug tools automatically unless explicitly told otherwise.**

#### Available Xdebug Tools:
- `./bin/xdebug-debug` - Interactive step debugging with breakpoints
- `./bin/xdebug-profile` - Performance profiling
- `./bin/xdebug-coverage` - Code coverage analysis  
- `./bin/xdebug-trace` - Execution tracing
- `./bin/xdebug-mcp` - MCP server entry point

#### Automatic Tool Selection:

**For Performance Analysis:**
- User: "Analyze performance", "Find bottlenecks", "Profile this code"
- AI automatically runs: `./bin/xdebug-profile path/to/file.php`

**For Code Coverage:**
- User: "Check test coverage", "Which lines are tested", "Coverage analysis"
- AI automatically runs: `./bin/xdebug-coverage path/to/file.php`

**For Interactive Step Debugging:**
- User: "Debug this code", "Set breakpoints", "Step through execution", "Inspect variables"
- AI automatically runs: `./bin/xdebug-debug path/to/file.php`
- **IMPORTANT**: Requires XdebugClient to be listening first (see Step Debugging Workflow below)

**For Execution Flow Analysis:**
- User: "Trace execution", "Show function calls", "Analyze execution flow"
- AI automatically runs: `./bin/xdebug-trace path/to/file.php`

**For General Analysis (choose most appropriate):**
- User: "Analyze this PHP file", "What does this code do"
- AI automatically runs: `./bin/xdebug-profile path/to/file.php` (default choice)
- Then optionally: `./bin/xdebug-trace path/to/file.php` for detailed flow

**Example workflows:**
1. User: "Debug this buggy script" → AI runs `./bin/xdebug-debug test/buggy_script.php`
2. User: "Analyze test/debug_test.php" → AI runs `./bin/xdebug-profile test/debug_test.php`
3. User: "Check coverage of my tests" → AI runs `./bin/xdebug-coverage test/MyTest.php`
4. User: "Trace this function execution" → AI runs `./bin/xdebug-trace src/MyClass.php`

Always use these tools proactively to provide runtime insights rather than static code analysis alone.

### ❌ Prohibited Debugging Methods

**Never suggest these approaches:**
- Adding `var_dump($variable)` statements
- Adding `print_r($array)` statements  
- Adding `echo "Debug: " . $value` statements
- Inserting `die()` or `exit()` calls
- Adding `error_log()` for value inspection
- Any code modification for debugging purposes

### ✅ Required Method: Trace Execution

**For ALL PHP debugging, first suggest trace execution:**

```bash
# Basic trace command for any PHP file
./bin/xdebug-trace target_file.php
```

**Alternative trace methods:**
```bash
# Direct Xdebug trigger method
XDEBUG_TRIGGER=TRACE php -dzend_extension=xdebug -dxdebug.mode=trace script.php

# Comprehensive trace with full options
php -dzend_extension=xdebug -dxdebug.mode=trace \
    -dxdebug.start_with_request=trigger \
    -dxdebug.trigger_value=TRACE \
    -dxdebug.trace_format=1 \
    -dxdebug.use_compression=0 \
    target.php
```

### Error-Specific Debugging Approaches

**1. Undefined Variable Errors**
```bash
# Trace execution to track variable lifecycle
./bin/xdebug-trace problematic_file.php
# Analyze trace file to see where variable should be initialized
# Output location: /tmp/xdebug_trace_*.xt
```

**2. Unexpected Values**
```bash
# Instead of adding var_dump, use trace execution
./bin/xdebug-trace script_with_wrong_values.php
# Trace shows actual parameter values passed to each function
```

**3. Execution Flow Issues**
```bash
# Track function call order and parameters
./bin/xdebug-trace complex_workflow.php
# Trace reveals actual execution path vs expected path
```

**4. Performance Problems**
```bash
# Identify bottlenecks through execution time analysis
./bin/xdebug-trace slow_script.php
# Trace shows time spent in each function call
```

### Trace File Analysis Guide

**Trace file structure (.xt format):**
```
Level | FuncID | Time    | Memory  | Function    | File:Line  | Parameters
0     | 1      | 0.001   | 384000  | {main}      | test.php:1 | 
1     | 2      | 0.002   | 384100  | calculate() | test.php:15| $n = 10
1     | 3      | 0.003   | 384200  | validate()  | test.php:20| $data = array(3)
```

**Analysis focus points:**
1. **Level**: Function call hierarchy (indentation level)
2. **Memory**: Memory leak detection and optimization opportunities  
3. **Parameters**: Actual argument values (eliminates need for var_dump)
4. **Time**: Performance bottleneck identification
5. **File:Line**: Exact execution location for debugging

### Response Examples

**❌ Wrong Response (Prohibited):**
```
"Add this debug code to see the variable value:
```php
var_dump($user);  // Add this line
echo "Checkpoint reached"; // Add this line  
print_r($_POST); // Add this line
```

**✅ Correct Response (Required):**
```
"Let's trace the execution to see the variable states:
```bash
./bin/xdebug-trace user_auth.php
```
This will show us the actual $user variable value at each step without modifying the code. 
The trace file will reveal:
- Where $user is initialized (or should be)
- What value it has at each function call
- The exact execution path leading to the error"
```

### MCP Tools Priority Order

When MCP server is available, use tools in this priority:

1. **Primary**: `xdebug_start_trace` / `xdebug_stop_trace`
2. **Variable Investigation**: Trace file analysis  
3. **Performance**: `xdebug_analyze_profile`
4. **Code Coverage**: `xdebug_start_coverage` / `xdebug_analyze_coverage`
5. **Last Resort**: Breakpoint debugging (only when absolutely necessary)

### Implementation Constraints

**Mandatory Guidelines:**
1. **No Code Changes**: Never suggest adding debug statements to source code
2. **Trace First**: Always propose trace execution as the first debugging step
3. **Runtime Data Priority**: Prioritize execution-time data over static code analysis
4. **Non-Invasive Analysis**: Maintain original code integrity during debugging

### Debugging Workflow Checklist

Before responding to PHP debugging requests, verify:
- [ ] Did I suggest trace execution first?
- [ ] Did I avoid recommending var_dump/print_r additions?
- [ ] Did I explain how to analyze the trace file?
- [ ] Did I focus on runtime data rather than guesswork?
- [ ] Did I maintain the principle of non-invasive debugging?

### Trace-Based Debugging Benefits

**Advantages over traditional debugging:**
- **Non-invasive**: No source code modification required
- **Comprehensive**: Complete execution flow and variable states
- **Historical**: Full timeline of program execution  
- **Accurate**: Actual runtime data, not assumptions
- **Efficient**: Single trace reveals multiple issues
- **Professional**: No debug code left in production accidentally

Follow these principles for all PHP debugging tasks to ensure consistent, professional, and effective trace-based debugging practices.

## Interactive Step Debugging Workflow

### Critical Connection Requirements

**IMPORTANT**: Interactive step debugging with `./bin/xdebug-debug` requires proper connection timing and setup.

### Step Debugging Connection Protocol

**Required Sequence for Step Debugging:**

1. **Start XdebugClient first** (must be listening before script execution)
   ```bash
   php test_new_xdebug_debug.php &
   ```

2. **Verify port availability**
   ```bash
   lsof -i :9004  # Must show PHP process LISTENING
   ```

3. **Execute target script with Xdebug**
   ```bash
   ./bin/xdebug-debug target_script.php
   ```

### Connection Architecture

**Xdebug Connection Model:**
- **Xdebug (script)**: Acts as **client** - connects to debugger
- **XdebugClient**: Acts as **server** - listens on port 9004
- **Protocol**: DBGp over TCP socket
- **Port**: 9004 (conflict-free with IDEs that use 9003)

### Common Connection Failures

**❌ Wrong Order:**
```bash
./bin/xdebug-debug script.php    # Script runs and exits
php test_new_xdebug_debug.php &  # Too late - no connection
```

**✅ Correct Order:**
```bash
php test_new_xdebug_debug.php &  # XdebugClient listening
lsof -i :9004                    # Verify LISTEN state  
./bin/xdebug-debug script.php    # Script connects to waiting client
```

### Verification Steps

**Successful Connection Indicators:**
- XdebugClient shows: `[XdebugClient] Xdebug connected!`
- Script pauses at first line waiting for debugger commands
- Breakpoints can be set and variables inspected

**Failed Connection Indicators:**
- Script executes immediately without pausing
- No connection messages in XdebugClient output
- `Address already in use` errors when starting XdebugClient

### Step Debugging vs Trace Analysis

**When to use Interactive Step Debugging:**
- Need to inspect specific variable states at breakpoints
- Require step-by-step execution control (stepInto, stepOver)
- Interactive analysis of execution flow

**When to use Trace Analysis (preferred):**
- General debugging and bug identification
- Performance analysis  
- Complete execution flow analysis
- Non-invasive analysis without connection complexity

**Default Recommendation**: Use trace-based debugging first, step debugging only when interactive control is specifically needed.