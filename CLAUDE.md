# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP Xdebug MCP (Model Context Protocol) Server that allows AI assistants to control PHP debugging sessions through Xdebug. The server implements MCP to bridge between AI clients and Xdebug debugging sessions.

## Common Commands

### Development Setup
```bash
composer install               # Install dependencies
```

### Testing
```bash
# PHPUnit Tests (Recommended)
vendor/bin/phpunit                       # Run all PHPUnit tests
vendor/bin/phpunit tests/Unit            # Run only unit tests
vendor/bin/phpunit tests/Integration     # Run only integration tests
vendor/bin/phpunit --coverage-text      # Run tests with coverage report
vendor/bin/phpunit --testdox            # Run tests with readable output

# Legacy Tests (Deprecated)
./simple_test.sh                        # Run basic syntax checks and MCP server tests
php test_mcp.php                        # Test MCP server functionality 
php test_integration.php                # Integration tests
php test_profiling_coverage.php         # Test profiling and coverage features
php tests/fake/demo.php                 # Run fake demo without real Xdebug
php tests/fake/FakeProfilingDemo.php    # Demo profiling and coverage features
```

### Running the MCP Server
```bash
./bin/xdebug-mcp             # Start the MCP server directly
php bin/xdebug-mcp           # Alternative way to start server
```

### Debugging with Xdebug
```bash
php -dxdebug.mode=debug test/debug_test.php    # Run PHP script with Xdebug enabled
```

## Architecture

### Core Components

- **McpServer.php**: Main MCP protocol handler that processes JSON-RPC requests and delegates to XdebugClient
- **XdebugClient.php**: Xdebug protocol client that communicates directly with Xdebug via sockets
- **bin/xdebug-mcp**: Executable entry point that instantiates and runs McpServer

### MCP Tools Available
The server exposes 21 tools via MCP across three categories:

**Debugging Tools (11 tools)**
- `xdebug_connect/disconnect`: Session management
- `xdebug_set/remove_breakpoint`: Breakpoint control  
- `xdebug_step_into/over/out`: Step debugging
- `xdebug_continue`: Resume execution
- `xdebug_get_stack/variables`: Inspection tools
- `xdebug_eval`: Expression evaluation

**Profiling Tools (4 tools)**
- `xdebug_start_profiling`: Start performance profiling
- `xdebug_stop_profiling`: Stop profiling and collect data
- `xdebug_get_profile_info`: Get profiling configuration
- `xdebug_analyze_profile`: Analyze Cachegrind format profile files

**Code Coverage Tools (6 tools)**
- `xdebug_start_coverage`: Start code coverage tracking
- `xdebug_stop_coverage`: Stop coverage tracking
- `xdebug_get_coverage`: Retrieve coverage data
- `xdebug_analyze_coverage`: Generate coverage reports (HTML/XML/JSON/text)
- `xdebug_coverage_summary`: Get coverage statistics

### Architecture Flow
1. MCP client sends JSON-RPC requests to McpServer
2. McpServer validates and routes tool calls to XdebugClient methods
3. XdebugClient communicates with Xdebug via socket protocol
4. Results are returned through MCP protocol back to client

### Testing Infrastructure
- **tests/fake/**: Contains fake implementations for testing without real Xdebug
- **FakeMcpServer.php**: Mock server for demonstrations
- **demo.php**: Interactive demo showing typical debugging workflow

### Configuration
- **mcp.json**: Example MCP client configuration
- **claude_desktop_config_example.json**: Example Claude Desktop setup
- Uses composer PSR-4 autoloading with XdebugMcp namespace

## PHP Requirements
- PHP >= 8.0
- ext-sockets extension for Xdebug communication
- ext-xml extension for parsing Xdebug responses
- Xdebug extension (with debug, profile, and coverage modes enabled)

## Xdebug Configuration
For full functionality, configure php.ini:
```ini
zend_extension=xdebug
xdebug.mode=debug,profile,coverage  ; Enable all modes
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
xdebug.output_dir=/tmp              ; For profile files
```

## Key Features Integration
- **Without Xdebug session**: Profiling and coverage work standalone using Xdebug functions
- **With Xdebug session**: Full debugging capabilities plus profiling/coverage
- **PHPUnit compatibility**: Coverage tools integrate with PHPUnit test workflows
- **Multiple report formats**: HTML, XML, JSON, and text reports for coverage analysis