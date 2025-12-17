---
description: Capture call stack (backtrace) at specific line. Use when asked for backtrace, stack trace, or call stack at a specific location.
---

# PHP Backtrace Capture

Execute `./bin/xback` to capture call stack at specific line without interactive debugging.

## Usage

```bash
./bin/xback --break="file.php:line" --context="DESCRIPTION" -- COMMAND
```

## Options

- `--break=SPEC` - Line location to capture backtrace (file.php:line)
- `--depth=N` - Maximum stack depth (default: 10)
- `--context=TEXT` - Add contextual description (ALWAYS use this)
- `--json` - Force JSON output format

## Examples

```bash
# Capture backtrace at specific line
./bin/xback --break="app.php:50" --context="Debug call stack at error point" -- php app.php

# With custom depth
./bin/xback --break="user.php:25" --depth=20 --context="Deep stack analysis" -- php script.php

# Multiple investigations
./bin/xback --break="auth.php:100" --context="Auth flow backtrace" -- php main.php
```

## Analysis Guidelines

After running xback, analyze:
1. **Call hierarchy** - How execution reached this point
2. **Function chain** - Which functions led to this location
3. **Entry points** - Where the execution started
4. **Context** - Variables and state at each level

## When to Use

- "Get backtrace at line X"
- "Show call stack at this point"
- "What's the stack trace here?"
- "How did execution reach this line?"
- "Show me the call hierarchy"

## Difference from xstep

**xback**: Lightweight, single-point stack capture (non-interactive)
**xstep**: Interactive debugging with stepping and variable inspection

Use `xback` when you only need to see the call stack at a specific location.
Use `xstep` when you need to step through code and inspect variables.