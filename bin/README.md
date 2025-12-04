# Xdebug MCP Tools

This directory contains executable tools for PHP debugging, profiling, and analysis using Xdebug and MCP (Model Context Protocol).

## Core Debugging Tools

### `./xstep`
Interactive step debugging with conditional breakpoints and Forward Trace™ capabilities.
```bash
# Interactive debugging session
./xstep script.php

# Conditional breakpoints (Forward Trace)
./xstep --break='User.php:42:$id==null' --exit-on-break -- php script.php

# Step recording with JSON output
./xstep --break='loop.php:15' --steps=100 --json -- php script.php

# Multiple conditions (first match triggers)
./xstep --break='Auth.php:20:empty($token),User.php:85:$id==0' --exit-on-break -- php app.php
```

### `./xprofile`
Performance profiling with microsecond precision and AI analysis integration.
```bash
# Basic profiling
./xprofile script.php

# With context for AI analysis
./xprofile --context="API endpoint performance" -- php api.php

# JSON output for MCP integration
./xprofile --json -- php slow-script.php
```

### `./xtrace`
Execution flow tracing with complete function call analysis.
```bash
# Basic execution tracing
./xtrace script.php

# With context documentation
./xtrace --context="Authentication flow analysis" -- php login.php

# JSON output for AI processing
./xtrace --json -- php complex-workflow.php
```

### `./xcoverage`
Code coverage analysis with multiple output formats.
```bash
# Basic coverage analysis
./xcoverage tests/MyTest.php

# With context
./xcoverage --context="Unit test coverage verification" -- php vendor/bin/phpunit tests/

# Multiple formats: HTML, XML, JSON, text
./xcoverage --format=html --format=json -- php tests/suite.php
```

### `./xdebug-phpunit`
PHPUnit integration with Xdebug profiling and coverage.
```bash
# Run PHPUnit with Xdebug integration
./xdebug-phpunit tests/UserTest.php

# With context for analysis
./xdebug-phpunit --context="User authentication tests" tests/AuthTest.php
```

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

## Utility Tools

### `./check-env`
Environment verification script - checks Xdebug installation and configuration.
```bash
./check-env
# Verifies: PHP version, Xdebug extension, required modes, port availability
```

### `./test-json`
JSON validation and testing utility for MCP protocol compliance.
```bash
./test-json
# Tests: JSON schema validation, MCP tool responses, output format compliance
```

### `./validate-profile-json`
Profile data validation utility for ensuring schema compliance.
```bash
./validate-profile-json profile-data.json
# Validates against: https://koriym.github.io/xdebug-mcp/schemas/xdebug-profile.json
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

**Integration Tools:**
- `xdebug-mcp` - AI assistant protocol handler
- `xdebug-phpunit` - Test framework integration

**Support Tools:**
- `check-env` - Environment validation
- `test-json` - Protocol compliance testing
- `validate-profile-json` - Schema validation

## Common Usage Patterns

### Bug Investigation
```bash
# Catch specific problem conditions
./xstep --break='ErrorHandler.php:45:$error_code>400' --exit-on-break -- php api.php
```

### Performance Analysis
```bash
# Profile slow endpoints
./xprofile --context="Payment processing bottleneck analysis" -- php checkout.php
```

### Test Coverage Verification
```bash
# Analyze test effectiveness
./xcoverage --context="AuthController test coverage" -- php vendor/bin/phpunit tests/AuthTest.php
```

### Complex Flow Understanding
```bash
# Trace execution paths
./xtrace --context="Multi-step form submission workflow" -- php form-handler.php
```

All tools support `--help` option for detailed usage information.