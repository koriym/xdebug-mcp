# PHP Xdebug MCP Server

A comprehensive MCP (Model Context Protocol) server that enables AI to control PHP Xdebug debugger.

## Overview

This MCP server provides **complete coverage of 42 Xdebug features**, enabling AI to fully automate PHP application debugging, profiling, and code coverage analysis.

**Specifications:**
- **MCP 2025-03-26** (Latest specification)
- **Xdebug 3.x** Full support
- **42 MCP Tools** Available

## Key Features

### Debugging Tools (11 tools)
- **Session Management**: Connect/disconnect to Xdebug sessions
- **Breakpoint Control**: Line, exception, and watch breakpoints
- **Step Execution**: step into, step over, step out
- **Execution Control**: Continue, pause execution
- **Code Inspection**: Stack traces, variable inspection, PHP expression evaluation
- **Advanced Features**: Conditional breakpoints, exception handling

### Profiling Tools (4 tools)
- **Performance Measurement**: Detailed execution time and memory usage analysis
- **Function-level Analysis**: Per-function execution time and call count
- **Cachegrind Support**: Integration with KCacheGrind/QCacheGrind
- **Standalone Operation**: Works without active Xdebug sessions

### Code Coverage Tools (6 tools)
- **Comprehensive Tracking**: Complete recording of executed and unexecuted lines
- **Multiple Report Formats**: HTML/XML/JSON/text formats
- **PHPUnit Integration**: CI/CD pipeline automation support
- **Detailed Statistics**: Per-file and per-line coverage rates

### Extended Features (21 tools)
#### **Statistics & Diagnostics**
- Memory usage monitoring (current and peak values)
- Stack depth tracking
- Execution time measurement
- Xdebug configuration information

#### **Error Management**
- Automatic collection of PHP errors, warnings, and notices
- Error history management and analysis
- Production environment issue tracking

#### **Tracing**
- Complete function call tracing
- Specific function monitoring
- Execution flow visualization

#### **Advanced Breakpoints**
- Exception-type specific breakpoints
- Variable watch points
- Breakpoint list management

#### **Extended Stack Information**
- Detailed stack display with arguments
- Formatted stack traces
- Detailed caller information

#### **Dynamic Configuration**
- Dynamic Xdebug feature modification
- Setting adjustments during debug sessions
- Optimization parameter tuning

## Quick Start

```bash
# 1. Clone and install dependencies
git clone <repository-url>
cd xdebug-mcp
composer install

# 2. Add MCP server
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp"

# 3. Verify installation
claude mcp list

# 4. Test in Claude Desktop
# Ask: "利用可能なツールを教えてください"
```

## Setup

### 1. Install Dependencies
```bash
composer install
```

### 2. Configure Xdebug
Add to your php.ini:
```ini
zend_extension=xdebug
xdebug.mode=debug,profile,coverage  ; Enable debug, profile, and coverage
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
; Optional profiling settings
xdebug.output_dir=/tmp
xdebug.profiler_output_name=cachegrind.out.%p
```

### 3. MCP Client Configuration

#### Method 1: Using claude mcp add Command (Recommended)

Add the MCP server using the Claude CLI command:

```bash
claude mcp add xdebug php /path/to/your/xdebug-mcp/bin/xdebug-mcp
```

For this project specifically:
```bash
cd /path/to/xdebug-mcp
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp"
```

**Verify the installation:**
```bash
# List all MCP servers
claude mcp list

# Should show:
# xdebug: php /path/to/xdebug-mcp/bin/xdebug-mcp - ✓ Connected
```

**Manage MCP servers:**
```bash
# Remove server (if needed)
claude mcp remove xdebug

# Add with environment variables
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp" --env PATH=/usr/local/bin:/usr/bin:/bin
```

#### Method 2: Manual Claude Desktop Configuration

Add to Claude Desktop configuration file:

**macOS**: `claude`
**Windows**: `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "xdebug": {
      "command": "php",
      "args": ["/path/to/your/xdebug-mcp/bin/xdebug-mcp"],
      "env": {
        "PATH": "/usr/local/bin:/usr/bin:/bin"
      }
    }
  }
}
```

#### Method 3: Other MCP Clients
Refer to `mcp.json` file:
```json
{
  "mcpServers": {
    "xdebug": {
      "command": "php",
      "args": ["/path/to/xdebug-mcp/bin/xdebug-mcp"],
      "env": {
        "PHP_PATH": "/usr/bin/php"
      }
    }
  }
}
```

## Usage

### Verify Installation

After setting up the MCP server, verify it's working properly:

#### Command Line Verification
```bash
# Check MCP server status
claude mcp list

# Test server directly
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php ./bin/xdebug-mcp
```

#### Claude Desktop Verification
Start a conversation and ask:

```text
What tools are available?
```

You should see all 42 Xdebug tools listed.

### With AI Clients (Claude Desktop, etc.)

After MCP client setup, you can ask AI:

```
Start PHP debugging and set a breakpoint at line 10 in /path/to/script.php
```

```
Check the current stack trace and variable values
```

```
Step over to the next line and examine the $user_data variable
```

### Direct JSON-RPC Usage

1. Start MCP server
```bash
./bin/xdebug-mcp
```

2. Connect to Xdebug session
```json
{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": {"name": "xdebug_connect", "arguments": {"host": "127.0.0.1", "port": 9003}}}
```

3. Set breakpoint
```json
{"jsonrpc": "2.0", "id": 2, "method": "tools/call", "params": {"name": "xdebug_set_breakpoint", "arguments": {"filename": "/path/to/file.php", "line": 10}}}
```

4. Step execution
```json
{"jsonrpc": "2.0", "id": 3, "method": "tools/call", "params": {"name": "xdebug_step_over", "arguments": {}}}
```

## Testing & Demo

### Fake Mode Demo
Try the demo without actual Xdebug:
```bash
php tests/fake/demo.php
```

### Real Xdebug Testing
Test debugging with actual PHP script:
```bash
php -dxdebug.mode=debug test/debug_test.php
```

### Run Tests
```bash
# All PHPUnit tests
vendor/bin/phpunit

# With readable output
vendor/bin/phpunit --testdox

# With coverage
vendor/bin/phpunit --coverage-text
```

## Claude Desktop Usage Examples

With proper MCP server setup, you can have natural language interactions in Claude Desktop:

### Start Debug Session
```
User: I want to start PHP debugging. Please listen on 127.0.0.1:9003

Claude: I'll connect to the Xdebug session for you.
[Executes xdebug_connect tool]
Connected to Xdebug session. Session info: {...}
```

### Set Breakpoints
```
User: Set a breakpoint at line 25 in /var/www/html/index.php

Claude: I'll set a breakpoint at the specified location.
[Executes xdebug_set_breakpoint tool]
Breakpoint set successfully (ID: 1)
```

### Step Debugging and Inspection
```
User: Check current variables and step over to the next line

Claude: Let me first check the current local variables.
[Executes xdebug_get_variables tool]
Current variables:
- $user_id: 123
- $data: ["name" => "John", "age" => 30]

Now stepping over to the next line.
[Executes xdebug_step_over tool]
Step over completed
```

### Expression Evaluation
```
User: Check the value of $data['name']

Claude: I'll evaluate that expression.
[Executes xdebug_eval tool]
Value of $data['name']: "John"
```

### Profiling
```
User: Start profiling this PHP script

Claude: I'll start profiling for you.
[Executes xdebug_start_profiling tool]
Profiling started

[After execution]
User: Stop profiling and analyze the results

Claude: I'll stop profiling and analyze the results.
[Executes xdebug_stop_profiling and xdebug_analyze_profile tools]
Profiling Analysis:
- Total execution time: 0.25 seconds
- Slowest function: calculateTax() (0.15s, 60%)
- Most called function: formatDate() (1,250 calls)
```

### Code Coverage
```
User: Measure code coverage for my tests

Claude: I'll start code coverage tracking.
[Executes xdebug_start_coverage tool]
Code coverage tracking started

[After test execution]
User: Generate a coverage report

Claude: I'll retrieve coverage data and generate an HTML report.
[Executes xdebug_get_coverage and xdebug_analyze_coverage tools]
Coverage Summary:
- Total coverage: 85.2%
- Lines tested: 1,240 / 1,456 lines
- Uncovered files: src/Utils.php (45%), src/Cache.php (62%)
HTML report saved to coverage.html
```

## Available Tools (42 Features)

### Debug Tools (11)
| Tool Name | Function |
|-----------|----------|
| `xdebug_connect` | Connect to Xdebug session |
| `xdebug_disconnect` | Disconnect from Xdebug session |
| `xdebug_set_breakpoint` | Set line breakpoint |
| `xdebug_remove_breakpoint` | Remove breakpoint |
| `xdebug_step_into` | Step into execution |
| `xdebug_step_over` | Step over execution |
| `xdebug_step_out` | Step out execution |
| `xdebug_continue` | Continue execution |
| `xdebug_get_stack` | Get stack trace |
| `xdebug_get_variables` | Get variable list |
| `xdebug_eval` | Evaluate PHP expression |

### Profiling Tools (4)
| Tool Name | Function |
|-----------|----------|
| `xdebug_start_profiling` | Start profiling |
| `xdebug_stop_profiling` | Stop profiling |
| `xdebug_get_profile_info` | Get profile configuration info |
| `xdebug_analyze_profile` | Analyze Cachegrind file |

### Code Coverage Tools (6)
| Tool Name | Function |
|-----------|----------|
| `xdebug_start_coverage` | Start code coverage tracking |
| `xdebug_stop_coverage` | Stop code coverage tracking |
| `xdebug_get_coverage` | Get coverage data |
| `xdebug_analyze_coverage` | Generate coverage report (HTML/XML/JSON/text) |
| `xdebug_coverage_summary` | Get coverage statistics summary |

### Statistics & Diagnostics Tools (5)
| Tool Name | Function |
|-----------|----------|
| `xdebug_get_memory_usage` | Get current memory usage |
| `xdebug_get_peak_memory_usage` | Get peak memory usage |
| `xdebug_get_stack_depth` | Get current stack depth |
| `xdebug_get_time_index` | Get elapsed time since script start |
| `xdebug_info` | Get Xdebug configuration and diagnostic info |

### Error Management Tools (3)
| Tool Name | Function |
|-----------|----------|
| `xdebug_start_error_collection` | Start PHP error collection |
| `xdebug_stop_error_collection` | Stop error collection |
| `xdebug_get_collected_errors` | Get collected error list |

### Tracing Tools (5)
| Tool Name | Function |
|-----------|----------|
| `xdebug_start_trace` | Start function call tracing |
| `xdebug_stop_trace` | Stop tracing |
| `xdebug_get_tracefile_name` | Get trace file name |
| `xdebug_start_function_monitor` | Start monitoring specific functions |
| `xdebug_stop_function_monitor` | Stop function monitoring |

### Advanced Breakpoint Tools (3)
| Tool Name | Function |
|-----------|----------|
| `xdebug_list_breakpoints` | List active breakpoints |
| `xdebug_set_exception_breakpoint` | Set exception breakpoint |
| `xdebug_set_watch_breakpoint` | Set variable watch breakpoint |

### Extended Stack Information Tools (3)
| Tool Name | Function |
|-----------|----------|
| `xdebug_get_function_stack` | Get detailed function stack info |
| `xdebug_print_function_stack` | Get formatted stack display |
| `xdebug_call_info` | Get detailed caller information |

### Feature Configuration Tools (3)
| Tool Name | Function |
|-----------|----------|
| `xdebug_get_features` | List available Xdebug features |
| `xdebug_set_feature` | Set Xdebug feature value |
| `xdebug_get_feature` | Get specific feature value |

## Architecture

### Core Components

- **McpServer.php**: Main MCP protocol handler that processes JSON-RPC requests and delegates to XdebugClient
- **XdebugClient.php**: Xdebug protocol client that communicates directly with Xdebug via sockets
- **bin/xdebug-mcp**: Executable entry point that instantiates and runs McpServer

### Architecture Flow
1. MCP client sends JSON-RPC requests to McpServer
2. McpServer validates and routes tool calls to XdebugClient methods
3. XdebugClient communicates with Xdebug via socket protocol
4. Results are returned through MCP protocol back to client

### Testing Infrastructure
- **tests/fake/**: Contains fake implementations for testing without real Xdebug
- **FakeMcpServer.php**: Mock server for demonstrations
- **demo.php**: Interactive demo showing typical debugging workflow

## Requirements

- **PHP >= 8.0**
- **ext-sockets** extension for Xdebug communication
- **ext-xml** extension for parsing Xdebug responses
- **Xdebug extension** (with debug, profile, and coverage modes enabled)

## Troubleshooting

### MCP Server Issues

#### Server Not Listed or Not Connected
```bash
# Check server status
claude mcp list

# If server is not listed, add it again
claude mcp add xdebug php /path/to/xdebug-mcp/bin/xdebug-mcp

# If server shows as not connected, check paths and permissions
ls -la /path/to/xdebug-mcp/bin/xdebug-mcp
chmod +x /path/to/xdebug-mcp/bin/xdebug-mcp
```

#### Remove and Re-add Server
```bash
# Remove problematic server
claude mcp remove xdebug

# Re-add with correct path
cd /path/to/xdebug-mcp
claude mcp add xdebug php "$(pwd)/bin/xdebug-mcp"
```

### Cannot Connect to Xdebug
1. Verify Xdebug is properly installed
2. Check php.ini configuration
3. Ensure port 9003 is available

### MCP Server Won't Start
1. Verify PHP path is correctly configured
2. Ensure `composer install` has been run
3. Check file execution permissions
4. Test server directly: `php ./bin/xdebug-mcp --help`

### Debugging Session Issues
1. Verify Xdebug mode includes 'debug'
2. Check firewall settings for port 9003
3. Ensure `xdebug.start_with_request=yes` is set

### Performance Issues
1. For profiling: Ensure sufficient disk space in `xdebug.output_dir`
2. For coverage: Consider using file filters to reduce overhead
3. Monitor memory usage with built-in memory tracking tools

## Additional Resources

- [Xdebug Official Documentation](https://xdebug.org/docs/)
- [Model Context Protocol Specification](https://modelcontextprotocol.io/)
- [Claude Desktop MCP Guide](https://docs.anthropic.com/claude/docs/mcp)

## Contributing

Contributions are welcome! Please ensure:
1. All tests pass: `vendor/bin/phpunit`
2. Code follows PSR-4 autoloading standards
3. New features include appropriate tests
4. Documentation is updated for new functionality

## License

This project is open source. See the LICENSE file for details.

---

🇯🇵 **日本語版README**: [README-ja.md](README-ja.md)
