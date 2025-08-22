# MCP Tools Testing Guide

This guide explains how to test the 25 confirmed working tools of the Xdebug MCP Server.

## 📋 Quick Start

### Working Tools Test (Recommended)
```bash
# Test the 25 confirmed working tools
./bin/test-all.sh

# Alternative: Use the new refactored test runner directly
./bin/test-working-tools.php
```

This method tests the following tools:
- ✅ Profiling Tools (4 tools)
- ✅ Coverage Tools (5 tools)  
- ✅ Statistics Tools (5 tools)
- ✅ Error Collection Tools (3 tools)
- ✅ Tracing Tools (5 tools)
- ✅ Configuration Tools (2 tools)

### CLI Tools Individual Testing
```bash
# Recommended Xdebug usage (load only when needed)
./bin/xdebug-trace test/debug_test.php
./bin/xdebug-profile test/debug_test.php  
./bin/xdebug-coverage test/debug_test.php --text
```

### Individual Tool Testing (If Needed)
```bash
# Environment check
./bin/check-xdebug-status

# Individual tool test example
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_get_memory_usage","arguments":{}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp
```

## 🔧 Test Results Overview

### Working Tools by Category

| Category | Tool Count | Description |
|----------|------------|-------------|
| **Profiling** | 4 | Performance analysis, function timing |
| **Coverage** | 5 | Code coverage, report generation |
| **Statistics** | 5 | Memory usage, stack information |
| **Error Collection** | 3 | PHP error tracking |
| **Tracing** | 5 | Function call tracing |
| **Configuration** | 2 | Xdebug settings management |
| **Total** | **24** | Confirmed working tools |

### Limitations

**Unavailable Tools (18 tools):**
- Session management: connect, disconnect
- Breakpoints: set/remove/list breakpoints  
- Step execution: step_into, step_over, step_out, continue
- Variable inspection: get_stack, get_variables, eval
- Advanced features: exception/watch breakpoints, features management

**Reason:** These tools require active debugging sessions with persistent socket connections, which is not currently supported in the command-line MCP server architecture.

### Actual Test Results Example

```bash
$ ./bin/test-all.sh

🚀 Working MCP Tools Test
===================================================

✅ Xdebug is not loaded (good - as recommended)

🧪 Testing 24 Working MCP Tools
===================================================

⚡ Profiling Tools (4 tools)
  xdebug_start_profiling              ... PASS
  xdebug_stop_profiling               ... PASS
  xdebug_get_profile_info             ... PASS
  xdebug_analyze_profile              ... PASS

📊 Coverage Tools (5 tools)
  xdebug_start_coverage               ... PASS
  xdebug_stop_coverage                ... PASS
  xdebug_get_coverage                 ... PASS
  xdebug_analyze_coverage             ... PASS
  xdebug_coverage_summary             ... PASS

📈 Statistics Tools (5 tools)
  xdebug_get_memory_usage             ... PASS
  xdebug_get_peak_memory_usage        ... PASS
  xdebug_get_stack_depth              ... PASS
  xdebug_get_time_index               ... PASS
  xdebug_info                         ... PASS

🚨 Error Collection Tools (3 tools)
  xdebug_start_error_collection       ... PASS
  xdebug_stop_error_collection        ... PASS
  xdebug_get_collected_errors         ... PASS

🔍 Tracing Tools (5 tools)
  xdebug_start_trace                  ... PASS
  xdebug_stop_trace                   ... PASS
  xdebug_get_tracefile_name           ... PASS
  xdebug_start_function_monitor       ... PASS
  xdebug_stop_function_monitor        ... PASS

⚙️ Configuration Tools (2 tools)
  xdebug_call_info                    ... PASS
  xdebug_print_function_stack         ... PASS

===================================================
📋 Final Results
===================================================
Total tools tested: 24/24
✅ Passed: 24
❌ Failed: 0
Pass rate: 100%

✅ All working tools functioning properly!
```

## 🔧 Environment Setup

### Prerequisites
```bash
# Xdebug should be commented out in php.ini for optimal performance
;zend_extension=xdebug

# Xdebug extension should be available for dynamic loading
php -dzend_extension=xdebug.so -m | grep xdebug
```

### Testing Workflow
1. **Install dependencies**: `composer install`
2. **Check environment**: `./bin/check-xdebug-status`  
3. **Run working tools test**: `./bin/test-all.sh`
4. **Review results**: All 24 tools should pass

## 🚀 Individual Tool Categories

### Profiling Tools (4 tools)
Test performance analysis and function timing:
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_start_profiling","arguments":{}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp
echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_stop_profiling","arguments":{}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp
```

### Coverage Tools (5 tools)
Test code coverage tracking:
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_start_coverage","arguments":{"track_unused":true}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp
echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_stop_coverage","arguments":{}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp
```

### Statistics Tools (5 tools)
Test memory and timing information:
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_get_memory_usage","arguments":{}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp
echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_info","arguments":{"format":"array"}}}' | php -dzend_extension=xdebug.so bin/xdebug-mcp
```

## 🔍 Troubleshooting

### Common Issues

#### 1. Xdebug extension not available
```bash
php -m | grep xdebug
# If empty, install Xdebug: pecl install xdebug
```

#### 2. MCP server not responding
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php -dzend_extension=xdebug.so bin/xdebug-mcp
# Should return list of available tools
```

#### 3. Individual tool failures
Check specific tool requirements and ensure Xdebug is properly loaded with the correct mode.

## 📚 References

- [Xdebug Documentation](https://xdebug.org/docs/)
- [MCP Protocol Specification](https://spec.modelcontextprotocol.io/)
- [PHPUnit Testing](https://phpunit.de/documentation.html)

---

**💡 Note:** This testing guide focuses on the 25 confirmed working tools. Session-dependent debugging tools require additional architecture work for proper integration.