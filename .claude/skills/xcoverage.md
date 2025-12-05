---
description: Analyze PHP code coverage to identify untested code. Use when asked about test coverage or which lines are tested.
---

# PHP Code Coverage Analyzer

Execute `./bin/xcoverage` to collect code coverage data for any PHP script or PHPUnit tests.

## Usage

```bash
# Auto-detect PHPUnit (most common)
./bin/xcoverage

# Any PHP script
./bin/xcoverage -- php script.php
```

## Options

- `--branch-coverage` - Enable detailed branch/path coverage analysis
- `--include-vendor=PATTERNS` - Include vendor packages (e.g., "bear/*,ray/di")

## Examples

```bash
# Run PHPUnit with coverage (auto-detected)
./bin/xcoverage

# Coverage for specific PHP script
./bin/xcoverage -- php app.php

# Include vendor packages
./bin/xcoverage --include-vendor=bear/resource,ray/di

# Branch coverage with GraphViz
./bin/xcoverage --branch-coverage -- php app.php
```

## Output Format

JSON coverage data with line execution counts:
- `1` = Line executed
- `-1` = Line not executed
- `-2` = Dead code (unreachable)

## Analysis Guidelines

After running xcoverage, analyze:
1. **Uncovered lines** - Code paths not tested
2. **Coverage percentage** - Overall test coverage
3. **Critical paths** - Important code that needs testing
4. **Dead code** - Unreachable code to remove

## When to Use

- "Check test coverage"
- "Which lines are tested?"
- "Coverage analysis"
- "Find untested code"
- "Run tests with coverage"