# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Universal PHP Xdebug tooling for AI-driven debugging.** Five CLI tools (`xtrace`, `xstep`, `xprofile`, `xcoverage`, `xback`) each run a target command under Xdebug and emit JSON that an AI can analyze. The same five tools are also exposed via an MCP server (`bin/xdebug-mcp`) and as a bundled skill (`skills/xdebug/SKILL.md`).

Design principle: **no var_dump, no code modification.** AI assistants should drive these tools instead of asking users to instrument source code.

## Architecture

For the full developer-facing architecture reference (component layout, DBGp lifecycle, coverage/trace formats, configuration), see [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md). The summary below is the minimum an AI harness needs.

### Execution model

Each CLI tool is a thin wrapper that:
1. Locates a PHP binary with a compatible Xdebug (`XdebugFinder`).
2. Spawns the target command with the right `-dxdebug.mode=...` / `XDEBUG_TRIGGER` / `XDEBUG_SESSION=xdebug-mcp` flags (`XdebugRunner`).
3. Post-processes Xdebug's raw output (trace `.xt`, Cachegrind, coverage array) into the JSON schema at `https://koriym.github.io/xdebug-mcp/schemas/<tool>.json`.

`xstep` / `xback` additionally need an interactive DBGp session: `DebugServer` listens on port **9004**, Xdebug (the script) connects back as a client, and the server drives it via the DBGp protocol over sockets.

### Why port 9004, why session key

- IDEs (PhpStorm, VS Code) default to **9003** — we use **9004** so both can run simultaneously.
- On shared ports, the `XDEBUG_SESSION=xdebug-mcp` key isolates our sessions from IDE sessions (`PHPSTORM`, `vscode`, etc.).

### Key source files

| File | Role |
|------|------|
| `src/McpServer.php` | MCP JSON-RPC server; registers the 5 tools and shells out to `bin/*` |
| `src/DebugServer.php` | DBGp server on port 9004 for `xstep`/`xback` |
| `src/XdebugRunner.php` | Command assembly for running targets under Xdebug |
| `src/XdebugFinder.php` | Locate PHP+Xdebug binary (supports cross-version debugging) |
| `src/XdebugTracer.php` / `XdebugProfiler.php` | `.xt` and Cachegrind parsers |
| `src/CLIParamsNormalizer.php` | Shared argv parsing for all `bin/*` tools |
| `src/TraceSubscriber.php` | Dispatch trace events to downstream parsers |
| `bin/xdebug-mcp` | MCP entry point (calls `McpServer`) |
| `bin/debug-server-mcp` | Standalone DBGp server launcher |

Namespace is `Koriym\XdebugMcp\` (PSR-4 → `src/`).

### MCP tool surface

`McpServer::initializeTools()` exposes exactly these 5 tools — each one shells out to the matching `bin/` script. If you add or rename a tool, update `initializeTools()`, the relevant `bin/*` script, `skills/xdebug/SKILL.md`, and `README.md` together.

## Common Commands

### Setup and environment check

```bash
composer install
./bin/check-env                  # Verify Xdebug install + php.ini
```

### Tests

```bash
composer test                    # Full phpunit run
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Integration
vendor/bin/phpunit --filter testSomething
composer test-json               # End-to-end MCP protocol tests via bin/test-json
```

### Lint / static analysis

```bash
composer cs                      # phpcs (doctrine/coding-standard)
composer cs-fix                  # phpcbf
composer sa                      # phpstan
composer psalm
composer tests                   # cs + sa + phpunit (CI-equivalent)
```

### Running the tools

```bash
./bin/xtrace    --context="why is X null" -- php script.php
./bin/xstep     --break="script.php:42"   --steps=20 -- php script.php
./bin/xprofile  --json                     -- php script.php
./bin/xcoverage                            -- vendor/bin/phpunit
./bin/xback     --break="app.php:50"       -- php app.php
./bin/xrepl     --break="script.php:42"    -- php script.php   # interactive mode of xstep
```

Demo targets live in `demo/` (`buggy.php`, `slow.php`, `coverage.php`).

### Running the MCP server directly

```bash
./bin/xdebug-mcp                                          # stdin/stdout JSON-RPC
MCP_DEBUG=1 ./bin/xdebug-mcp                              # debug logging to stderr
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | ./bin/xdebug-mcp
```

## PHP / Xdebug requirements

- PHP `^8.1` (runs the tooling itself; target scripts may be older — see README "Debugging Legacy PHP").
- Extensions: `ext-sockets`, `ext-xml`.
- Xdebug 3.x installed on whatever PHP binary you hand to `-- php ...`. The tool enables `debug`/`trace`/`profile`/`coverage` per-invocation, so **keep Xdebug disabled in php.ini for daily use** — it loads on demand.

For interactive step debugging, Xdebug's client port must be 9004:

```ini
xdebug.client_port=9004
```

(The tools pass this via `-d` when they launch the target, so php.ini changes are only needed if you're running scripts by hand.)

## Natural-language → tool mapping

When a user describes a PHP problem, pick the tool from the intent, not the literal word "trace":

| User says | Tool |
|-----------|------|
| "trace", "execution flow", "what gets called" | `xtrace` |
| "debug", "breakpoint", "watch `$x`", "step through" | `xstep` |
| "slow", "bottleneck", "profile", "memory" | `xprofile` |
| "coverage", "untested lines" | `xcoverage` |
| "backtrace", "call stack", "how did we get here" | `xback` |

"Trace" is ambiguous: forward trace → `xtrace`; call stack at a point → `xback`; interactive → `xstep`.

## Working conventions

- **Never suggest `var_dump`/`print_r`/`echo` for investigation.** Run a tool instead. The JSON output of `xtrace`/`xstep` already contains the variable/parameter values that `var_dump` would have shown.
- **Always pass `--context="..."`** when producing data for later AI analysis — the JSON is self-describing only if context is set.
- Default excludes `vendor/`. Use `--include-vendor="bear/*"` (or `"*/*"`) only when the bug is genuinely in a dependency.
- `xstep` requires `DebugServer` to be listening first. The `bin/xstep` wrapper handles this; only worry about ordering when wiring things up by hand.

## Release process

1. Update `CHANGELOG.md`.
2. Commit.
3. `gh release create` — only when the user explicitly asks for a release.
