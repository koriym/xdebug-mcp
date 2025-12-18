---
description: Profile PHP performance to identify bottlenecks. Use when asked about performance, optimization, or slow code.
---

# PHP Performance Profiler

Execute `./bin/xprofile` to identify performance bottlenecks with precise data.

## Usage

```bash
./bin/xprofile --context="DESCRIPTION" -- COMMAND
```

## Options

- `--json` - AI-optimized JSON output
- `--context=TEXT` - Add contextual description (ALWAYS use this)
- `--include-vendor=PATTERNS` - Include vendor packages in analysis

## Examples

```bash
# Profile PHP script
./bin/xprofile --context="Optimize data processing" -- php process_data.php

# Profile with JSON output
./bin/xprofile --json --context="API performance" -- php api.php

# Docker execution
./bin/xprofile --context="Docker test" -- docker compose run --rm php php /app/script.php

# Include vendor analysis
./bin/xprofile --include-vendor="doctrine/*" --context="ORM performance" -- php app.php
```

## Analysis Guidelines

After running xprofile, analyze:
1. **Bottleneck functions** - Functions consuming most time
2. **Memory usage** - Memory-hungry operations
3. **Call counts** - Frequently called functions
4. **Optimization opportunities** - Caching, algorithm improvements

## When to Use

- "Profile this code"
- "Find performance bottlenecks"
- "Why is this slow?"
- "Optimize this script"
- "Memory usage analysis"