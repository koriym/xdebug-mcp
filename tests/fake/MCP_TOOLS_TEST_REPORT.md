# MCP Tools Comprehensive Test Report

Generated: 2025-08-28 03:34:11

Total tools tested: 41

## Summary

| Tool | Status | Response Type |
|------|--------|---------------|
| `xdebug_list_sessions` | ❌ | Error |
| `xdebug_connect` | ❌ | Error |
| `xdebug_disconnect` | ❌ | Error |
| `xdebug_step_into` | ❌ | Error |
| `xdebug_step_over` | ❌ | Error |
| `xdebug_step_out` | ❌ | Error |
| `xdebug_continue` | ❌ | Error |
| `xdebug_set_breakpoint` | ❌ | Error |
| `xdebug_remove_breakpoint` | ❌ | Error |
| `xdebug_list_breakpoints` | ❌ | Error |
| `xdebug_get_stack` | ❌ | Error |
| `xdebug_get_variables` | ❌ | Error |
| `xdebug_eval` | ❌ | Error |
| `xdebug_start_profiling` | ❌ | Error |
| `xdebug_stop_profiling` | ❌ | Error |
| `xdebug_get_profile_info` | ❌ | Error |
| `xdebug_start_coverage` | ❌ | Error |
| `xdebug_stop_coverage` | ❌ | Error |
| `xdebug_get_coverage` | ❌ | Error |
| `xdebug_coverage_summary` | ❌ | Error |
| `xdebug_get_memory_usage` | ✅ | Success |
| `xdebug_get_peak_memory_usage` | ✅ | Success |
| `xdebug_get_stack_depth` | ✅ | Success |
| `xdebug_get_time_index` | ✅ | Success |
| `xdebug_info` | ✅ | Success |
| `xdebug_get_features` | ❌ | Error |
| `xdebug_set_feature` | ❌ | Error |
| `xdebug_get_feature` | ❌ | Error |
| `xdebug_start_error_collection` | ✅ | Success |
| `xdebug_stop_error_collection` | ✅ | Success |
| `xdebug_get_collected_errors` | ✅ | Success |
| `xdebug_start_trace` | ✅ | Success |
| `xdebug_stop_trace` | ✅ | Success |
| `xdebug_get_tracefile_name` | ✅ | Success |
| `xdebug_start_function_monitor` | ✅ | Success |
| `xdebug_stop_function_monitor` | ✅ | Success |
| `xdebug_set_exception_breakpoint` | ❌ | Error |
| `xdebug_set_watch_breakpoint` | ❌ | Error |
| `xdebug_get_function_stack` | ✅ | Success |
| `xdebug_print_function_stack` | ✅ | Success |
| `xdebug_call_info` | ✅ | Success |

**Statistics:**
- ✅ Successful: 16
- ❌ Errors: 25

## Detailed Results

### `xdebug_list_sessions`

**Description:** List all active debugging sessions

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 112,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_list_sessions"
    }
}
```


---

### `xdebug_connect`

**Description:** Connect to Xdebug (will fail without active session)

**Arguments:**
```json
{
    "host": "127.0.0.1",
    "port": 9004
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 909,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_connect"
    }
}
```


---

### `xdebug_disconnect`

**Description:** Disconnect from Xdebug session

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 222,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_disconnect"
    }
}
```


---

### `xdebug_step_into`

**Description:** Step into next function call

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 29,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_step_into"
    }
}
```


---

### `xdebug_step_over`

**Description:** Step over current line

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 510,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_step_over"
    }
}
```


---

### `xdebug_step_out`

**Description:** Step out of current function

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 296,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_step_out"
    }
}
```


---

### `xdebug_continue`

**Description:** Continue execution until next breakpoint

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 337,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_continue"
    }
}
```


---

### `xdebug_set_breakpoint`

**Description:** Set breakpoint at file:line

**Arguments:**
```json
{
    "filename": "\/tmp\/test.php",
    "line": 10
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 282,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_set_breakpoint"
    }
}
```


---

### `xdebug_remove_breakpoint`

**Description:** Remove breakpoint by ID

**Arguments:**
```json
{
    "breakpoint_id": "1"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 955,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_remove_breakpoint"
    }
}
```


---

### `xdebug_list_breakpoints`

**Description:** List all active breakpoints

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 212,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_list_breakpoints"
    }
}
```


---

### `xdebug_get_stack`

**Description:** Get current stack trace

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 352,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_get_stack"
    }
}
```


---

### `xdebug_get_variables`

**Description:** Get variables in current context

**Arguments:**
```json
{
    "context": 0
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 925,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_get_variables"
    }
}
```


---

### `xdebug_eval`

**Description:** Evaluate PHP expression

**Arguments:**
```json
{
    "expression": "2 + 2"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 20,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_eval"
    }
}
```


---

### `xdebug_start_profiling`

**Description:** Start performance profiling

**Arguments:**
```json
{
    "output_file": "\/tmp\/profile.out"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 315,
    "error": {
        "code": -32000,
        "message": "Xdebug extension not loaded"
    }
}
```


---

### `xdebug_stop_profiling`

**Description:** Stop profiling and get results

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 602,
    "error": {
        "code": -32000,
        "message": "Xdebug extension not loaded"
    }
}
```


---

### `xdebug_get_profile_info`

**Description:** Get profiling configuration

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 365,
    "error": {
        "code": -32000,
        "message": "Xdebug extension not loaded"
    }
}
```


---

### `xdebug_start_coverage`

**Description:** Start code coverage tracking

**Arguments:**
```json
{
    "track_unused": true
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 107,
    "error": {
        "code": -32000,
        "message": "Xdebug extension not loaded"
    }
}
```


---

### `xdebug_stop_coverage`

**Description:** Stop code coverage tracking

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 126,
    "error": {
        "code": -32000,
        "message": "Xdebug extension not loaded"
    }
}
```


---

### `xdebug_get_coverage`

**Description:** Get code coverage data

**Arguments:**
```json
{
    "format": "summary"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 33,
    "error": {
        "code": -32000,
        "message": "Xdebug extension not loaded"
    }
}
```


---

### `xdebug_coverage_summary`

**Description:** Get coverage statistics

**Arguments:**
```json
{
    "coverage_data": []
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 115,
    "error": {
        "code": -32000,
        "message": "No coverage data available"
    }
}
```


---

### `xdebug_get_memory_usage`

**Description:** Get current memory usage

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 699,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Memory usage information:\n{\n    \"current_memory\": 1672256,\n    \"current_memory_real\": 4194304,\n    \"memory_limit\": \"1280M\"\n}"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_get_peak_memory_usage`

**Description:** Get peak memory usage

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 109,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Peak memory usage information:\n{\n    \"peak_memory\": 1958064,\n    \"peak_memory_real\": 4194304\n}"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_get_stack_depth`

**Description:** Get current stack depth

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 673,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Current stack depth: 5"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_get_time_index`

**Description:** Get execution time index

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 106,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Time information:\n{\n    \"start_time\": 1756352049.705075,\n    \"current_time\": 1756352049.709181,\n    \"elapsed_seconds\": 0.004106044769287109\n}"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_info`

**Description:** Get Xdebug configuration

**Arguments:**
```json
{
    "format": "array"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 60,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Xdebug extension not loaded"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_get_features`

**Description:** Get all available features

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 431,
    "error": {
        "code": -32000,
        "message": "Not connected to Xdebug"
    }
}
```


---

### `xdebug_set_feature`

**Description:** Set Xdebug feature

**Arguments:**
```json
{
    "feature_name": "max_depth",
    "value": "100"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 257,
    "error": {
        "code": -32000,
        "message": "Not connected to Xdebug"
    }
}
```


---

### `xdebug_get_feature`

**Description:** Get specific feature value

**Arguments:**
```json
{
    "feature_name": "max_depth"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 750,
    "error": {
        "code": -32000,
        "message": "Not connected to Xdebug"
    }
}
```


---

### `xdebug_start_error_collection`

**Description:** Start collecting PHP errors

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 824,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Custom error collection started"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_stop_error_collection`

**Description:** Stop error collection

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 647,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Custom error collection stopped. Collected 0 errors."
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_get_collected_errors`

**Description:** Get collected error messages

**Arguments:**
```json
{
    "clear": false
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 508,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "No errors collected"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_start_trace`

**Description:** Start function call tracing

**Arguments:**
```json
{
    "trace_file": "\/tmp\/trace.xt"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 193,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Custom trace started (file: /tmp/trace.xt)"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_stop_trace`

**Description:** Stop tracing and get data

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 317,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Custom trace stopped (file: )"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_get_tracefile_name`

**Description:** Get current trace filename

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 912,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "No trace file active"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_start_function_monitor`

**Description:** Monitor specific functions

**Arguments:**
```json
{
    "functions": [
        "strlen",
        "array_merge"
    ]
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 934,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Custom function monitor started for: strlen, array_merge"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_stop_function_monitor`

**Description:** Stop function monitoring

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 214,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Custom function monitor stopped. Monitored 0 calls:\n[]"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_set_exception_breakpoint`

**Description:** Set exception breakpoint

**Arguments:**
```json
{
    "exception_name": "Exception",
    "state": "all"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 674,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_set_exception_breakpoint"
    }
}
```


---

### `xdebug_set_watch_breakpoint`

**Description:** Set watch breakpoint

**Arguments:**
```json
{
    "expression": "$variable",
    "type": "write"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 364,
    "error": {
        "code": -32000,
        "message": "Unknown tool: xdebug_set_watch_breakpoint"
    }
}
```


---

### `xdebug_get_function_stack`

**Description:** Get detailed function stack

**Arguments:**
```json
{
    "include_args": true,
    "limit": 10
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 447,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Function stack:\n[\n    {\n        \"file\": \"\\/Users\\/akihito\\/git\\/xdebug-mcp\\/src\\/McpServer.php\",\n        \"line\": 622,\n        \"function\": \"getFunctionStack\",\n        \"class\": \"Koriym\\\\XdebugMcp\\\\McpServer\",\n        \"object\": {},\n        \"type\": \"->\",\n        \"args\": [\n            {\n                \"include_args\": true,\n                \"limit\": 10\n            }\n        ]\n    },\n    {\n        \"file\": \"\\/Users\\/akihito\\/git\\/xdebug-mcp\\/src\\/McpServer.php\",\n        \"line\": 526,\n        \"function\": \"executeToolCall\",\n        \"class\": \"Koriym\\\\XdebugMcp\\\\McpServer\",\n        \"object\": {},\n        \"type\": \"->\",\n        \"args\": [\n            \"xdebug_get_function_stack\",\n            {\n                \"include_args\": true,\n                \"limit\": 10\n            }\n        ]\n    },\n    {\n        \"file\": \"\\/Users\\/akihito\\/git\\/xdebug-mcp\\/src\\/McpServer.php\",\n        \"line\": 424,\n        \"function\": \"handleToolCall\",\n        \"class\": \"Koriym\\\\XdebugMcp\\\\McpServer\",\n        \"object\": {},\n        \"type\": \"->\",\n        \"args\": [\n            447,\n            {\n                \"name\": \"xdebug_get_function_stack\",\n                \"arguments\": {\n                    \"include_args\": true,\n                    \"limit\": 10\n                }\n            }\n        ]\n    },\n    {\n        \"file\": \"\\/Users\\/akihito\\/git\\/xdebug-mcp\\/src\\/McpServer.php\",\n        \"line\": 366,\n        \"function\": \"handleRequest\",\n        \"class\": \"Koriym\\\\XdebugMcp\\\\McpServer\",\n        \"object\": {},\n        \"type\": \"->\",\n        \"args\": [\n            {\n                \"jsonrpc\": \"2.0\",\n                \"id\": 447,\n                \"method\": \"tools\\/call\",\n                \"params\": {\n                    \"name\": \"xdebug_get_function_stack\",\n                    \"arguments\": {\n                        \"include_args\": true,\n                        \"limit\": 10\n                    }\n                }\n            }\n        ]\n    },\n    {\n        \"file\": \"\\/Users\\/akihito\\/git\\/xdebug-mcp\\/bin\\/xdebug-mcp\",\n        \"line\": 10,\n        \"function\": \"__invoke\",\n        \"class\": \"Koriym\\\\XdebugMcp\\\\McpServer\",\n        \"object\": {},\n        \"type\": \"->\",\n        \"args\": []\n    }\n]"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_print_function_stack`

**Description:** Print formatted stack trace

**Arguments:**
```json
{
    "message": "Debug Stack"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 934,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Debug Stack:\n#0 Koriym\\XdebugMcp\\McpServer::printFunctionStack() called at [/Users/akihito/git/xdebug-mcp/src/McpServer.php:625]\n#1 Koriym\\XdebugMcp\\McpServer::executeToolCall() called at [/Users/akihito/git/xdebug-mcp/src/McpServer.php:526]\n#2 Koriym\\XdebugMcp\\McpServer::handleToolCall() called at [/Users/akihito/git/xdebug-mcp/src/McpServer.php:424]\n#3 Koriym\\XdebugMcp\\McpServer::handleRequest() called at [/Users/akihito/git/xdebug-mcp/src/McpServer.php:366]\n#4 Koriym\\XdebugMcp\\McpServer::__invoke() called at [/Users/akihito/git/xdebug-mcp/bin/xdebug-mcp:10]\n"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

### `xdebug_call_info`

**Description:** Get calling context information

**Response:**
```json
{
    "jsonrpc": "2.0",
    "id": 756,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "Call information:\n{\n    \"current_function\": \"executeToolCall\",\n    \"current_class\": \"Koriym\\\\XdebugMcp\\\\McpServer\",\n    \"current_file\": \"\\/Users\\/akihito\\/git\\/xdebug-mcp\\/src\\/McpServer.php\",\n    \"current_line\": 526,\n    \"caller_function\": \"handleToolCall\",\n    \"caller_class\": \"Koriym\\\\XdebugMcp\\\\McpServer\",\n    \"caller_file\": \"\\/Users\\/akihito\\/git\\/xdebug-mcp\\/src\\/McpServer.php\",\n    \"caller_line\": 424\n}"
            }
        ]
    }
}
```

**Response Analysis:**
- Response contains plain text

---

## Response Format Analysis

### JSON-only Responses
**Pure JSON responses:**

**Mixed format responses (text + JSON):**
- `xdebug_get_memory_usage`
- `xdebug_get_peak_memory_usage`
- `xdebug_get_stack_depth`
- `xdebug_get_time_index`
- `xdebug_info`
- `xdebug_start_error_collection`
- `xdebug_stop_error_collection`
- `xdebug_get_collected_errors`
- `xdebug_start_trace`
- `xdebug_stop_trace`
- `xdebug_get_tracefile_name`
- `xdebug_start_function_monitor`
- `xdebug_stop_function_monitor`
- `xdebug_get_function_stack`
- `xdebug_print_function_stack`
- `xdebug_call_info`
