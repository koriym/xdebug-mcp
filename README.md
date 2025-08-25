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

### Your First AI Profile Analysis

Try running your first AI-assisted profile analysis:

```bash
# Run automatic profiling with AI analysis
./vendor/bin/xdebug-profile --claude -- php vendor/koriym/xdebug-mcp/test-scripts/deep_recursion_test.php
# ✅ Claude automatically analyzes performance bottlenecks and suggests optimizations
```

This command will:
1. Profile the execution with Xdebug
2. Generate a Cachegrind file
3. Automatically invoke Claude to analyze the results
4. Provide specific performance improvement recommendations

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

**3. Deep AI Investigation:**
```bash
# After running step 1 or 2, ask targeted questions:
./vendor/bin/xdebug-profile -- php test-scripts/sqlite_db_test.php

# Then ask AI to analyze:
> What are the heaviest functions in this profile?
> Are there any N+1 query problems? How can I fix them?

# For detailed investigation, run trace analysis:
./vendor/bin/xdebug-trace -- php test-scripts/sqlite_db_test.php

# Then ask for deeper analysis:
> Show me the exact execution flow that causes the N+1 queries
> How can I improve memory usage in this code?
> Identify performance bottlenecks from the execution trace
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

### Shell Usage (Primary Workflow)

#### Performance Analysis
```bash
# Run profiling
./vendor/bin/xdebug-profile -- php test-scripts/deep_recursion_test.php

# Ask AI to analyze results
> What are the heaviest functions in this profile?
> How can I improve these performance bottlenecks?
> What are the best ways to optimize memory usage?
```

#### Trace Analysis  
```bash
# Run execution tracing
./vendor/bin/xdebug-trace -- php test-scripts/sqlite_db_test.php

# Ask AI to analyze trace data
> Are there any N+1 query problems in this function?
> Identify the slowest execution paths
> Are there any unexpected execution flows?
```

#### Interactive Step Debugging
```bash
# Start debugging session
./vendor/bin/xdebug-debug test-scripts/buggy_calculation_code.php

# Use natural language with AI
> Set a breakpoint at line 15 and show me the local variables
> Step over to the next line and check the value of $result
> Continue execution to the next breakpoint
```

**AI Debugging Benefits:** AI automatically analyzes execution traces instead of requiring `var_dump()` or `echo` statements. This provides accurate debugging insights from actual runtime data without code modification.

### Console Shortcuts

Quick one-liner commands for immediate AI analysis:

```bash
# Auto-analyze with Claude immediately  
./vendor/bin/xdebug-profile --claude -- php script.php
./vendor/bin/xdebug-trace --claude -- php script.php

# ✅ Automatically runs analysis and prompts Claude for insights
```

## MCP Tools (47 Available)

**All 47 MCP tools are automatically used by AI** when you interact through shell commands. The tools provide:

### Tool Categories
- **Profiling & Performance**: 4 tools (timing, memory, bottleneck analysis)
- **Trace Analysis**: 4 tools (execution flow, function monitoring, N+1 detection)
- **Interactive Debugging**: 11 tools (breakpoints, step execution, variables)  
- **Configuration & Diagnostics**: 17 tools (memory, stack depth, error collection)
- **Code Coverage**: 6 tools (line/function coverage, HTML/XML reports)
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

