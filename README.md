# PHP Xdebug MCP Server

> Enable AI to use Xdebug for PHP debugging like we do

<img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

MCP server enabling AI control of PHP Xdebug debugging, profiling, and coverage analysis.

## Features

- **AI-First Debugging**: AI analyzes actual runtime traces instead of guessing from static code
- **Trace-based Analysis**: Complete execution flow analysis with N+1 query detection
- **Performance Profiling**: Cachegrind output analysis with AI-driven bottleneck identification  
- **47 Working MCP Tools**: Complete AI-driven PHP debugging suite (100% tested)
- **Interactive Step Debugging**: Full breakpoint, step execution, and variable inspection
- **IDE Compatible**: Port 9004 avoids conflicts with PhpStorm/VS Code (9003)

## Working Tool Categories

- **Profiling & Performance**: Analysis, function timing, Cachegrind output (4 tools) ✅ 100%
- **Code Coverage**: Line/function coverage, HTML/XML reports, PHPUnit integration (6 tools) ✅ 100%
- **Interactive Debugging**: Breakpoints, step execution, variable inspection (11 tools) ✅ 100%
- **Trace Analysis**: Function call tracing, execution flow monitoring (4 tools) ✅ 100%
- **Configuration & Diagnostics**: Settings, memory usage, stack depth, error collection (17 tools) ✅ 100%
- **CLI Tools**: Standalone debugging utilities (5 tools) ✅ 100%

**All 47 tools are fully functional and AI-tested** with specialized Profile (performance analysis) and Trace (execution flow & N+1 detection) tools providing AI-native JSON output.

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

**1. Performance Analysis with AI:**
```bash
# Run profiling with automatic AI analysis
./vendor/bin/xdebug-profile --claude -- php test-scripts/deep_recursion_test.php
# ✅ Automatically profiles and asks Claude to analyze performance bottlenecks
```

**2. Execution Tracing for Debugging:**
```bash
# Trace execution flow for AI analysis
./vendor/bin/xdebug-trace --claude -- php test-scripts/sqlite_db_test.php
# ✅ Traces execution and prompts Claude to analyze for N+1 queries and logic issues
```

**3. Ask AI specific questions:**
```bash
# After running profile/trace, ask targeted questions
claude --print "What are the heaviest functions in this profile?"
claude --print "Are there any N+1 query problems? How can I fix them?"
claude --print "How can I improve memory usage in this code?"
claude --print "Identify performance bottlenecks from the execution trace"
```

**4. Advanced: PHPUnit Debugging with AI:**
```bash
# AI-assisted test debugging with automatic Xdebug setup
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin
# ✅ TraceExtension auto-injected, traces specific test method
```

## Verification

**Test AI integration:**
```bash
# Performance profiling
claude --print "Profile test-scripts/deep_recursion_test.php and show bottlenecks"
# ✅ AI runs xdebug-profile --json and analyzes performance data

# Database analysis
claude --print "Check test-scripts/sqlite_db_test.php for N+1 query problems"
# ✅ AI runs xdebug-trace --json and reports database query statistics
```

**Manual verification (optional):**
```bash
# Direct tool usage with JSON output for AI analysis
./vendor/bin/xdebug-trace --json -- php test-scripts/sqlite_db_test.php
./vendor/bin/xdebug-profile --json -- php test-scripts/deep_recursion_test.php  
./vendor/bin/xdebug-coverage --json -- php test-scripts/deep_recursion_test.php
```

**Expected results:**
- JSON-structured trace data with database query counts and execution flow
- Performance data with function costs and bottleneck identification
- Coverage reports with line/function percentages and untested paths
- AI providing data-driven analysis with precise metrics instead of static code guessing


## Usage

### CLI Tools (Human-friendly)

**Primary tools for direct execution and analysis:**
- `xdebug-profile` - Performance profiling and bottleneck identification
- `xdebug-trace` - Execution flow tracing and N+1 database analysis
- `xdebug-phpunit` - PHPUnit with selective Xdebug analysis

```bash
# Performance profiling
./vendor/bin/xdebug-profile -- php script.php          # Human-readable output
./vendor/bin/xdebug-profile --claude -- php script.php # Auto-invoke AI analysis

# Execution tracing (recommended for debugging)
./vendor/bin/xdebug-trace -- php script.php            # Human-readable output  
./vendor/bin/xdebug-trace --claude -- php script.php   # Auto-invoke AI analysis
```

### MCP Integration (AI-driven)

**Automatic AI analysis of trace data and interactive debugging:**
- AI automatically reads and analyzes trace files generated by CLI tools
- Interactive step debugging with breakpoints and variable inspection
- Advanced debugging scenarios through MCP tool calls

**Manual approach (step debugging example):**
```bash
# Step debugging example (manual). For traces/profiles/coverage, prefer ./vendor/bin/xdebug-*
# or set the appropriate xdebug.mode values and ini flags manually.
php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_port=9004 script.php
```

## Tool Usage

### Human CLI Usage (Direct Execution)

### Trace Analysis (Execution Flow & Database)
```bash
# N+1 problem detection - Human readable output
./vendor/bin/xdebug-trace -- php test-scripts/sqlite_db_test.php
# ✅ Trace complete: /tmp/trace.1034012359.xt
# 📊 83 lines generated, 10 unique functions, 3 max depth, 🗃️ 22 database queries

# Recursion analysis - Human readable output
./vendor/bin/xdebug-trace -- php test-scripts/deep_recursion_test.php
# ✅ Trace complete: /tmp/trace.1034012360.xt  
# 📊 50 lines generated, 3 unique functions, 🔄 10 max call depth
```

### Performance Profiling (Bottleneck Identification)
```bash  
# Performance bottleneck identification - Human readable output
./vendor/bin/xdebug-profile -- php test-scripts/deep_recursion_test.php
# ✅ Profile complete: /tmp/cachegrind.out.1755719364
# 📊 Size: 2.1K, Functions: 12, Calls: 45
# 🎯 Top functions: countdown (45.2%), factorial (31.8%), {main} (23.0%)

# Memory and timing analysis - Human readable output
./vendor/bin/xdebug-profile -- php test-scripts/sqlite_db_test.php  
# ✅ Profile complete: /tmp/cachegrind.out.1755719365
# 📊 Size: 3.2K, Functions: 18, ⏱️ 0.002s, 💾 420KB
```

### Interactive Step Debugging (Breakpoints & Variables)
```bash
# Single command execution (AMP-powered, no manual setup)
./vendor/bin/xdebug-debug test-scripts/buggy_calculation_code.php
# ✅ Interactive debugging session with breakpoints, variable inspection, step execution
```

### Code Coverage Analysis (Test Quality)
```bash
# Code path coverage analysis - Human readable output
./vendor/bin/xdebug-coverage -- php test-scripts/deep_recursion_test.php
# ✅ Coverage complete: HTML report generated  
# 📊 Coverage: 85.2% lines, 92.1% functions, identifies untested code paths
```

### AI Usage (JSON Output for Analysis)

**For AI Analysis - Tools automatically use --json flag when called by AI:**
```bash
# AI receives structured JSON data for precise analysis
claude --print "Analyze test-scripts/sqlite_db_test.php for N+1 problems"
# AI automatically runs: ./vendor/bin/xdebug-trace --json -- php test-scripts/sqlite_db_test.php
# AI receives: {"trace_file":"/tmp/trace.xt","total_lines":83,"unique_functions":10,"max_call_depth":3,"database_queries":22}

claude --print "Profile test-scripts/deep_recursion_test.php performance"  
# AI automatically runs: ./vendor/bin/xdebug-profile --json -- php test-scripts/deep_recursion_test.php
# AI receives: {"profile_file":"/tmp/cachegrind.out.123","size":"2.1K","functions":12,"calls":45}
```

### PHPUnit Testing (Zero Configuration)
```bash
# Trace specific test method (default)
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin

# Profile entire test file
./vendor/bin/xdebug-phpunit --profile tests/UserTest.php

# Auto-injection of TraceExtension, no manual setup required
```

## MCP Tools (47 Available)

**All 47 MCP tools are fully functional and AI-tested.** The tools are automatically used by AI assistants when you use commands like:

```bash
# AI automatically selects appropriate tools
claude --print "Profile test-scripts/deep_recursion_test.php"
claude --print "Trace test-scripts/sqlite_db_test.php for N+1 problems"  
claude --print "Debug test-scripts/buggy_calculation_code.php with breakpoints"
```

### Tool Categories
- **Profiling & Performance**: 4 tools (timing, memory, bottleneck analysis)
- **Code Coverage**: 6 tools (line/function coverage, HTML/XML reports)
- **Interactive Debugging**: 11 tools (breakpoints, step execution, variables)
- **Trace Analysis**: 4 tools (execution flow, function monitoring)
- **Configuration & Diagnostics**: 17 tools (memory, stack depth, error collection)
- **CLI Tools**: 5 tools (standalone utilities)


## Development

### MCP Tool Testing
```bash
# Test all MCP tools functionality
./bin/test-all.sh
```

**macOS users:** If timeout command is not available, install GNU coreutils:
```bash
brew install coreutils
```

## Troubleshooting

- Check `claude mcp list` 
- Verify Xdebug installation: `php -m | grep xdebug`
- Port conflicts: xdebug-mcp uses 9004, IDEs use 9003


## Links

- [Templates & Deployment Guide](templates/README.md)
- [Xdebug Docs](https://xdebug.org/docs/)
- [MCP Specification](https://modelcontextprotocol.io/)
- [Claude Desktop MCP](https://docs.anthropic.com/claude/docs/mcp)

