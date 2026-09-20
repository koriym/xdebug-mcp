# Demo - Quick Start Guide

Try the Xdebug MCP tools with these sample scripts.

## Prerequisites

**Navigate to project root first:**

```bash
# If you're in demo/ directory, go to project root
cd ..

# Install dependencies
composer install

# Verify Xdebug is installed
./bin/check-env
```

---

## 0. Run everything at once

```bash
composer demo
```

`run-demo.sh` exercises every documented feature of the CLI tools and the
MCP server against the sample scripts below, reporting PASS/FAIL per check.
It finishes in well under a minute and needs nothing beyond `composer
install` and Xdebug.

Checks whose prerequisites are missing are reported as SKIP rather than
failing:

| Check | Enable it by |
|---|---|
| Cross-version targets | having a second PHP on `PATH`, or `XDEBUG_MCP_DEMO_ALT_PHP=/path/to/php composer demo` |
| Docker target | starting a container runtime |

The interactive REPL is only presence-checked (`xrepl --help`) because it
needs a TTY, and the `--claude` flags are left out because they shell out
to the Claude CLI.

---

## 1. Debugging with xstep

Debug buggy code with step debugging.

**Ask AI:**
> "Debug demo/buggy.php and find the bug"
> 「demo/buggy.php をデバッグしてバグを見つけて」

**What to look for:**
- Line 22: `$a - $b` should be `$a + $b`
- Line 30: Loop misses last element

---

## 2. Execution Tracing with xtrace

Trace the execution flow without stopping.

**Ask AI:**
> "Trace execution flow of demo/buggy.php"
> 「demo/buggy.php の実行フローをトレースして」

**Output:**
- Function call hierarchy
- Parameter values at each call

---

## 3. Performance Profiling with xprofile

Find performance bottlenecks in slow code.

**Ask AI:**
> "Profile demo/slow.php and find bottlenecks"
> 「demo/slow.php をプロファイルしてボトルネックを見つけて」

**What to look for:**
- `inefficientSort()`: O(n³) complexity with unnecessary inner loop
- `slowStringProcess()`: Redundant string operations
- `fibonacci()`: Exponential recursive calls

---

## 4. Code Coverage with xcoverage

Analyze which lines of code are executed.

**Ask AI:**
> "Analyze code coverage of demo/coverage.php"
> 「demo/coverage.php のコードカバレッジを分析して」

**What to look for:**
- Lines with `1`: Executed (green)
- Lines with `-1`: Not executed (red)

**Uncovered branches in this demo:**
- Age < 0 (invalid)
- Age >= 65 (senior)
- Empty email
- Email without @
- Short password, no uppercase, no number

---

## Sample Files

| File | Purpose |
|------|---------|
| `buggy.php` | Debug demo with intentional bugs |
| `slow.php` | Performance profiling demo |
| `coverage.php` | Code coverage demo |

---

## Next Steps

1. Try these demos on your own PHP files
2. Read the main [README.md](../README.md) for full documentation
3. Configure Claude Desktop with the MCP server
