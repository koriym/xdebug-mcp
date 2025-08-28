#!/bin/bash

# Manual MCP Tools Test Script
# Run this script manually to test individual MCP tools

echo "=== MCP Tools Manual Test Script ==="
echo "Usage: Run each command manually to test specific tools"
echo ""

echo "1. List available tools:"
echo 'echo '"'"'{"jsonrpc":"2.0","id":1,"method":"tools/list"}'"'"' | php bin/xdebug-mcp'
echo ""

echo "2. Test session management:"
echo 'echo '"'"'{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_list_sessions","arguments":{}}}'"'"' | php bin/xdebug-mcp'
echo ""

echo "3. Test connection (will show error format):"
echo 'echo '"'"'{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"xdebug_connect","arguments":{"host":"127.0.0.1","port":9004}}}'"'"' | php bin/xdebug-mcp'
echo ""

echo "4. Test step debugging (will show error format):"
echo 'echo '"'"'{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"xdebug_step_into","arguments":{}}}'"'"' | php bin/xdebug-mcp'
echo ""

echo "5. Test memory usage:"
echo 'echo '"'"'{"jsonrpc":"2.0","id":5,"method":"tools/call","params":{"name":"xdebug_get_memory_usage","arguments":{}}}'"'"' | php bin/xdebug-mcp'
echo ""

echo "6. Test Xdebug info:"
echo 'echo '"'"'{"jsonrpc":"2.0","id":6,"method":"tools/call","params":{"name":"xdebug_info","arguments":{"format":"array"}}}'"'"' | php bin/xdebug-mcp'
echo ""

echo "7. Test profiling (standalone):"
echo 'echo '"'"'{"jsonrpc":"2.0","id":7,"method":"tools/call","params":{"name":"xdebug_start_profiling","arguments":{}}}'"'"' | php bin/xdebug-mcp'
echo ""

echo "8. Test coverage:"
echo 'echo '"'"'{"jsonrpc":"2.0","id":8,"method":"tools/call","params":{"name":"xdebug_start_coverage","arguments":{}}}'"'"' | php bin/xdebug-mcp'
echo ""

echo "=== Key Tools to Test Manually ==="
echo ""
echo "# Session Management (Pure JSON)"
echo 'echo '"'"'{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"xdebug_list_sessions","arguments":{}}}'"'"' | php bin/xdebug-mcp | jq .'
echo ""
echo "# Memory Usage (Text + JSON)"
echo 'echo '"'"'{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"xdebug_get_memory_usage","arguments":{}}}'"'"' | php bin/xdebug-mcp | jq .'
echo ""
echo "# Error Format Test"
echo 'echo '"'"'{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"xdebug_step_into","arguments":{}}}'"'"' | php bin/xdebug-mcp | jq .'
echo ""

echo "Copy and paste these commands to test manually."