---
description: Trace PHP execution flow to understand runtime behavior. Use when asked to trace, analyze execution flow, or debug PHP code.
---

# PHP Execution Flow Tracer

Execute `./bin/xtrace` to capture complete execution flow, function calls, parameters, and timing data.

## Usage

```bash
./bin/xtrace --context="DESCRIPTION" -- COMMAND
```

## Options

- `--json` - Output structured JSON for analysis
- `--context=TEXT` - Add contextual description (ALWAYS use this)
- `--include-vendor=PATTERNS` - Include vendor packages (e.g., "bear/*,ray/di")

## Examples

```bash
# Trace PHP script execution
./bin/xtrace --context="Debug login flow" -- php login.php

# Trace PHPUnit test
./bin/xtrace --context="Test analysis" -- php vendor/bin/phpunit tests/UserTest.php

# With vendor packages
./bin/xtrace --include-vendor="bear/*" --context="BEAR DI analysis" -- php app.php

# Docker execution
./bin/xtrace --context="Docker test" -- docker compose run --rm php php /app/script.php
```

## Analysis Guidelines

After running xtrace, analyze the output for:
1. **Execution flow** - Function call hierarchy and order
2. **Parameters** - Actual values passed to functions
3. **Timing** - Performance bottlenecks
4. **Anomalies** - Unexpected paths, infinite loops, wrong parameters

## When to Use

- "Trace this code"
- "Show execution flow"
- "What functions are called?"
- "Debug this PHP file"
- General PHP debugging (default choice)