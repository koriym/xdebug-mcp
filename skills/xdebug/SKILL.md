---
name: xdebug
description: PHP debugging and analysis tools using Xdebug. Use when asked to trace, debug, profile, analyze coverage, or compare executions of PHP code. Trigger phrases include "trace this function", "profile this code", "check coverage", "debug PHP", "set breakpoint", "find the bottleneck", "why is this slow", "compare two runs", "トレース", "プロファイル", "カバレッジ", "比較".
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
| xcompare | `~/.composer/vendor/bin/xcompare` |

## Tool Selection Guide

| User Request | Tool |
|--------------|------|
| Trace, execution flow, show function calls | xtrace |
| Step debugging, breakpoints, inspect variables, track variable changes | xstep |
| Profile, performance, bottlenecks, slow code | xprofile |
| Coverage, test coverage, which lines tested | xcoverage |
| Backtrace, call stack, how did we get here | xback |
| Compare variable states across two runs, normal vs edge case, compare with git branch | xcompare |

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

**Output**: Slim, deduplicated JSON with a `$schema` URL — fields you might expect inline can live elsewhere, so **read the linked schema to interpret the structure and reconstruct variable state**. The schema is the source of truth; do not infer the format from examples.

```bash
~/.composer/vendor/bin/xstep --break=file.php:line --steps=N [--context=TEXT] [--include-vendor=PATTERNS] -- command
```

### Options

- `--break=file.php:line` - Single breakpoint location
- `--break=file.php:line:condition` - Conditional (e.g., `$user==null`)
- `--steps=N` - Step forward N times from breakpoint
- `--watch=EXPR` - Only record steps when expression value changes (can specify multiple times)

### Examples

```bash
# Step 20 times from line 42
~/.composer/vendor/bin/xstep --break="user.php:42" --steps=20 --context="Track auth" -- php user.php

# Conditional breakpoint
~/.composer/vendor/bin/xstep --break="user.php:15:\$id==null" --steps=10 -- php user.php

# Watch variable changes in loop
~/.composer/vendor/bin/xstep --break="loop.php:10" --watch="\$i" --steps=100 -- php loop.php

# Multiple watches
~/.composer/vendor/bin/xstep --break="app.php:25" --watch="\$user->getStatus()" --watch="count(\$items)" --steps=50 -- php app.php
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

**Output**: JSON with a `schema` URL. `time_ms`/`memory_mb` are measured from the cachegrind summary; `bottlenecks` is a structured array. Read the linked schema for the field shapes — don't infer the format here.
**Key fields**: `{time_ms, memory_mb, bottlenecks}`

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

## xcompare - Breakpoint Comparison

Compare variable states at the same breakpoint across two different executions. Useful for normal vs edge case inputs, success vs failure, or current code vs another git ref.

**Output**: JSON with `$schema` URL. `diff` contains `{changed, unchanged, only_in_a, only_in_b}` plus `analysis_hints`.
**Key fields**: `{breakpoint, run_a, run_b, diff, analysis_hints}`

```bash
# Mode 1: Compare two commands
~/.composer/vendor/bin/xcompare --break=FILE:LINE --run-a="CMD" --run-b="CMD" [--context=TEXT]

# Mode 2: Compare with another git ref
~/.composer/vendor/bin/xcompare --break=FILE:LINE --run="CMD" --compare-with=REF [--context=TEXT]
```

### Options

- `--label-a` / `--label-b` - Labels for run A/B (default: command or 'HEAD'/ref name)
- `--steps=N` - Steps to record after breakpoint (default: 1)
- `--include-vendor` - Include vendor packages in trace

### Examples

```bash
# Normal vs edge case input
~/.composer/vendor/bin/xcompare --break=src/Calculator.php:25 \
  --run-a="php calc.php 10" --run-b="php calc.php 0" \
  --context="Compare division behavior with normal vs zero input"

# Authentication success vs failure
~/.composer/vendor/bin/xcompare --break=src/Auth.php:42 \
  --run-a="php login.php valid_user" --run-b="php login.php invalid_user" \
  --context="Compare authentication flow"

# Current code vs main branch
~/.composer/vendor/bin/xcompare --break=src/Calculator.php:25 \
  --run="php calc.php 10" --compare-with=main \
  --context="Compare current implementation vs main"
```

**Note**: `--run-a`, `--run-b`, and `--run` are executed through the shell — only pass trusted input.

### When to Use

- "Compare how different inputs affect variable states"
- "Debug edge cases by comparing normal vs problematic inputs"
- "Compare current code vs another git branch/commit"

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
--include-vendor="bear/*"           # Include specific framework
--include-vendor="bear/*,ray/di"    # Multiple packages
--include-vendor="*/*"              # Include all vendor (framework debugging)
```

## JSON Schemas

- xtrace: https://koriym.github.io/xdebug-mcp/schemas/xtrace.json
- xstep: https://koriym.github.io/xdebug-mcp/schemas/xstep.json
- xprofile: https://koriym.github.io/xdebug-mcp/schemas/xprofile.json
- xcoverage: https://koriym.github.io/xdebug-mcp/schemas/xcoverage.json
- xback: https://koriym.github.io/xdebug-mcp/schemas/xback.json
- xcompare: https://koriym.github.io/xdebug-mcp/schemas/xcompare.json