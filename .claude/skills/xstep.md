---
description: Interactive PHP step debugging with breakpoints. Use when asked to set breakpoints, step through code, or inspect variables at specific points.
---

# PHP Interactive Step Debugger

Execute `./bin/xstep` for interactive debugging with breakpoints and variable inspection.

## Usage

```bash
./bin/xstep --context="DESCRIPTION" --exit-on-break -- COMMAND
```

## Options

- `--break=SPEC` - Set breakpoints (file.php:line or file.php:line:condition)
- `--exit-on-break` - Auto-continue and output complete trace
- `--steps=N` - Record N steps of variable evolution
- `--context=TEXT` - Add contextual description (ALWAYS use this)
- `--json` - Force JSON output format
- `--include-vendor=PATTERNS` - Include vendor packages

## Breakpoint Patterns

```bash
file.php:15                  # Break at line 15
file.php:20:$user==null      # Conditional: break when $user is null
file1.php:5,file2.php:10     # Multiple breakpoints
api.php:42:$id>100           # Break when condition is true
```

## Examples

```bash
# Quick bug investigation
./bin/xstep --context="Debug login failure" --exit-on-break -- php login.php

# Variable state tracking
./bin/xstep --break=app.php:50 --steps=20 --context="Track user variable" -- php app.php

# Conditional debugging
./bin/xstep --break="user.php:15:\$id==null" --context="Debug null ID" -- php user.php

# Multi-point analysis
./bin/xstep --break="auth.php:20,user.php:45" --context="Debug auth flow" -- php main.php
```

## Analysis Guidelines

After running xstep, analyze:
1. **Variable states** - Values at breakpoints
2. **Execution path** - How code reached the breakpoint
3. **Conditions** - Why conditional breakpoints triggered
4. **Stack trace** - Call hierarchy at each point

## When to Use

- "Set a breakpoint at line X"
- "Step through this code"
- "Inspect variables at this point"
- "Debug with breakpoints"
- "Track variable changes"

## Note

For general debugging, prefer `xtrace` first. Use `xstep` when you need:
- Interactive control at specific points
- Variable inspection at exact locations
- Conditional breakpoint analysis