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
# Install globally
composer global require koriym/xdebug-mcp

# Configure Claude Desktop MCP (~/.claude/claude_desktop_config.json)
{
  "mcpServers": {
    "xdebug": {
      "command": "php",
      "args": ["$HOME/.composer/vendor/bin/xdebug-mcp"],
      "env": {
        "PATH": "/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin"
      }
    }
  }
}

# Restart Claude Code to connect MCP server

# 🎯 Start with AI-optimized help (recommended first step)
~/.composer/vendor/bin/xdebug-debug --help     # Learn optimal AI debugging workflow
~/.composer/vendor/bin/xdebug-coverage --help  # Understand AI coverage analysis
~/.composer/vendor/bin/xdebug-trace --help     # Master execution flow tracing
~/.composer/vendor/bin/xdebug-profile --help   # Performance optimization guidance
~/.composer/vendor/bin/xdebug-backtrace --help # Get stack trace at breakpoint

# Example: Catch null bugs automatically
~/.composer/vendor/bin/xdebug-debug --break='script.php:42:$user==null' --exit-on-break -- php script.php
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
~/.composer/vendor/bin/xdebug-debug --break='script.php:42:$user==null' --exit-on-break -- php script.php
```

**2. Step Recording** - Watch variable evolution:
```bash
~/.composer/vendor/bin/xdebug-debug --break='script.php:17' --steps=100 --json -- php script.php
```

## Common Usage Patterns

**Catch Null Values** (The #1 PHP Bug):
```bash
~/.composer/vendor/bin/xdebug-debug --break='User.php:85:$user==null' --exit-on-break -- php app.php
```

**Performance Analysis**:
```bash
~/.composer/vendor/bin/xdebug-profile --context="API performance" --json -- php api.php
```

**Variable Evolution**:
```bash
~/.composer/vendor/bin/xdebug-debug --break='loop.php:45' --steps=100 --json -- php app.php
```

**AI Code Quality Verification**:
```bash
# Tests pass ✅ but is the code actually efficient?
~/.composer/vendor/bin/xdebug-trace --context="AI generated algorithm efficiency check" ai_code.php
```

**Vendor Filtering** (Focus on specific packages):
```bash
# Include only specific vendor packages in trace
~/.composer/vendor/bin/xdebug-trace --include-vendor=bear/resource,ray/di script.php

# Use wildcards for package groups
~/.composer/vendor/bin/xdebug-trace --include-vendor=bear/* script.php

# Include all vendor code
~/.composer/vendor/bin/xdebug-trace --include-vendor=*/* script.php
```

**AI Slash Commands** (Claude Code):
```bash
/x-debug "script.php" "script.php:42:$error!=null" "" "Debug error handling"
/x-trace script="auth.php" context="Login flow analysis" include_vendor="bear/*"
/x-backtrace script="app.php" breakpoint="app.php:50" context="Check call hierarchy"
```


## Available Tools

### 🤖 AI-Optimized CLI Tools
All tools now feature comprehensive AI-optimized help documentation. **Always run `--help` first** to understand optimal usage patterns:

- **`xdebug-debug`** 🔍 - Interactive debugging shell with conditional breakpoints and step recording
  ```bash
  ~/.composer/vendor/bin/xdebug-debug --help  # 📖 Essential reading: AI debugging workflow
  # Interactive REPL debugger with commands: s(tep), o(ver), c(ontinue), p <var>, claude, q(uit)
  ~/.composer/vendor/bin/xdebug-debug -- php app.php
  ```

- **`xdebug-coverage`** 🎯 - Superior alternative to PHPUnit HTML/XML coverage for AI analysis
  ```bash
  ~/.composer/vendor/bin/xdebug-coverage --help  # 📖 Learn why this beats HTML reports
  ~/.composer/vendor/bin/xdebug-coverage         # Auto-detects PHPUnit, outputs TestDox + JSON
  ```

- **`xdebug-trace`** 📊 - Ultimate alternative to static code analysis
  ```bash
  ~/.composer/vendor/bin/xdebug-trace --help     # 📖 Runtime reality vs theoretical analysis
  ```

- **`xdebug-profile`** ⚡ - Scientific performance optimization with precision metrics
  ```bash
  ~/.composer/vendor/bin/xdebug-profile --help   # 📖 AI-driven optimization workflow
  ```

- **`xdebug-backtrace`** 📋 - Get stack trace (backtrace) at breakpoint
  ```bash
  ~/.composer/vendor/bin/xdebug-backtrace --help  # 📖 Understand call hierarchy
  ~/.composer/vendor/bin/xdebug-backtrace --break='app.php:50' -- php app.php
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
- **Slash Commands**: `/x-debug`, `/x-profile`, `/x-trace`, `/x-coverage`, `/x-backtrace` for Claude Code
- **Schema-Validated Output**: JSON that any AI can understand and analyze
- **Dynamic Vendor Filtering**: AI can specify which vendor packages to include/exclude during analysis

## Installation

```bash
# Install globally
composer global require koriym/xdebug-mcp

# Configure Claude Desktop MCP
# Edit ~/.claude/claude_desktop_config.json:
{
  "mcpServers": {
    "xdebug": {
      "command": "php",
      "args": ["$HOME/.composer/vendor/bin/xdebug-mcp"],
      "env": {
        "PATH": "/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin"
      }
    }
  }
}

# Restart Claude Code to activate MCP integration
```

## Troubleshooting & Diagnostics

```bash
# Environment verification
composer check-env # Verify Xdebug installation
php -dzend_extension=xdebug.so -dxdebug.mode=debug --version # Test Xdebug loading

# MCP connection test
/mcp                                     # Check MCP server status in Claude Code
```

## Resources

📋 **[TROUBLESHOOTING.md](https://koriym.github.io/xdebug-mcp/TROUBLESHOOTING)** - Setup and common issues
🎯 **[Forward Trace Guide](https://koriym.github.io/xdebug-mcp/debug-guidelines/)** - AI debugging methodology
📖 **[MOTIVATION.md](MOTIVATION.md)** - Why we built this
🐳 **[Docker Integration](tests/docker/README.md)** - Container debugging guide
🎬 **[Interactive Presentation](https://koriym.github.io/xdebug-mcp/slide/)** - See the paradigm shift
📚 **[Xdebug Documentation](https://xdebug.org/docs/)** - Official Xdebug docs

## Docker Integration

All xdebug-mcp tools work seamlessly with Docker, Podman, and Kubectl:

```bash
# Breakpoint debugging in Docker container
./bin/xdebug-debug --break="/app/script.php:42" --exit-on-break -- \
  docker compose run --rm php php /app/script.php

# Conditional breakpoint (catch null bugs)
./bin/xdebug-debug --break="/app/script.php:42:\$user==null" --exit-on-break -- \
  docker compose run --rm php php /app/script.php

# Trace execution in Docker container
./bin/xdebug-trace --context="Docker debug" -- \
  docker compose run --rm php php /app/script.php

# Profile performance in container
./bin/xdebug-profile -- docker compose exec -T php php /app/api.php

# Coverage analysis with Podman
./bin/xdebug-coverage -- podman run --rm php:8.4 php /app/tests.php
```

**Note**: Breakpoint paths like `/app/script.php` refer to paths inside the container, not on the host.

The tools automatically:
- Detect container commands (docker, podman, kubectl)
- Skip local file validation for container paths
- Inject Xdebug arguments at the correct position
- Listen on `0.0.0.0` for container connections
- Use runtime-specific host aliases for `xdebug.client_host`:
  - Docker: `host.docker.internal`
  - Podman: `host.containers.internal`
  - Kubectl: `host.docker.internal` (requires manual network configuration)
- Maintain backward compatibility with local execution

See [tests/docker/README.md](tests/docker/README.md) for detailed setup instructions.

---

**Stop debugging blind. Give AI the power of Forward Trace.**

*Transform your PHP debugging from guesswork to intelligence.*

*Debug once, analyze anywhere - with schema-validated JSON that any AI can understand.*
