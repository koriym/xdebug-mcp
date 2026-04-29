# Xdebug MCP

<img width="256" alt="xdebug-mcp" src="docs/images/logo.jpeg" />

**Debug PHP with Runtime Data — No var_dump(), No Guesswork**

Trace-based debugging for PHP, built on Xdebug. Drive the tools from the CLI, or let an AI assistant run the core tools through plugin, Skill, or MCP integration.

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

When connected through the plugin, Skill, or an MCP-capable client, the AI can select the appropriate tool, execute it, and analyze the results.

## Requirements

- PHP 8.2+
- [Xdebug 3.x](https://xdebug.org/docs/install) extension (installed, but **not** enabled by default)
- Optional: an AI assistant (Claude Code plugin, Codex Skill, or any MCP-capable client)

> **💡 Performance Tip:** Keep Xdebug disabled in php.ini for daily use. This tool loads Xdebug on-demand only when needed.

## Quick Start

### 1. Install

```bash
composer global require koriym/xdebug-mcp
```

### 2. Verify Xdebug

```bash
"$(composer global config bin-dir --absolute --quiet)/check-env"
```

### 3. (Optional) Wire up an AI assistant

**Claude Code** — install the plugin:

```text
/plugin marketplace add koriym/xdebug-mcp
/plugin install xdebug@xdebug-mcp
```

**Codex CLI** — install the Skill:

```bash
git clone --depth 1 https://github.com/koriym/xdebug-mcp.git /tmp/xdebug-mcp
mkdir -p ~/.codex/skills
cp -r /tmp/xdebug-mcp/skills/xdebug ~/.codex/skills/
```

**Any MCP-capable client** — see [MCP Configuration](#mcp-configuration) below.

You can skip this step entirely and use the CLI tools directly.

### 4. Try it

```text
# Download demo files
git clone --depth 1 https://github.com/koriym/xdebug-mcp.git /tmp/xdebug-demo

# Ask Claude to debug
"Debug /tmp/xdebug-demo/demo/buggy.php and find the bugs"
```

Or use the skill directly:
```text
/xdebug
```

## Try CLI Tools

Run the demo examples to see each tool in action:

```bash
# Debug buggy code with breakpoints (JSON output)
./bin/xstep --break="demo/buggy.php:22" -- php demo/buggy.php

# Trace execution flow
./bin/xtrace --context="Debug demo" -- php demo/buggy.php

# Profile performance bottlenecks
./bin/xprofile --json -- php demo/slow.php

# Analyze code coverage (raw mode for plain PHP scripts)
./bin/xcoverage --raw -- php demo/coverage.php

# Get stack trace at breakpoint
./bin/xback --break="demo/buggy.php:44" -- php demo/buggy.php

# Compare variable states with different inputs
./bin/xcompare --break="demo/buggy.php:22" --run-a="php demo/buggy.php 10" --run-b="php demo/buggy.php 0"

# Compare current code vs another branch
./bin/xcompare --break="src/calc.php:25" --run="php src/calc.php 10" --compare-with=main
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
| `xcompare` | Compare variable states across two runs | "Compare input 10 vs 0" or "Compare with main branch" |

All tools in this table are available as CLI commands. MCP currently exposes the core one-shot analysis tools: `xtrace`, `xprofile`, `xstep`, `xcoverage`, and `xback`. `xcompare` is CLI-only.

## CLI Usage

For direct command-line usage without AI:

```bash
# Trace execution
xtrace -- php script.php

# Profile performance
xprofile -- php api.php

# Debug with conditional breakpoint
xstep --break='script.php:42:$user==null' -- php script.php

# Pretty-print JSON, truncate long values, limit nesting depth
xstep --break='script.php:42' --pretty --max-value-bytes=200 --max-depth=3 -- php script.php

# Code coverage
xcoverage -- vendor/bin/phpunit

# Stack trace at breakpoint
xback --break='app.php:50' -- php app.php

# Compare variables at breakpoint with different inputs
xcompare --break='calc.php:25' --run-a='php calc.php 10' --run-b='php calc.php 0'

# Compare current code vs main branch
xcompare --break='calc.php:25' --run='php calc.php 10' --compare-with=main
```

Run `--help` on any tool for detailed options.

> **Note on `xcompare` commands:** the `--run-a`, `--run-b`, and `--run` values are executed through the shell so that quoting, redirection, and environment variables behave as users expect. Only pass trusted input to these options.
>
> **Note on `--steps`:** `xcompare` defaults to `--steps=1`, since the comparison only needs the variable snapshot at the breakpoint. Pass `--steps=N` explicitly (e.g. `--steps=100`) if you want to see how execution diverges between the two runs after the break.

## Schema-Backed JSON Output

Every tool emits JSON with a `$schema` URL, so the output is machine-verifiable and self-describing — no log-string parsing needed:

```json
{
  "$schema": "https://koriym.github.io/xdebug-mcp/schemas/xstep.json",
  "breaks": [
    {
      "location": {"file": "demo/buggy.php", "line": 22},
      "variables": {"$user": "NULL", "$id": "42"}
    }
  ],
  "trace": { "...": "..." }
}
```

Schemas live under [docs/schemas/](docs/schemas/). AI assistants — and humans — can verify exactly what each tool captured.

## MCP Configuration

For any MCP-capable client, register `xdebug-mcp` in that client's MCP config (e.g. `.mcp.json`). MCP exposes `xtrace`, `xprofile`, `xstep`, `xcoverage`, and `xback`:

```json
{
  "mcpServers": {
    "xdebug": {
      "command": "php",
      "args": ["/ABSOLUTE/PATH/TO/xdebug-mcp"]
    }
  }
}
```

Find the path with `which xdebug-mcp`. Restart your client after editing the config.

## Interactive REPL

For hands-on debugging without AI, use the interactive debugger (`xrepl`):

```bash
xrepl --break="script.php:42" -- php script.php
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
xstep --break="/app/script.php:42" -- \
  docker compose run --rm php php /app/script.php

xtrace -- docker compose run --rm php php /app/script.php
```

The tools automatically detect container runtime and configure Xdebug networking.

See [tests/docker/README.md](tests/docker/README.md) for details.

## Debugging Legacy PHP (7.x / 5.x)

While xdebug-mcp itself requires PHP 8.2+, it can debug older PHP versions as long as a compatible Xdebug 3.x release is installed on the target PHP. Simply specify the target PHP binary after `--`:

```bash
# Debug PHP 7.2 code
xtrace -- /opt/homebrew/opt/php@7.2/bin/php legacy_app.php
xprofile -- /opt/homebrew/opt/php@7.2/bin/php legacy_app.php
xstep --break="legacy_app.php:30" -- /opt/homebrew/opt/php@7.2/bin/php legacy_app.php
```

The tool runs on your modern PHP while the target script executes on the specified PHP binary — provided the specified PHP has a compatible Xdebug 3.x installed. No Docker required. Check the compatibility table below before trying older PHP binaries.

### Xdebug 3.x Compatibility

| Xdebug | Supported PHP |
| -------- | --------------- |
| 3.0-3.1 | PHP 5.4 - 8.0 |
| 3.2 | PHP 5.6 - 8.3 |
| 3.3 | PHP 7.0 - 8.4 |
| 3.4 | PHP 7.4 - 8.5 |

See [Xdebug compatibility](https://xdebug.org/docs/compat) for details.

## For Developers

See [tests/ai/README.md](tests/ai/README.md) for tool discoverability testing.

## AI Native Design

This tool is designed specifically for AI consumption, not adapted from human interfaces.

| Debugger for Humans | AI Native (this tool) |
|---------------------|----------------------|
| Step-by-step interaction | One-shot batch execution |
| Session management required | Stateless CLI |
| Multiple tool calls | Single command, complete data |
| Manual log placement | Automatic full trace |

**Why it matters:**
- **Fewer tokens**: One command returns all data vs. many back-and-forth calls
- **No session errors**: Stateless design eliminates timeout/connection issues
- **Comprehensive data**: `xtrace` captures everything; AI filters what it needs

## See Also

Looking for a different approach? [kpanuragh/xdebug-mcp](https://github.com/kpanuragh/xdebug-mcp) offers 41 MCP tools with session-based interactive debugging — ideal if you prefer step-by-step control.

## Why "xdebug-mcp"?

This project started as an MCP (Model Context Protocol) server for AI-powered PHP debugging. MCP is still supported for any MCP-capable client, but the CLI is now the primary interface. The core MCP tools (`xstep`, `xtrace`, `xprofile`, `xcoverage`, `xback`) also work as standalone CLI commands; `xcompare` and `xrepl` are CLI-only tools.

## Resources

- [Troubleshooting](https://koriym.github.io/xdebug-mcp/TROUBLESHOOTING) - Setup issues
- [Forward Trace Guide](https://koriym.github.io/xdebug-mcp/debug-guidelines/) - AI debugging methodology
- [llms.txt](https://koriym.github.io/xdebug-mcp/llms.txt) - LLM-readable documentation
- [Motivation](MOTIVATION.md) - Why we built this
- [Xdebug Docs](https://xdebug.org/docs/) - Official documentation

---

**Stop debugging blind. Just ask your AI.**
