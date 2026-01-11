---
name: xdebug
description: PHP debugging and analysis tools using Xdebug. Use when asked to trace, debug, profile, or analyze coverage of PHP code.
---

# Xdebug MCP Tools

Non-invasive PHP debugging and analysis tools. No var_dump() or code modification needed.

## Tool Paths

Tools are installed globally via composer. Use absolute paths:

| Tool | Path |
|------|------|
| xtrace | `~/.composer/vendor/bin/xtrace` |
| xstep | `~/.composer/vendor/bin/xstep` |
| xprofile | `~/.composer/vendor/bin/xprofile` |
| xcoverage | `~/.composer/vendor/bin/xcoverage` |
| xback | `~/.composer/vendor/bin/xback` |

## Tool Selection Guide

| User Request | Tool |
|--------------|------|
| Trace, execution flow, show function calls | xtrace |
| Step debugging, breakpoints, inspect variables, track variable changes | xstep |
| Profile, performance, bottlenecks, slow code | xprofile |
| Coverage, test coverage, which lines tested | xcoverage |
| Backtrace, call stack, how did we get here | xback |

### "Trace" Ambiguity

The word "trace" can mean different things:
- **Forward Trace** → `xtrace` (records execution from start to finish)
- **Backtrace / Stack Trace** → `xback` (shows call stack at a point)
- **Step through / Debug** → `xstep` (interactive with breakpoints)

---

## xtrace - Forward Trace (Full)

Trace execution forward from start to finish. Captures complete execution flow, function calls, parameters, and timing data.

**Output**: JSON with `$schema` URL for semantic details and AI analysis strategies.
**Key fields**: `{lines, functions, max_depth, db_queries}`

```bash
~/.composer/vendor/bin/xtrace [--json] [--context=TEXT] [--include-vendor=PATTERNS] -- command
```

### Examples

```bash
~/.composer/vendor/bin/xtrace --context="Debug login" -- php login.php
~/.composer/vendor/bin/xtrace --context="Test analysis" -- vendor/bin/phpunit tests/UserTest.php
~/.composer/vendor/bin/xtrace --include-vendor="bear/*" -- php app.php
```

### When to Use

- "Trace this code"
- "Show execution flow"
- "What functions are called?"
- General PHP debugging (default choice)

---

## xstep - Forward Trace (Step-by-Step)

Stop at breakpoint, step forward N times, record variable changes at each step. See how variable values affect branching ("this variable was X, so it went into this branch").

**Output**: JSON with `$schema` URL for semantic details.
**Key fields**: `{breaks: [{step, location, variables}]}` - Variables show diff only (changed values).

```bash
~/.composer/vendor/bin/xstep --break=file.php:line --steps=N [--context=TEXT] [--include-vendor=PATTERNS] -- command
```

### Options

- `--break=file.php:line` - Single breakpoint location
- `--break=file.php:line:condition` - Conditional (e.g., `$user==null`)
- `--steps=N` - Step forward N times from breakpoint

### Examples

```bash
# Step 20 times from line 42
~/.composer/vendor/bin/xstep --break="user.php:42" --steps=20 --context="Track auth" -- php user.php

# Conditional breakpoint
~/.composer/vendor/bin/xstep --break="user.php:15:\$id==null" --steps=10 -- php user.php
```

### Workflow Tips

- **Need more steps?** Set new breakpoint at where you stopped, run again
- **Too much variable info?** Use xtrace first to see flow, then xstep for details

### When to Use

- "Track how variable changes step by step"
- "Why did it branch this way?"
- "What's the variable value at line X?"

---

## xprofile - Performance Profiler

Identify performance bottlenecks with precision data.

**Output**: JSON with `$schema` URL for semantic details and AI analysis strategies.
**Key fields**: `{time_ms, memory_mb, bottlenecks}` - Bottlenecks auto-identified.

```bash
~/.composer/vendor/bin/xprofile [--json] [--context=TEXT] [--include-vendor=PATTERNS] -- command
```

### Examples

```bash
~/.composer/vendor/bin/xprofile --context="Optimize processing" -- php process.php
~/.composer/vendor/bin/xprofile --json -- php script.php
~/.composer/vendor/bin/xprofile --include-vendor="doctrine/*" -- php app.php
```

### When to Use

- "Profile this code"
- "Find bottlenecks"
- "Why is this slow?"
- "Memory usage analysis"

---

## xcoverage - Code Coverage

Collect code coverage data for PHPUnit or any PHP script. Shows only uncovered lines (compact output).

**Output**: JSON with `$schema` URL for semantic details.
**Key fields**: `{summary: {coverage_percent, covered_lines, uncovered_lines}, uncovered: {file: [lines]}}`

```bash
~/.composer/vendor/bin/xcoverage [--include-vendor=PATTERNS] -- command
~/.composer/vendor/bin/xcoverage -- vendor/bin/phpunit        # PHPUnit
~/.composer/vendor/bin/xcoverage -- php script.php            # Any PHP script
```

### Examples

```bash
~/.composer/vendor/bin/xcoverage -- vendor/bin/phpunit
~/.composer/vendor/bin/xcoverage --include-vendor="bear/*,ray/di" -- vendor/bin/phpunit
~/.composer/vendor/bin/xcoverage -- vendor/bin/phpunit --filter testMethod
```

### When to Use

- "Check test coverage"
- "Which lines are tested?"
- "Find untested code"

---

## xback - Backtrace Capture

Get call stack (backtrace) at a specific line. Shows "who called this?" - the chain of function calls that led to this point.

**Output**: JSON with `$schema` URL for semantic details.
**Key fields**: `{backtrace: [{file, line, function, args}]}`

```bash
~/.composer/vendor/bin/xback [--break=SPEC] [--depth=N] [--context=TEXT] -- command
```

### Examples

```bash
~/.composer/vendor/bin/xback -- php script.php                    # First line
~/.composer/vendor/bin/xback --break=app.php:50 -- php app.php    # At breakpoint
~/.composer/vendor/bin/xback --depth=20 -- php main.php           # Deep trace
```

### When to Use

- "Get backtrace at line X"
- "Show call stack"
- "How did execution reach this line?"

---

## Common Options

| Option | Description |
|--------|-------------|
| `--json` | AI-optimized JSON output |
| `--context=TEXT` | Add description for AI analysis |
| `--include-vendor=PATTERNS` | Include vendor packages |

### Vendor Filtering

By default, vendor code is excluded to focus on your code. Use `--include-vendor` when needed:

```bash
--include-vendor=bear/*           # Include specific framework
--include-vendor=bear/*,ray/di    # Multiple packages
--include-vendor=*/*              # Include all vendor (framework debugging)
```

## JSON Schemas

- xtrace: https://koriym.github.io/xdebug-mcp/schemas/xtrace.json
- xstep: https://koriym.github.io/xdebug-mcp/schemas/xstep.json
- xprofile: https://koriym.github.io/xdebug-mcp/schemas/xprofile.json
- xcoverage: https://koriym.github.io/xdebug-mcp/schemas/xcoverage.json
- xback: https://koriym.github.io/xdebug-mcp/schemas/xback.json