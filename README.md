# PHP Xdebug MCP Server

> Enable AI to use Xdebug for PHP debugging like we do

<img width="256" alt="xdebug-mcp" src="https://koriym.github.io/xdebug-mcp/logo.jpeg" />

MCP server enabling AI control of PHP Xdebug debugging, profiling, and coverage analysis.

## Features

- **AI-First Debugging**: AI analyzes actual runtime traces instead of guessing from static code
- **Trace-based Analysis**: Complete execution flow analysis with N+1 query detection
- **Performance Profiling**: Cachegrind output analysis with AI-driven bottleneck identification  
- **Interactive Step Debugging**: Full breakpoint, step execution, and variable inspection

## Core Xdebug Functions

- **🔍 Trace**: Execution flow analysis, function call monitoring, N+1 query detection
- **⚡ Profile**: Performance analysis, bottleneck identification, Cachegrind output  
- **📊 Coverage**: Line/function coverage, HTML/XML reports, PHPUnit integration
- **🐛 Step Debugging**: Breakpoints, variable inspection, interactive execution control

**All Xdebug functions are MCP-enabled** - 47 AI tools provide complete access to Xdebug's trace, profile, coverage, step debugging, configuration, and diagnostic capabilities.

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

## Usage

### Quick Start Workflows

**Performance Analysis with AI:**
```bash
# Run profiling with automatic AI analysis
./vendor/bin/xdebug-profile --claude -- php test-scripts/deep_recursion_test.php
# ✅ Automatically profiles and asks Claude to analyze performance bottlenecks
```

**Execution Tracing for Debugging:**
```bash
# Trace execution flow for AI analysis
./vendor/bin/xdebug-trace --claude -- php test-scripts/sqlite_db_test.php
# ✅ Traces execution and prompts Claude to analyze for N+1 queries and logic issues
```

**Deep AI Investigation:**
```bash
# Combine profiling and tracing for comprehensive analysis
./vendor/bin/xdebug-profile -- php test-scripts/sqlite_db_test.php
./vendor/bin/xdebug-trace -- php test-scripts/sqlite_db_test.php

# Ask AI to investigate:
> Show me the exact execution flow that causes the N+1 queries
> What are the performance bottlenecks and how to fix them?
```

**Advanced: PHPUnit Debugging with AI:**
```bash
# AI-assisted test debugging with automatic Xdebug setup
./vendor/bin/xdebug-phpunit tests/UserTest.php::testLogin
# ✅ TraceExtension auto-injected, traces specific test method
```

### Shell Usage (Detailed AI Interaction)

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


## Development

**macOS users:** If `timeout` command is not available, install GNU coreutils:
```bash
brew install coreutils
```

## Troubleshooting

- Check `claude mcp list` 
- Verify Xdebug installation: `php -dzend_extension=xdebug -m | grep xdebug`
- Port conflicts: xdebug-mcp uses 9004, IDEs use 9003


## Links

- [Templates & Deployment Guide](templates/README.md)
- [Xdebug Docs](https://xdebug.org/docs/)
- [MCP Specification](https://modelcontextprotocol.io/)
- [Claude Desktop MCP](https://docs.anthropic.com/claude/docs/mcp)

