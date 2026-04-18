# Architecture

Developer-facing reference for the internals of `xdebug-mcp`. `CLAUDE.md` is intentionally short and targeted at AI harnesses; this file collects the architectural detail that used to live there.

## Purpose

A universal PHP Xdebug MCP (Model Context Protocol) server and CLI suite that lets AI assistants perform runtime analysis of any PHP application without modifying its source:

- **Forward tracing** of execution flow (`xtrace`)
- **Interactive step debugging** over DBGp (`xstep`, `xrepl`)
- **Performance profiling** from Cachegrind output (`xprofile`)
- **Code coverage** collection and reporting (`xcoverage`)
- **Backtraces** at a specified point (`xback`)

All five tools expose a common JSON schema (`https://koriym.github.io/xdebug-mcp/schemas/<tool>.json`) and the same five tools are surfaced through the MCP server (`bin/xdebug-mcp`) and the bundled skill (`skills/xdebug/SKILL.md`).

## Design principles

- **Non-invasive**: No `var_dump`/`print_r`/`echo` additions. Runtime data is captured via Xdebug, not by mutating source.
- **Universal**: Works with any PHP codebase, framework, or test runner. No assumptions about project layout beyond "PHP on disk".
- **AI-first output**: JSON is compact, schema-documented, and includes `--context` metadata so a trace file is self-explanatory weeks later or across AI systems.
- **Single source of truth for tool metadata**: `ToolDefinition` produces both the MCP tool descriptor and the prompt descriptor, so the two surfaces cannot drift.

## Execution model

Each CLI entry point (`bin/xtrace`, `bin/xstep`, etc.) is a thin wrapper that:

1. Locates a PHP binary with a compatible Xdebug via `XdebugFinder`.
2. Builds an Xdebug-enabled command line via `XdebugRunner` (or, for profile/trace, the `XdebugCommandExecutor` helper) with the appropriate `-dxdebug.mode=...`, `XDEBUG_TRIGGER`, and `XDEBUG_SESSION=xdebug-mcp` flags.
3. Executes the target command, then parses the raw Xdebug artifact (`.xt` trace, Cachegrind profile, coverage array) and emits JSON against the schema.

`xstep` / `xrepl` / `xback` additionally need an interactive DBGp session. `DebugServer` listens on port **9004**, Xdebug (the target script) connects back as a client, and `DebugServer` drives it via DBGp over a socket. `DbgpClient` encapsulates the wire protocol (framing, transaction IDs, XML parsing).

### Port 9004 and session keys

| Role | Session key |
|------|-------------|
| IDE (PhpStorm) | `PHPSTORM` |
| IDE (VS Code) | `vscode` |
| IDE (NetBeans) | `netbeans` |
| This tool | `xdebug-mcp` |

- IDEs default to port **9003**; we use **9004** so both can run simultaneously.
- If you do share a single port, the `XDEBUG_SESSION=xdebug-mcp` key isolates our sessions from IDE sessions.
- Emergency cleanup only kills `XDEBUG_SESSION=xdebug-mcp` processes and leaves IDE sessions intact.

## Component layout

| File | Role |
|------|------|
| `src/McpServer.php` | MCP JSON-RPC server; registers the 5 tools via `ToolDefinition` and shells out to `bin/*`. |
| `src/DebugServer.php` | DBGp server on port 9004 for `xstep`/`xrepl`/`xback`; owns socket lifecycle. |
| `src/DbgpClient.php` | DBGp protocol client (framing, transaction IDs, XML response parsing). Does **not** own the socket. |
| `src/DebugResultFormatter.php` | Builds the `xstep` JSON payload and emits JSON or human-readable output. |
| `src/ToolDefinition.php` | Single source of truth for MCP tool and prompt metadata (`toMcpTool()` / `toPromptDefinition()`). |
| `src/ClaudeTraceAnalyzer.php` | Optional adapter that invokes the local `claude` CLI to analyze a trace file. Trace-file reads are restricted to `xdebug.output_dir` / system temp. |
| `src/XdebugCommandExecutor.php` | Shared helpers: `buildPhpCommand()`, `executeAndAssertSuccess()`, `findLatestArtifact()` (throws) / `findLatestArtifactOrNull()`. Used by `XdebugTracer`, `XdebugProfiler`, and `XdebugRunner`. |
| `src/XdebugRunner.php` | Command assembly and execution for `xtrace` / `xprofile`, including Docker/Podman/kubectl detection. |
| `src/XdebugFinder.php` | Locate PHP + Xdebug (supports cross-version debugging). |
| `src/XdebugTracer.php` / `XdebugProfiler.php` | Parsers for `.xt` trace and Cachegrind profile output. |
| `src/CLIParamsNormalizer.php` | Shared argv parsing for all `bin/*` tools. |
| `src/TraceSubscriber.php` / `src/TraceExtension.php` / `src/TraceHelper.php` | Trace event dispatch and helpers. |
| `src/ContainerHelper.php` | Detects `docker` / `podman` / `kubectl` commands so targets inside containers are handled correctly. |
| `bin/xdebug-mcp` | MCP entry point (instantiates `McpServer`). |
| `bin/debug-server-mcp` | Standalone DBGp server launcher. |

Namespace: `Koriym\XdebugMcp\` (PSR-4 → `src/`).

## MCP tool surface

`McpServer::initializeTools()` exposes exactly these 5 tools — each shells out to the matching `bin/` script:

- `xtrace` — forward execution tracing
- `xstep` — interactive step debugging / variable capture
- `xprofile` — performance profiling
- `xcoverage` — code coverage
- `xback` — backtrace at a breakpoint or first executable line

The same `ToolDefinition` objects drive both `tools/list` (via `toMcpTool()`) and `prompts/list` (via `toPromptDefinition()`). When adding or renaming a tool, update `initializeTools()`, the matching `bin/*` script, `skills/xdebug/SKILL.md`, and `README.md` together.

Tool Search optimization: when the MCP client's context budget forces dynamic tool loading, the `initialize` response includes `instructions` text that helps the client's tool-search match relevant tools from natural-language debugging queries.

## DBGp session lifecycle (xstep / xback)

Because Xdebug acts as the *client* and our process as the *server*, ordering matters when the tools are driven by hand:

1. **Startup** — warn (not fail) if another `XDEBUG_SESSION=xdebug-mcp` process is already running.
2. **Listen** — `DebugServer` opens a socket on port 9004 and waits.
3. **Target script starts** — Xdebug connects back, sends the `init` packet. `DbgpClient` parses it and assigns a transaction ID stream.
4. **Drive** — breakpoints, step commands, variable queries are issued as DBGp commands and responses are framed as `<length>\0<xml>\0`.
5. **Normal exit** — `DbgpClient::sendCommand('detach')` then the caller closes the socket.
6. **Emergency exit** — `register_shutdown_function([$this, 'emergencyCleanup'])` reaps only `XDEBUG_SESSION=xdebug-mcp` processes.

The `bin/xstep` wrapper handles all of this so end users never see step 1–2; only worry about ordering when wiring things up by hand.

## Coverage flow (`xcoverage`)

- Default mode: `phpunit --coverage-clover` — respects `@codeCoverageIgnore` and `phpunit.xml` settings.
- `--raw` mode: uses Xdebug's `xdebug_start_code_coverage` / `xdebug_get_code_coverage` directly for any PHP script.

Raw output schema (per file line map):

```json
{
  "$schema": "https://koriym.github.io/xdebug-mcp/schemas/test-coverage.json",
  "coverage": {
    "/path/to/file.php": {"10": 1, "11": -1, "12": 1}
  }
}
```

Values follow Xdebug conventions: `1` executed, `-1` not executed, `-2` dead code.

## Trace file format (`.xt`, `trace_format=1`)

| Column | Meaning |
|--------|---------|
| Level | Call depth |
| Function ID | Unique id for the call |
| Time index | Seconds since script start |
| Memory | Bytes used |
| Function name | User function, method, or internal builtin |
| User defined | `1` = user, `0` = internal |
| Include filename | For `include`/`require` |
| Filename | Source file of the call site |
| Line number | Source line |
| Parameters | Argument values when `collect_params` is set |

`XdebugTracer` parses this streamwise and emits an AI-friendly summary (`file`, `lines`, `functions`, `max_depth`, `db_queries`).

## Vendor filtering

`--include-vendor=<pattern>[,<pattern>...]` controls which `vendor/*/` subtrees are retained in traces:

- Omitted → all of `vendor/` is excluded (default; focus on application code).
- `*/*` → include all vendor packages (studying framework behavior).
- `bear/*,symfony/console` → include only listed packages (debugging specific dependencies).

Implementation lives in `src/prepend_filter.php`, wired up via `auto_prepend_file`.

## Testing layout

- `tests/Unit/` — pure unit tests (no Xdebug required).
- `tests/Integration/` — end-to-end MCP protocol and CLI tests.
- `tests/fake/` — fake `McpServer` / trace data for demos without a real Xdebug.
- `tests/fixtures/` — sample PHP scripts targeted by the debugger/tracer.

Helper classes under test include `DbgpClient`, `DebugResultFormatter`, `ClaudeTraceAnalyzer`, `McpServer`, `DebugServer`, `CLIParamsNormalizer`, `ContainerHelper`, `XdebugFinder`, and `XdebugRunner`.

## Configuration reference

For day-to-day use the CLI tools pass Xdebug settings via `-d` flags, so nothing in `php.ini` is strictly required. A fully enabled `php.ini` for manual experimentation:

```ini
zend_extension=xdebug
xdebug.mode=debug,profile,coverage,trace
xdebug.start_with_request=trigger
xdebug.client_host=127.0.0.1
xdebug.client_port=9004
xdebug.output_dir=/tmp
xdebug.trace_format=1
xdebug.use_compression=0
```

Environment variables:

- `MCP_DEBUG=1` — verbose stderr logging from `McpServer`.
- `XDEBUG_SESSION=xdebug-mcp` — isolates our sessions from IDE sessions on shared ports.
- `XDEBUG_TRIGGER=TRACE` — used by `xtrace` to activate trace mode on a running script.
- `XDEBUG_RUNNER_DEBUG=1` — print the assembled command line before executing.

## Release process

1. Update `CHANGELOG.md` with the new version.
2. Commit.
3. `gh release create` — only when explicitly requested.
