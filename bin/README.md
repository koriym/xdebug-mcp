# Xdebug MCP Tools

This directory contains various tools for PHP debugging, profiling, and analysis using Xdebug and MCP (Model Context Protocol).

## User Tools (Direct Execution)

These tools are designed for direct user interaction and analysis:

### `./xdebug-debug <script.php>`
AMP-powered interactive step debugging with breakpoints and variable inspection.
```bash
# Single command execution (no manual setup required)
./xdebug-debug test/debug_test.php

# Features: Step-by-step tracing, variable inspection, stack analysis
```

### `./xdebug-profile <script.php>`
Performance profiling and bottleneck analysis.
```bash
./xdebug-profile src/MyClass.php
# Generates cachegrind format profile files
```

### `./xdebug-coverage <script.php>`
Code coverage analysis with multiple output formats.
```bash
./xdebug-coverage tests/MyTest.php
# Generates HTML, XML, JSON, and text coverage reports
```

### `./xdebug-trace <script.php>`
Execution flow tracing and function call analysis.
```bash
./xdebug-trace problematic_script.php
# Generates detailed execution trace files (.xt format)
```

### `./xdebug-phpunit`
PHPUnit integration with Xdebug features.
```bash
./xdebug-phpunit tests/
# Runs PHPUnit tests with Xdebug integration
```

## System/Infrastructure Tools

These tools provide background services and protocol handling:

### `./xdebug-mcp`
**MCP protocol handler**
- Entry point for MCP (Model Context Protocol) communication
- Processes JSON-RPC requests from AI clients
- Delegates to XdebugClient for actual debugging operations

## Port Configuration

- **Port 9003**: Reserved for IDEs (VS Code, PhpStorm)  
- **Port 9004**: Xdebug MCP Server (conflict-free)

## Usage Patterns

### Quick Analysis
```bash
# For general debugging
./xdebug-trace script.php

# For performance issues
./xdebug-profile slow_script.php

# For test coverage
./xdebug-coverage test_suite.php
```

### Interactive Debugging
```bash
# Single command execution (no manual setup required)
./xdebug-debug buggy_script.php
```

### AI-Driven Analysis
The MCP tools enable AI assistants to perform comprehensive PHP analysis:
- Automatic tool selection based on analysis type
- Runtime data collection and analysis
- Non-invasive debugging without code modification

## Tool Selection Guide

**For Bug Investigation**: Start with `xdebug-trace`, escalate to `xdebug-debug` if interactive control needed

**For Performance Issues**: Use `xdebug-profile` for bottleneck identification

**For Test Quality**: Use `xdebug-coverage` for coverage analysis

**For AI Integration**: MCP tool (`xdebug-mcp`) provides seamless AI assistant integration

## Architecture Notes

- **Client-Server Model**: Xdebug acts as client, debug tools act as servers
- **DBGp Protocol**: Standard debugging protocol over TCP sockets
- **MCP Integration**: Enables AI assistants to perform runtime analysis
- **Non-Invasive**: No source code modification required for analysis