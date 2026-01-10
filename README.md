# Xdebug MCP

<img width="256" alt="xdebug-mcp" src="docs/images/logo.jpeg" />

**Debug PHP with Natural Language — No var_dump(), No Guesswork**

AI-powered PHP debugging tools using Xdebug's runtime analysis. Works with Claude Code (plugin), Cursor, Windsurf (MCP), and CLI.

[![AI Native](https://img.shields.io/badge/AI_Native-YES-green)](https://github.com/koriym/xdebug-mcp)
[![Runtime Data](https://img.shields.io/badge/Runtime_Data-YES-green)](https://github.com/koriym/xdebug-mcp)
[![var_dump](https://img.shields.io/badge/var__dump()-NO-red)](https://github.com/koriym/xdebug-mcp)

---

## Natural Language Debugging

Just tell your AI assistant what you want:

**English:**
```text
"Debug script.php and find why $user is null at line 42"
"Profile api.php and find the performance bottleneck"
"Trace the authentication flow in login.php"
"Check test coverage for UserService"
```

**日本語:**
```text
"script.phpをデバッグして、42行目で$userがnullになる原因を調べて"
"api.phpのパフォーマンスボトルネックを見つけて"
"login.phpの認証フローをトレースして"
"UserServiceのテストカバレッジを確認して"
```

The AI automatically selects the appropriate tool, executes it, and analyzes the results.

## Requirements

- PHP 8.1+
- [Xdebug 3.x](https://xdebug.org/docs/install) extension (installed, but **not** enabled by default)
- AI assistant: Claude Code (plugin), Cursor/Windsurf (MCP), or CLI

> **💡 Performance Tip:** Keep Xdebug disabled in php.ini for daily use. This tool loads Xdebug on-demand only when needed.

## Quick Demo (Try Before Install)

```bash
git clone https://github.com/koriym/xdebug-mcp.git
cd xdebug-mcp
rm ./CLAUDE.md  # Remove to test fresh AI tool discovery
claude
> Read tests/ai/demo.md and follow the instructions.
```

## Quick Start

### 1. Install

```bash
composer global require koriym/xdebug-mcp
```

### 2. Verify Xdebug

```bash
~/.composer/vendor/bin/check-env
```

### 3. Setup AI Integration

**Claude Code:**
```text
/plugin marketplace https://github.com/koriym/xdebug-mcp
```

**Cursor / Windsurf:** See [MCP Configuration](#mcp-configuration) below.

### 4. Restart your AI assistant

Now ask your AI to debug PHP code.

**New to xdebug-mcp?** Try the [demo/](demo/) folder with sample buggy code, performance issues, and coverage examples.

## Try It Out

### 1. Check Xdebug Installation

**Recommended Setup:** Xdebug installed but disabled in php.ini (loaded on-demand for zero performance impact)

```bash
./bin/check-env
```

Expected output:
```text
✅ PHP 8.4.15
✅ Xdebug 3.5.0 (on-demand)
```

> ⚠️ If you see `(always loaded)`, disable Xdebug in php.ini. This tool loads it on-demand only when needed.

### 2. Try CLI Tools

Run the demo examples to see each tool in action:

```bash
# Debug buggy code with breakpoints (JSON output)
./bin/xstep --break="demo/buggy.php:22" -- php demo/buggy.php

# Trace execution flow
./bin/xtrace --context="Debug demo" -- php demo/buggy.php

# Profile performance bottlenecks
./bin/xprofile --json -- php demo/slow.php

# Analyze code coverage
./bin/xcoverage -- php demo/coverage.php

# Get stack trace at breakpoint
./bin/xback --break="demo/buggy.php:44" -- php demo/buggy.php
```

Each command outputs structured JSON data that AI can analyze to provide debugging insights.

## How It Works

```mermaid
flowchart LR
    A[You] -->|"Debug login.php"| B[AI Assistant]
    B -->|CLI / MCP| C[xtrace, xstep, ...]
    C -->|Runtime Analysis| D[Xdebug]
    D -->|JSON| C
    C -->|Results| B
    B -->|Explanation| A
```

**No var_dump(). No code modification. No guesswork.**

## Available Tools

| Tool | Purpose | Example Prompt |
|------|---------|----------------|
| `xstep` | Breakpoint debugging, variable inspection | "Stop at line 42 and show me the variables" |
| `xtrace` | Execution flow analysis | "Trace how the request flows through the app" |
| `xprofile` | Performance profiling | "Find what's making this endpoint slow" |
| `xcoverage` | Code coverage analysis | "Which lines aren't covered by tests?" |
| `xback` | Call stack at breakpoint | "Show me how we got to this error" |

## CLI Usage

For direct command-line usage without AI:

```bash
# Trace execution
xtrace -- php script.php

# Profile performance
xprofile -- php api.php

# Debug with conditional breakpoint
xstep --break='script.php:42:$user==null' --exit-on-break -- php script.php

# Code coverage
xcoverage -- vendor/bin/phpunit

# Stack trace at breakpoint
xback --break='app.php:50' -- php app.php
```

Run `--help` on any tool for detailed options.

## Claude Code Plugin

**Recommended for Claude Code users** - simpler than MCP configuration.

### Install via Plugin Marketplace

```text
/plugin marketplace https://github.com/koriym/xdebug-mcp
```

### Local Installation

```text
/plugin marketplace add ~/.composer/vendor/koriym/xdebug-mcp
```

## MCP Configuration

For Cursor, Windsurf, and other MCP-compatible tools.

Create `.mcp.json` in your project root:

```json
{
  "mcpServers": {
    "xdebug": {
      "command": "php",
      "args": ["/Users/YOUR_USERNAME/.composer/vendor/bin/xdebug-mcp"]
    }
  }
}
```

Find the correct path: `which xdebug-mcp`

## Interactive REPL

For hands-on debugging without AI, use the interactive debugger:

```bash
xstep -- php script.php
```

**Commands:**

| Command | Description |
|---------|-------------|
| `s` | Step into function |
| `o` | Step over line |
| `out` | Step out of function |
| `c` | Continue execution |
| `p <var>` | Print variable (e.g., `p $user`) |
| `bt` | Show backtrace |
| `l` | List source code |
| `q` | Quit debugger |

## Docker Support

All tools work with Docker, Podman, and Kubectl:

```bash
xstep --break="/app/script.php:42" --exit-on-break -- \
  docker compose run --rm php php /app/script.php

xtrace -- docker compose run --rm php php /app/script.php
```

The tools automatically detect container runtime and configure Xdebug networking.

See [tests/docker/README.md](tests/docker/README.md) for details.

## For Developers

See [tests/ai/README.md](tests/ai/README.md) for tool discoverability testing.

## Why "xdebug-mcp"?

This project started as an MCP (Model Context Protocol) server for AI-powered PHP debugging. While MCP remains supported for tools like Cursor and Windsurf, we now recommend the **plugin approach** for Claude Code users — it's simpler and requires no MCP configuration.

The CLI tools (`xstep`, `xtrace`, `xprofile`, `xcoverage`, `xback`) work independently of both MCP and plugins.

## Resources

- [Troubleshooting](https://koriym.github.io/xdebug-mcp/TROUBLESHOOTING) - Setup issues
- [Forward Trace Guide](https://koriym.github.io/xdebug-mcp/debug-guidelines/) - AI debugging methodology
- [Motivation](MOTIVATION.md) - Why we built this
- [Xdebug Docs](https://xdebug.org/docs/) - Official documentation

---

**Stop debugging blind. Just ask your AI.**
