# Xdebug MCP Tools

This directory contains executable tools for PHP debugging, profiling, and analysis using Xdebug and MCP (Model Context Protocol).

## Core Debugging Tools

### `./xdebug-debug`
Interactive step debugging with conditional breakpoints and Forward Trace™ capabilities.
```bash
# Interactive debugging session
./xdebug-debug script.php

# Conditional breakpoints (Forward Trace)
./xdebug-debug --break='User.php:42:$id==null' --exit-on-break -- php script.php

# Step recording with JSON output
./xdebug-debug --break='loop.php:15' --steps=100 --json -- php script.php

# Multiple conditions (first match triggers)
./xdebug-debug --break='Auth.php:20:empty($token),User.php:85:$id==0' --exit-on-break -- php app.php
```

### `./xdebug-profile`
Performance profiling with microsecond precision and AI analysis integration.
```bash
# Basic profiling
./xdebug-profile script.php

# With context for AI analysis
./xdebug-profile --context="API endpoint performance" -- php api.php

# JSON output for MCP integration
./xdebug-profile --json -- php slow-script.php
```

### `./xdebug-trace`
Execution flow tracing with complete function call analysis.
```bash
# Basic execution tracing
./xdebug-trace script.php

# With context documentation
./xdebug-trace --context="Authentication flow analysis" -- php login.php

# JSON output for AI processing
./xdebug-trace --json -- php complex-workflow.php
```

### `./xdebug-coverage`
Code coverage analysis with multiple output formats.
```bash
# Basic coverage analysis
./xdebug-coverage tests/MyTest.php

# With context
./xdebug-coverage --context="Unit test coverage verification" -- php vendor/bin/phpunit tests/

# Multiple formats: HTML, XML, JSON, text
./xdebug-coverage --format=html --format=json -- php tests/suite.php
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
- `xdebug-debug` - Conditional breakpoints with step recording
- `xdebug-trace` - Complete execution flow analysis
- `xdebug-profile` - Performance bottleneck identification
- `xdebug-coverage` - Test coverage verification

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
./xdebug-debug --break='ErrorHandler.php:45:$error_code>400' --exit-on-break -- php api.php
```

### Performance Analysis  
```bash
# Profile slow endpoints
./xdebug-profile --context="Payment processing bottleneck analysis" -- php checkout.php
```

### Test Coverage Verification
```bash
# Analyze test effectiveness
./xdebug-coverage --context="AuthController test coverage" -- php vendor/bin/phpunit tests/AuthTest.php
```

### Complex Flow Understanding
```bash
# Trace execution paths
./xdebug-trace --context="Multi-step form submission workflow" -- php form-handler.php
```

All tools support `--help` option for detailed usage information.