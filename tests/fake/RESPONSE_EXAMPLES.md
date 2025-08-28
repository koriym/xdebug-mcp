# MCP Tools Response Examples

Generated: 2025-08-27

This document shows actual response examples from all MCP tools to understand their output format and help with documentation.

## Session Management Tools (Pure JSON)

### xdebug_list_sessions

**Request:**
```json
{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_list_sessions","arguments":{}}}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "{\"status\":\"success\",\"active_sessions\":0,\"sessions\":[]}"
      }
    ]
  }
}
```

**Analysis:** Pure JSON response with structured session information.

### xdebug_connect (Error Case)

**Request:**
```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_connect","arguments":{"host":"127.0.0.1","port":9004}}}
```

**Actual Response (No Xdebug running):**
```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "content": [
      {
        "type": "text", 
        "text": "{\"status\":\"failed\",\"error\":\"Connection refused\",\"host\":\"127.0.0.1\",\"port\":9004}"
      }
    ]
  }
}
```

**Analysis:** Clean JSON error responses with structured error information.

### xdebug_step_into (Error Case)

**Request:**
```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"xdebug_step_into","arguments":{}}}
```

**Actual Response:**
```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "{\"status\":\"error\",\"message\":\"No active Xdebug session found\"}"
      }
    ]
  }
}
```

**Analysis:** Consistent error format across all step debugging tools.

## Information Tools (Mixed Format)

### xdebug_get_memory_usage

**Request:**
```json
{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"xdebug_get_memory_usage","arguments":{}}}
```

**Actual Response:**
```json
{
  "jsonrpc": "2.0",
  "id": 4,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Memory usage information:\n{\n    \"current_memory\": 1922824,\n    \"current_memory_real\": 4194304,\n    \"memory_limit\": \"1280M\"\n}"
      }
    ]
  }
}
```

**Analysis:** Text prefix + JSON data format.

### xdebug_info

**Request:**
```json
{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"xdebug_info","arguments":{"format":"array"}}}
```

**Expected Response:**
```json
{
  "jsonrpc": "2.0",
  "id": 5,
  "result": {
    "content": [
      {
        "type": "text",
        "text": "Xdebug information:\n{\n    \"version\": \"3.3.0\",\n    \"mode\": \"develop\",\n    \"client_host\": \"127.0.0.1\",\n    \"client_port\": \"9003\"\n}"
      }
    ]
  }
}
```

**Analysis:** Comprehensive Xdebug configuration information.

## Response Format Patterns

### Pattern 1: JSON-in-text envelope (Session Management, current)
Tools that return pure JSON in structured format:
- `xdebug_list_sessions`
- `xdebug_connect` 
- `xdebug_disconnect`
- `xdebug_step_into`
- `xdebug_step_over` 
- `xdebug_step_out`
- `xdebug_continue`
**Format:**
```json
{"status": "success|error", "data": "...", "session_id": "..."}
```

### Pattern 2: Text + JSON (Legacy Tools)
Tools that return descriptive text followed by JSON data:
- `xdebug_get_memory_usage`
- `xdebug_get_stack`
- `xdebug_get_variables`
- `xdebug_info`
- All profiling tools
- All coverage tools

**Format:**
```
Description text:
{
  "json": "data"
}
```

### Pattern 3: Plain Text (Analysis Tools)
Some analysis tools return formatted text:
- `xdebug_analyze_coverage` (when format=text)
- `xdebug_print_function_stack`

## Testing Instructions

### Manual Testing
Use the commands in `tests/fake/manual-mcp-test.sh`:

```bash
# Show all available tools
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php bin/xdebug-mcp | jq '.result.tools | length'

# Test session management
echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_list_sessions","arguments":{}}}' | php bin/xdebug-mcp | jq .

# Test error responses
echo '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"xdebug_step_into","arguments":{}}}' | php bin/xdebug-mcp | jq .
```

### Automated Testing
Run the comprehensive test with:
```bash
php tests/fake/test-all-mcp-tools.php
```

## Recommendations

### For Documentation Updates:

1. **Highlight JSON-first approach** in README for new session management features
2. **Show response examples** for each tool category
3. **Explain error handling** patterns consistently
4. **Document session lifecycle** with actual JSON examples

### For API Consistency:

Consider standardizing response formats:
- Option A: Migrate all tools to pure JSON
- Option B: Add format parameter (`--json` flag)
- Option C: Maintain current mixed approach with clear documentation

## Tools Count

Total MCP tools available: **43** (as of latest count from actual server test)

### Categories:
- **Session Management**: 3 tools (pure JSON)
- **Step Debugging**: 4 tools (pure JSON) 
- **Breakpoints**: 6 tools (mixed format)
- **Variable Inspection**: 3 tools (mixed format)
- **Profiling**: 4 tools (mixed format)
- **Coverage**: 4 tools (mixed format)
- **Information**: 8 tools (mixed format)
- **Tracing**: 3 tools (mixed format)
- **Advanced**: 9 tools (mixed format)