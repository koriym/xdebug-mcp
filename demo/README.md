# Demo - Quick Start Guide

Try the Xdebug MCP tools with these sample scripts.

## Prerequisites

```bash
composer install
./bin/check-env
```

---

## 1. Debugging with xstep

Debug buggy code with step debugging. **Returns JSON for AI analysis.**

**Ask AI:**
> "Debug demo/buggy.php and find the bug"

**CLI:**
```bash
./bin/xstep --exit-on-break -- php demo/buggy.php

# With breakpoint and context
./bin/xstep --exit-on-break --break=demo/buggy.php:20 \
  --context="Debug sum calculation bug" -- php demo/buggy.php
```

**Output Format:**
- `--exit-on-break`: JSON output with execution trace and variable states
- Without option: Interactive terminal session

**What to look for:**
- Line 20: `$a - $b` should be `$a + $b`
- Line 28: Loop misses last element

---

## 2. Execution Tracing with xtrace

Trace the execution flow without stopping.

**Ask AI:**
> "Trace execution flow of demo/buggy.php"

**CLI:**
```bash
./bin/xtrace -- php demo/buggy.php

./bin/xtrace --context="Trace buggy calculation flow" -- php demo/buggy.php
```

**Output:**
- Function call hierarchy
- Parameter values at each call

---

## 3. Performance Profiling with xprofile

Find performance bottlenecks in slow code.

**Ask AI:**
> "Profile demo/slow.php and find bottlenecks"

**CLI:**
```bash
./bin/xprofile -- php demo/slow.php
```

**What to look for:**
- `inefficientSort()`: O(n^3) complexity with unnecessary inner loop
- `slowStringProcess()`: Redundant string operations
- `fibonacci()`: Exponential recursive calls

---

## 4. Code Coverage with xcoverage

Analyze which lines of code are executed.

**Ask AI:**
> "Analyze code coverage of demo/coverage.php"

**CLI:**
```bash
./bin/xcoverage -- php demo/coverage.php
```

**What to look for:**
- Lines with `1`: Executed
- Lines with `-1`: Not executed

**Uncovered branches in this demo:**
- Age < 0 (invalid)
- Age >= 65 (senior)
- Empty email
- Email without @
- Short password, no uppercase, no number

---

## 5. Using MCP Server

For integration with AI assistants (Claude, etc.):

```bash
./bin/xdebug-mcp

echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | ./bin/xdebug-mcp
```

---

## Files

| File | Purpose |
|------|---------|
| `buggy.php` | Debug demo with intentional bugs |
| `slow.php` | Performance profiling demo |
| `coverage.php` | Code coverage demo |

---

## Quick Command Reference

| Tool | Output | Use Case |
|------|--------|----------|
| `xstep --exit-on-break` | JSON | Step debugging for AI |
| `xstep` | Interactive | Manual step debugging |
| `xtrace` | JSON | Execution flow analysis |
| `xprofile` | JSON | Performance analysis |
| `xcoverage` | JSON | Code coverage analysis |

---

## Next Steps

1. Try these demos on your own PHP files
2. Read the main [README.md](../README.md) for full documentation
3. Configure Claude Desktop with the MCP server
