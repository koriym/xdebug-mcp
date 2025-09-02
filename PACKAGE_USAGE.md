# Package Usage Examples

## Installation

```bash
composer require koriym/xdebug-mcp
```

## 🎯 Start Here: AI-Optimized Help

**Before using any tool, read the AI-optimized help documentation:**

```bash
./vendor/bin/xdebug-debug --help     # 🔍 Interactive debugging workflow
./vendor/bin/xdebug-coverage --help  # 🎯 Coverage analysis (beats HTML/XML)
./vendor/bin/xdebug-trace --help     # 📊 Execution flow tracing
./vendor/bin/xdebug-profile --help   # ⚡ Performance optimization
```

**Recommended AI Prompt:**
```
"Run [tool] --help first to understand this tool, then help me with [specific task]"
```

Each tool's help includes:
- ✅ **Purpose & Value**: Why this beats traditional methods
- ✅ **AI Workflows**: Step-by-step collaboration processes
- ✅ **Common Patterns**: Real-world usage examples with context
- ✅ **Output Formats**: JSON designed for AI consumption

## Basic Breakpoint + Step Debugging

### Command Line Usage

```bash
# Multiple breakpoints with context
./vendor/bin/xdebug-debug \
  --break="src/OrderProcessor.php:25,src/TaxCalculator.php:15" \
  --context="Order processing debug session" \
  --exit-on-break \
  -- php process_order.php

# Conditional breakpoint
./vendor/bin/xdebug-debug \
  --break="src/User.php:42:\$user_id>1000" \
  --context="Debug high-value user processing" \
  --exit-on-break \
  -- php user_handler.php

# Step recording (capture variable evolution)
./vendor/bin/xdebug-debug \
  --break="src/Calculator.php:20" \
  --steps=50 \
  --context="Track calculation steps" \
  --exit-on-break \
  -- php calculator.php
```

### Expected Output (JSON Format)

```json
{
    "$schema": "https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json",
    "breaks": [
        {
            "step": 1,
            "location": {
                "file": "src/OrderProcessor.php", 
                "line": 21
            },
            "variables": {
                "$subtotal": "float: 111.99",
                "$tax": "uninitialized: ",
                "$items": "array: [...]"
            }
        },
        {
            "step": 2,
            "location": {
                "file": "src/TaxCalculator.php",
                "line": 11
            },
            "variables": {
                "$price": "float: 111.99",
                "$rate": "float: 0.08",
                "$tax": "float: 8.96"
            }
        }
    ],
    "trace": {
        "file": "/tmp/trace-xyz.xt",
        "content": ["...execution trace..."]
    },
    "context": "Order processing debug session"
}
```

## MCP Integration (Claude Code)

### Slash Commands

```bash
# Interactive debugging with context
/x-debug --script="php app.php" --context="Login flow debugging" --breakpoints="User.php:42"

# Performance analysis
/x-profile --script="php slow_endpoint.php" --context="API performance analysis"

# Execution tracing
/x-trace --script="php workflow.php" --context="Business logic flow analysis"

# Code coverage
/x-coverage --script="php vendor/bin/phpunit UserTest.php" --context="Test coverage analysis"
```

### MCP Tool Parameters

```json
{
  "script": "php src/process_payment.php",
  "breakpoints": "PaymentGateway.php:85,CreditCard.php:120:\$amount>1000",
  "context": "Payment processing with high-value transaction debugging",
  "steps": 100,
  "include_vendor": "stripe/stripe-php,paypal/rest-api-sdk"
}
```

## Advanced Features

### Session Isolation

xdebug-mcp uses `XDEBUG_SESSION=xdebug-mcp` to isolate sessions from IDEs:

- **IDEs (PhpStorm, VS Code)**: Use port 9004 with session key `PHPSTORM` or `vscode`  
- **xdebug-mcp**: Uses port 9004 with session key `xdebug-mcp`
- **No conflicts**: Both can run simultaneously

### Vendor Filtering

```bash
# Default: Exclude all vendor code (focus on application logic)
./vendor/bin/xdebug-debug --exit-on-break -- php app.php

# Include specific packages
./vendor/bin/xdebug-debug \
  --include-vendor="doctrine/orm,symfony/console" \
  --exit-on-break \
  -- php app.php

# Include all vendor code
./vendor/bin/xdebug-debug \
  --include-vendor="*/*" \
  --exit-on-break \
  -- php app.php
```

### Context-Aware Debugging

Always use `--context` for self-explanatory debugging data:

```bash
# ✅ Good: Self-explanatory
./vendor/bin/xdebug-debug \
  --context="Testing user authentication with expired tokens" \
  --exit-on-break \
  -- php AuthTest.php

# ❌ Bad: Requires external knowledge
./vendor/bin/xdebug-debug --exit-on-break -- php AuthTest.php
```

## Integration with Testing Frameworks

### PHPUnit Integration

```bash
# Debug specific test with breakpoints
./vendor/bin/xdebug-debug \
  --break="UserTest.php:25,User.php:42" \
  --context="Debug user authentication test failure" \
  --exit-on-break \
  -- php vendor/bin/phpunit tests/UserTest.php::testLogin

# Coverage analysis for tests  
./vendor/bin/xdebug-coverage \
  --context="User module test coverage analysis" \
  -- php vendor/bin/phpunit tests/Unit/UserTest.php
```

### Custom Test Runners

```bash
# Works with any PHP-based test runner
./vendor/bin/xdebug-debug \
  --break="TestRunner.php:15" \
  --context="Custom test runner debugging" \
  --exit-on-break \
  -- php bin/custom-test-runner.php
```

## Error Handling and Cleanup

### Automatic Session Cleanup

- Sessions automatically close on script completion
- Emergency cleanup kills only xdebug-mcp sessions (preserves IDE sessions)  
- Port sharing allows concurrent IDE and MCP usage

### Connection Issues

```bash
# Check if port is available
lsof -i :9004

# Kill stuck xdebug-mcp sessions only
pkill -f "XDEBUG_SESSION=xdebug-mcp"

# Verify Xdebug configuration
php -m | grep xdebug
```

## Best Practices

### 1. Use Context for All Sessions
```bash
./vendor/bin/xdebug-debug \
  --context="Debugging checkout process with invalid coupon codes" \
  --exit-on-break \
  -- php checkout.php
```

### 2. Specific Breakpoints
```bash
# Target specific issues
--break="PaymentGateway.php:42:\$response['status']=='failed'"
```

### 3. Vendor Filtering Strategy
- **Application debugging**: Default (no vendor)
- **Framework issues**: `--include-vendor=framework/*`  
- **Integration debugging**: `--include-vendor=package1,package2`

### 4. Step Recording for Complex Issues
```bash
./vendor/bin/xdebug-debug \
  --break="ComplexAlgorithm.php:15" \
  --steps=200 \
  --context="Algorithm variable evolution tracking" \
  --exit-on-break \
  -- php algorithm.php
```