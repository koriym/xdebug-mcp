# Xdebug MCP Tools

This directory contains CLI tools for PHP debugging, profiling, and analysis using Xdebug and MCP (Model Context Protocol).

Public debugging commands start with `x`. Helper scripts such as `check-env` and `test-json` are development or setup utilities, not debugging commands.

## Core Debugging Tools

### `./xstep`
Interactive step debugging with conditional breakpoints and Forward Trace™ capabilities.
```bash
# Interactive debugging session
./xstep --break='script.php:10' -- php script.php

# Conditional breakpoints (Forward Trace)
./xstep --break='User.php:42:$id==null' -- php script.php

# Step recording with JSON output
./xstep --break='loop.php:15' --steps=100 -- php script.php

# Multiple conditions (first match triggers)
./xstep --break='Auth.php:20:empty($token),User.php:85:$id==0' -- php app.php
```

### `./xprofile`
Performance profiling with microsecond precision and AI analysis integration.
```bash
# Basic profiling
./xprofile -- php script.php

# With context for AI analysis
./xprofile --context="API endpoint performance" -- php api.php

# JSON output for MCP integration
./xprofile --json -- php slow-script.php
```

### `./xtrace`
Execution flow tracing with complete function call analysis.
```bash
# Basic execution tracing
./xtrace -- php script.php

# With context documentation
./xtrace --context="Authentication flow analysis" -- php login.php

# JSON output for AI processing
./xtrace --json -- php complex-workflow.php
```

### `./xcoverage`
Code coverage analysis with multiple output formats.
```bash
# Basic coverage analysis
./xcoverage -- php ./vendor/bin/phpunit tests/MyTest.php

# Raw coverage for any PHP script
./xcoverage --raw -- php app.php

# Limit raw coverage to source paths
./xcoverage --raw --source=src -- php app.php
```

### `./xcompare`
Compare variable states at the same breakpoint across two different executions.
```bash
# Compare normal vs edge case input
./xcompare --break='Calculator.php:25' \
  --run-a='php calc.php 10' \
  --run-b='php calc.php 0' \
  --label-a='Normal input' \
  --label-b='Edge case (zero)'

# Compare success vs failure authentication
./xcompare --break='Auth.php:42' \
  --run-a='php login.php valid_user' \
  --run-b='php login.php invalid_user' \
  --context='Compare authentication flow'
```

> **Security note:** `--run`, `--run-a`, and `--run-b` values are executed through the shell so quoting and redirection behave as expected. Only pass trusted input to these options.
>
> **Steps default:** `xcompare` defaults to `--steps=1` because the comparison only needs the variable snapshot at the breakpoint. Use `--steps=N` (e.g. `--steps=100`) to also capture how execution diverges after the break.

## MCP Protocol Tools

### `./xdebug-mcp`
**Main MCP protocol server** - Entry point for AI assistant communication.
```bash
# Start MCP server
./xdebug-mcp

# With debug logging
MCP_DEBUG=1 ./xdebug-mcp

# Test MCP protocol
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | ./xdebug-mcp
```

## Utility Scripts

### `./check-env`
Environment verification script - checks Xdebug installation and configuration.
```bash
./check-env
# Verifies: PHP version, Xdebug extension, required modes, port availability
```

### `./test-json`
Internal JSON regression script for development.
```bash
./test-json
```

### `./validate-profile-json`
Profile data validation utility for ensuring schema compliance.
```bash
./validate-profile-json profile-data.json
# Validates against: https://koriym.github.io/xdebug-mcp/schemas/xprofile.json
```

### `./autoload.php`
Composer autoloader setup for standalone tool execution.

### `./debug-server-mcp`
Legacy debugging server utility (development purposes).

## Port Configuration

**Port Usage:**
- **Port 9003**: Reserved for IDEs (VS Code, PhpStorm)
- **Port 9004**: Xdebug MCP Server (conflict-free with IDE debugging)

## Tool Categories

**Forward Trace Tools (AI-Optimized):**
- `xstep` - Conditional breakpoints with step recording
- `xtrace` - Complete execution flow analysis
- `xprofile` - Performance bottleneck identification
- `xcoverage` - Test coverage verification
- `xcompare` - Compare variable states across two executions

**Integration Command:**
- `xdebug-mcp` - AI assistant protocol handler

**Support Scripts:**
- `check-env` - Environment validation
- `test-json` - Internal JSON regression script
- `validate-profile-json` - Schema validation

## Common Usage Patterns

### Bug Investigation
```bash
# Catch specific problem conditions
./xstep --break='ErrorHandler.php:45:$error_code>400' -- php api.php
```

### Performance Analysis
```bash
# Profile slow endpoints
./xprofile --context="Payment processing bottleneck analysis" -- php checkout.php
```

### Test Coverage Verification
```bash
# Analyze test effectiveness
./xcoverage -- php ./vendor/bin/phpunit tests/AuthTest.php
```

### Complex Flow Understanding
```bash
# Trace execution paths
./xtrace --context="Multi-step form submission workflow" -- php form-handler.php
```

### Comparative Debugging
```bash
# Compare behavior with different inputs
./xcompare --break='Validator.php:30' \
  --run-a='php validate.php valid@email.com' \
  --run-b='php validate.php invalid-email' \
  --context='Email validation comparison'
```

Run `--help` on any `x*` command for detailed usage information.
