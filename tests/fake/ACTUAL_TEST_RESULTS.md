# MCP Tools - ACTUAL Test Results

**Generated**: 2025-08-27  
**Method**: Manual testing of each tool individually  
**Total Tools**: See runtime `tools/list` output (do not hard-code)

**IMPORTANT**: All responses below are ACTUAL results from running the MCP server, not estimations or guesses.

---

## Test 1/43: tools/list

**Command:**
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php bin/xdebug-mcp | jq .
```

**Result:**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "tools": [
      // ... 43 tools listed with full schemas
    ]
  }
}
```
**Status**: ✅ SUCCESS - 43 tools available

---

## Test 2/43: xdebug_list_sessions

**Command:**
```bash
echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_list_sessions","arguments":{}}}' | php bin/xdebug-mcp | jq .
```

**Result:**