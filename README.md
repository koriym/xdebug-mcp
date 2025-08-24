# PHP Xdebug MCP Server

> Enable AI to use Xdebug for PHP debugging like we do

<img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

MCP server enabling AI control of PHP Xdebug debugging, profiling, and coverage analysis.

## Features

- **47 Working MCP Tools**: Complete AI-driven PHP debugging suite (100% tested)
- **Interactive Step Debugging**: Full breakpoint, step execution, and variable inspection
- **Trace-based Debugging**: AI analyzes runtime execution data (no var_dump needed)
- **IDE Compatible**: Port 9004 avoids conflicts with PhpStorm/VS Code (9003)
- **Command Line Tools**: 5 standalone debugging utilities

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

**1. Start MCP server:**
```bash
./vendor/bin/xdebug-mcp
# ✅ Server starts on port 9004, ready for AI commands
```

**2. Ask AI to debug with runtime data:**
```bash
# In another terminal - AI analyzes actual execution instead of guessing
claude --print "Analyze test-scripts/sqlite_db_test.php for N+1 problems"
# ✅ AI automatically runs xdebug-trace --json and provides data-driven analysis
```

**3. Interactive step debugging with AI:**
```bash
# AI can now perform full interactive debugging
claude --print "Debug test/buggy_script.php with breakpoints and step execution"
# ✅ AI sets up XdebugClient, connects, sets breakpoints, and inspects variables
```

**4. Zero-config PHPUnit debugging:**
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

### Command Line Tools

- `xdebug-mcp` - MCP server (port 9004)
- `xdebug-debug` - Interactive step debugging with breakpoints
- `xdebug-trace` - Execution flow tracing and N+1 database analysis
- `xdebug-profile` - Performance profiling and bottleneck identification
- `xdebug-coverage` - Code coverage analysis
- `xdebug-phpunit` - PHPUnit with selective Xdebug analysis

### Basic Commands

```bash
# Recommended: Use bin/xdebug-* commands
./vendor/bin/xdebug-debug script.php    # Interactive debugging with breakpoints
./vendor/bin/xdebug-trace script.php    # Execution tracing
./vendor/bin/xdebug-profile script.php  # Performance profiling
./vendor/bin/xdebug-coverage script.php # Code coverage analysis
```

**Manual approach (step debugging example):**
```bash
# Step debugging example (manual). For traces/profiles/coverage, prefer ./vendor/bin/xdebug-*
# or set the appropriate xdebug.mode values and ini flags manually.
php -dzend_extension=xdebug -dxdebug.mode=debug -dxdebug.client_port=9004 script.php
```

## Tool Usage

### 🔍 Trace Analysis (Execution Flow & Database)
```bash
# N+1 problem detection
./vendor/bin/xdebug-trace test-scripts/sqlite_db_test.php
# ✅ 83 lines, 10 functions, 3 max depth, 🗃️ 22 database queries

# Recursion analysis  
./vendor/bin/xdebug-trace test-scripts/deep_recursion_test.php
# ✅ 50 lines, 3 functions, 🔄 10 max call depth
```

### ⚡ Performance Profiling (Bottleneck Identification)
```bash  
# Performance bottleneck identification
./vendor/bin/xdebug-profile test-scripts/deep_recursion_test.php
# ✅ 2.1K, 12 functions, 45 calls, 🎯 countdown (45.2%), factorial (31.8%)

# Memory and timing analysis
./vendor/bin/xdebug-profile test-scripts/sqlite_db_test.php  
# ✅ 3.2K, 18 functions, ⏱️ 0.002s, 💾 420KB
```

### 🐛 Interactive Step Debugging (Breakpoints & Variables)
```bash
# Single command execution (AMP-powered)
./vendor/bin/xdebug-debug test-scripts/buggy_calculation_code.php
# ✅ Breakpoints, variable inspection, step execution
```

### 📊 Code Coverage Analysis (Test Quality)
```bash
# Code path coverage analysis  
./vendor/bin/xdebug-coverage test-scripts/deep_recursion_test.php
# ✅ HTML report, 📊 85.2% lines, 92.1% functions
```

### 🧪 PHPUnit Testing (Zero Configuration)
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


## Troubleshooting

- Check `claude mcp list` 
- Verify Xdebug installation: `php -m | grep xdebug`
- Port conflicts: xdebug-mcp uses 9004, IDEs use 9003


## Links

- [Templates & Deployment Guide](templates/README.md)
- [Xdebug Docs](https://xdebug.org/docs/)
- [MCP Specification](https://modelcontextprotocol.io/)
- [Claude Desktop MCP](https://docs.anthropic.com/claude/docs/mcp)
