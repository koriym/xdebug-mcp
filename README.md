# PHP Xdebug MCP Server

<img width="256" alt="xdebug-mcp" src="docs/images/logo.jpeg" />

> **Error message is the crime photo. Trace is the crime footage.**  
> Don't just see the crime. Watch how it happened.

**Enable AI to Debug PHP Autonomously, Beyond Human IDE Capabilities**

[![Debugging](https://img.shields.io/badge/AI_Native-YES-green)](https://github.com/koriym/xdebug-mcp)
[![Runtime](https://img.shields.io/badge/Runtime_Data-YES-green)](https://github.com/koriym/xdebug-mcp)
[![var_dump](https://img.shields.io/badge/var__dump()-NO-red)](https://github.com/koriym/xdebug-mcp)
[![Guesswork](https://img.shields.io/badge/Guesswork-NO-red)](https://github.com/koriym/xdebug-mcp)

---

## The Problem: From var_dump() to xdebug_start_trace()

When you ask AI to debug PHP today, it adds `var_dump()` to your code—the same technique from 30 years ago.

Why? Because **AI is debugging blind**, only able to read static code and guess what happens at runtime.

## The Solution: Forward Trace™

**Transform AI debugging from `var_dump()` to `xdebug_start_trace()`** — a paradigm shift from static guesswork to runtime intelligence.

This MCP server enables AI to debug PHP with superhuman capabilities:

- **Watch execution unfold live**: Record runtime behavior from any point forward as it happens
- **Track variable evolution**: Watch every variable change step-by-step
- **Set intelligent traps**: Conditional breakpoints that capture exact problem moments
- **Verify AI code quality**: Beyond tests passing - see if code is actually efficient
- **Share debug sessions**: Schema-validated JSON that any AI can analyze
- **Debug without touching code**: Zero var_dumps, zero pollution

## Quick Start

```bash
# Install
composer require koriym/xdebug-mcp

# Enable AI debugging
echo "@vendor/koriym/xdebug-mcp/docs/debug_guideline_for_ai.md" >> CLAUDE.md
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"

# 🎯 Start with AI-optimized help (recommended first step)
./vendor/bin/xdebug-debug --help     # Learn optimal AI debugging workflow
./vendor/bin/xdebug-coverage --help  # Understand AI coverage analysis
./vendor/bin/xdebug-trace --help     # Master execution flow tracing
./vendor/bin/xdebug-profile --help   # Performance optimization guidance

# Example: Catch null bugs automatically  
./vendor/bin/xdebug-debug --break='script.php:42:$user==null' --exit-on-break -- php script.php
```

## Forward Trace™ vs Traditional Debugging

| Traditional Debugging | Forward Trace |
|----------------------|----------------|
| Post-crash investigation | Live execution monitoring |
| Add var_dump(), test, remove | Zero code modification |
| Manual stepping through IDE | Automatic variable evolution recording |
| One developer, one session | Schema-validated JSON for any AI |
| Hours of investigation | Seconds of AI analysis |

### Two Powerful Modes

**1. Conditional Breakpoints** - Stop when problems occur:
```bash
./vendor/bin/xdebug-debug --break='script.php:42:$user==null' --exit-on-break -- php script.php
```

**2. Step Recording** - Watch variable evolution:
```bash
./vendor/bin/xdebug-debug --break='script.php:17' --steps=100 --json -- php script.php
```

## Common Usage Patterns

**Catch Null Values** (The #1 PHP Bug):
```bash
./vendor/bin/xdebug-debug --break='User.php:85:$user==null' --exit-on-break -- php app.php
```

**Performance Analysis**:
```bash
./vendor/bin/xdebug-profile --context="API performance" --json -- php api.php
```

**Variable Evolution**:
```bash
./vendor/bin/xdebug-debug --break='loop.php:45' --steps=100 --json -- php app.php
```

**AI Code Quality Verification**:
```bash
# Tests pass ✅ but is the code actually efficient?
./vendor/bin/xdebug-trace --context="AI generated algorithm efficiency check" ai_code.php
```

**Vendor Filtering** (Focus on specific packages):
```bash
# Include only specific vendor packages in trace
./vendor/bin/xdebug-trace --include-vendor=bear/resource,ray/di script.php

# Use wildcards for package groups  
./vendor/bin/xdebug-trace --include-vendor=bear/* script.php

# Include all vendor code
./vendor/bin/xdebug-trace --include-vendor=*/* script.php
```

**AI Slash Commands** (Claude Code):
```bash
/x-debug "script.php" "script.php:42:$error!=null" "" "Debug error handling"
/x-trace script="auth.php" context="Login flow analysis" include_vendor="bear/*"
```


## Available Tools

### 🤖 AI-Optimized CLI Tools
All tools now feature comprehensive AI-optimized help documentation. **Always run `--help` first** to understand optimal usage patterns:

- **`xdebug-debug`** 🔍 - Interactive debugging shell with conditional breakpoints and step recording
  ```bash
  ./vendor/bin/xdebug-debug --help  # 📖 Essential reading: AI debugging workflow
  # Interactive REPL debugger with commands: s(tep), o(ver), c(ontinue), p <var>, claude, q(uit)
  ./vendor/bin/xdebug-debug -- php app.php
  ```

- **`xdebug-coverage`** 🎯 - Superior alternative to PHPUnit HTML/XML coverage for AI analysis
  ```bash
  ./vendor/bin/xdebug-coverage --help  # 📖 Learn why this beats HTML reports
  ./vendor/bin/xdebug-coverage         # Auto-detects PHPUnit, outputs TestDox + JSON
  ```

- **`xdebug-trace`** 📊 - Ultimate alternative to static code analysis  
  ```bash
  ./vendor/bin/xdebug-trace --help     # 📖 Runtime reality vs theoretical analysis
  ```

- **`xdebug-profile`** ⚡ - Scientific performance optimization with precision metrics
  ```bash
  ./vendor/bin/xdebug-profile --help   # 📖 AI-driven optimization workflow
  ```

- **`xdebug-phpunit`** - PHPUnit integration with Xdebug profiling and coverage

### 🎯 AI-First Design Philosophy

**Start Here**: Every tool includes comprehensive AI-optimized help documentation designed to teach optimal usage patterns:

```bash
# Recommended AI prompt for any PHP debugging task:
"Run [tool] --help first to understand this tool, then help me debug this issue"
```

**What makes this AI-optimized?**
- ✅ **Value Proposition Clear**: Why this beats traditional debugging methods
- ✅ **Workflow Integration**: Step-by-step AI collaboration processes  
- ✅ **Practical Examples**: Real-world usage patterns with context
- ✅ **Output Optimization**: JSON formats designed for AI consumption
- ✅ **Cognitive Load Reduction**: Mixed output streams AI can parse efficiently

### AI Integration Features
- **42+ MCP Tools**: Performance profiling, code coverage, execution tracing, memory diagnostics, error tracking
- **Slash Commands**: `/x-debug`, `/x-profile`, `/x-trace`, `/x-coverage` for Claude Code
- **Schema-Validated Output**: JSON that any AI can understand and analyze
- **Dynamic Vendor Filtering**: AI can specify which vendor packages to include/exclude during analysis

## Installation

```bash
# Install
composer require koriym/xdebug-mcp

# Enable AI debugging
echo "@vendor/koriym/xdebug-mcp/docs/debug_guideline_for_ai.md" >> CLAUDE.md
claude mcp add xdebug php "$(pwd)/vendor/bin/xdebug-mcp"
```

## Troubleshooting & Diagnostics

```bash
# Environment verification
./vendor/bin/check-env                    # Verify Xdebug installation
php -dxdebug.mode=debug --version        # Test Xdebug loading
claude mcp list                          # Verify MCP integration
```

## Resources

📋 **[TROUBLESHOOTING.md](https://koriym.github.io/xdebug-mcp/TROUBLESHOOTING)** - Setup and common issues  
🎯 **[Forward Trace Guide](https://koriym.github.io/xdebug-mcp/debug-guidelines/)** - AI debugging methodology  
📖 **[MOTIVATION.md](MOTIVATION.md)** - Why we built this  
🎬 **[Interactive Presentation](https://koriym.github.io/xdebug-mcp/slide/)** - See the paradigm shift  
📚 **[Xdebug Documentation](https://xdebug.org/docs/)** - Official Xdebug docs  

---

**Stop debugging blind. Give AI the power of Forward Trace.**

*Transform your PHP debugging from guesswork to intelligence.*

*Debug once, analyze anywhere - with schema-validated JSON that any AI can understand.*
