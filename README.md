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

**AI Slash Commands** (Claude Code):
```bash
/x-debug "script.php" "script.php:42:$error!=null" "" "Debug error handling"
/x-trace script="auth.php" context="Login flow analysis"
```


## Available Tools

### Core CLI Tools
- **`xdebug-debug`** - Interactive debugging shell with conditional breakpoints and step recording
  ```bash
  # Interactive REPL debugger with commands: s(tep), o(ver), c(ontinue), p <var>, claude, q(uit)
  ./vendor/bin/xdebug-debug -- php app.php
  ```
- **`xdebug-profile`** - Performance profiling with microsecond precision
- **`xdebug-trace`** - Complete execution flow tracing
- **`xdebug-coverage`** - Code coverage analysis with multiple output formats
- **`xdebug-phpunit`** - PHPUnit integration with Xdebug profiling and coverage

### AI Integration Features
- **42+ MCP Tools**: Performance profiling, code coverage, execution tracing, memory diagnostics, error tracking
- **Slash Commands**: `/x-debug`, `/x-profile`, `/x-trace`, `/x-coverage` for Claude Code
- **Schema-Validated Output**: JSON that any AI can understand and analyze

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
