# 🤖 AI Autonomous Testing: Comprehensive Verification of All 47 Features

## Task
Autonomously test all XdebugMCP server features (42 MCP tools + 5 CLI tools = 47 features) and report the operational status of each feature.

## Critical Instructions
- **Execute all features yourself**
- **Judge based on actual execution results**  
- **Speculation or assumption-based judgments are FORBIDDEN**
- Report each feature as: "✅ Success" "❌ Failure" "⚠️ Partial Success" "🚫 Unimplemented"

## Target Feature List

### 📊 Profiling & Performance (4 tools)
1. **xdebug_start_profiling** - Start profiling
2. **xdebug_stop_profiling** - Stop profiling  
3. **xdebug_get_profile_info** - Get profiling information
4. **xdebug_analyze_profile** - Analyze Cachegrind files

### 📈 Code Coverage (6 tools)  
5. **xdebug_start_coverage** - Start coverage measurement
6. **xdebug_stop_coverage** - Stop coverage measurement
7. **xdebug_get_coverage** - Get coverage data
8. **xdebug_analyze_coverage** - Generate coverage reports
9. **xdebug_coverage_summary** - Coverage summary
10. **xdebug_get_coverage_info** - Get coverage configuration

### 🐛 Interactive Debugging (11 tools)
11. **xdebug_connect** - Connect to debug session
12. **xdebug_disconnect** - Disconnect debug session
13. **xdebug_set_breakpoint** - Set breakpoint
14. **xdebug_remove_breakpoint** - Remove breakpoint
15. **xdebug_step_into** - Step into execution
16. **xdebug_step_over** - Step over execution  
17. **xdebug_step_out** - Step out execution
18. **xdebug_continue** - Continue execution
19. **xdebug_get_stack** - Get stack trace
20. **xdebug_get_variables** - Get variable values
21. **xdebug_eval** - Evaluate expression

### 🔍 Trace Analysis (4 tools)
22. **xdebug_start_trace** - Start tracing
23. **xdebug_stop_trace** - Stop tracing
24. **xdebug_get_trace_info** - Get trace configuration
25. **xdebug_analyze_trace** - Analyze trace files

### ⚙️ Configuration & Diagnostics (17 tools)
26. **xdebug_get_status** - Get server status
27. **xdebug_get_error_info** - Get error information
28. **xdebug_get_memory_usage** - Get memory usage
29. **xdebug_get_execution_time** - Get execution time
30. **xdebug_get_stack_depth** - Get stack depth
31. **xdebug_list_breakpoints** - List breakpoints
32. **xdebug_set_exception_breakpoint** - Set exception breakpoint
33. **xdebug_set_watch_breakpoint** - Set watch breakpoint
34. **xdebug_get_features** - Get feature list
35. **xdebug_set_feature** - Set feature configuration
36. **xdebug_get_feature** - Get feature configuration
37. **xdebug_collect_errors** - Collect errors
38. **xdebug_get_collected_errors** - Get collected errors
39. **xdebug_clear_collected_errors** - Clear error list
40. **xdebug_get_function_trace** - Get function trace
41. **xdebug_call_info** - Get call information
42. **xdebug_print_function_stack** - Print function stack

### 🛠️ CLI Tools (5 tools)
43. **./bin/xdebug-debug** - Interactive debugging
44. **./bin/xdebug-profile** - Profiling execution
45. **./bin/xdebug-coverage** - Coverage analysis
46. **./bin/xdebug-trace** - Trace execution
47. **./bin/xdebug-phpunit** - PHPUnit integration

## Test Procedures

### Phase 1: Basic Feature Testing

**⚠️ Critical Restrictions**
- **Direct JSON-RPC command execution is FORBIDDEN** (e.g., `echo '{"jsonrpc":...}' | php bin/xdebug-mcp`)
- This only tests "command sending success," not "feature operation success"
- **Use MCP tools ONLY when available**
- If MCP tools are unavailable, use alternative methods (CLI tools)

**Correct Testing Methods:**
```bash
# 1. Check if MCP tools are available
# If MCP tools are accessible:
mcp__xdebug__xdebug_start_profiling

# 2. If MCP unavailable, test with CLI tools
./bin/xdebug-profile test/debug_test.php

# 3. Verify actual results (CRITICAL!)
ls -la /tmp/*profile*  # Confirm profile files are generated
```

### Phase 2: CLI Tools Testing  
```bash
# 43-47: Execute CLI tools directly
./bin/xdebug-profile test/debug_test.php
./bin/xdebug-coverage test/debug_test.php  
./bin/xdebug-trace test/debug_test.php
# etc...
```

### Phase 3: Interactive Debugging Testing

**🚨 CRITICAL: Correct Procedure for Interactive Debugging Testing**

Interactive Debugging (11 tools) requires special connection sequencing:

```bash
# ✅ Correct procedure (MANDATORY!)
# Step 1: Start XdebugClient first (MUST BE FIRST!)
php test_new_xdebug_debug.php &

# Step 2: Verify port is listening
lsof -i :9004  # Confirm LISTEN status

# Step 3: Execute script using ./bin/xdebug-debug
./bin/xdebug-debug test-scripts/buggy_calculation_code.php
```

**Expected Success Signs:**
- `✅ Connected to debugging session!`
- `Breakpoint ID: XXXXXXX` (successful breakpoint setting)
- `🔧 Variables at breakpoint:` (successful variable inspection)
- `▶️ Continuing to breakpoint...` (successful continue execution)
- `👣 Step over the condition...` (successful step execution)

## Report Format

Report each feature in the following format:

```
## Test Results Report

### 📊 Profiling & Performance (4/4)
✅ xdebug_start_profiling - Success: Profile file /tmp/cachegrind.out.12345 generated
❌ xdebug_stop_profiling - Failure: connection timeout error, no profile file generated
⚠️ xdebug_get_profile_info - Partial Success: Configuration data retrieved but actual profiling execution failed
🚫 xdebug_analyze_profile - Unimplemented: MCP tool itself does not exist

**Verification Method:** Actual file generation, data retrieval, and operation confirmation performed for each feature

### 📈 Code Coverage (X/6)
...

### 🐛 Interactive Debugging (X/11)  
...

### 🔍 Trace Analysis (X/4)
...

### ⚙️ Configuration & Diagnostics (X/17)
...

### 🛠️ CLI Tools (X/5)
...

## Overall Results
- ✅ Success: XX/47 (XX%)
- ❌ Failure: XX/47 (XX%) 
- ⚠️ Partial Success: XX/47 (XX%)
- 🚫 Unimplemented: XX/47 (XX%)

## Critical Discoveries
- [Actually discovered issues]
- [Recommended improvements]
- [Confirmed working major features]
```

## Success Criteria
- **Complete Testing**: Actually execute all 47 features
- **Accurate Judgment**: Evaluation based on actual results, not speculation
- **Detailed Reports**: Specific operational results for each feature
- **Improvement Proposals**: Solutions for discovered issues

## 🚨 Troubleshooting for Interactive Debugging Test Failures

If Interactive Debugging (11 tools) tests result in "partial success" or "failure," check the following:

### Problem Diagnosis Checklist

**❌ Common Mistakes:**
```bash
# ❌ Mistake 1: Testing MCP tools directly
mcp__xdebug__xdebug_connect  # This alone is insufficient

# ❌ Mistake 2: Executing script directly  
XDEBUG_TRIGGER=1 php -dxdebug.mode=debug test.php  # No connection target

# ❌ Mistake 3: Wrong sequence
./bin/xdebug-debug test.php  # XdebugClient not started
php test_new_xdebug_debug.php &
```

**✅ Correct Diagnostic Method:**
```bash
# 1. Connection check: Is XdebugClient actually running?
php test_new_xdebug_debug.php &
sleep 2
lsof -i :9004  # <- MANDATORY: Does LISTEN appear?

# 2. Execute actual debug session
./bin/xdebug-debug test-scripts/buggy_calculation_code.php

# 3. Confirm success signs
# - Does "✅ Connected to debugging session!" appear?
# - Does "Breakpoint ID:" appear?
# - Do variable values appear?
```

### Success Criteria Reconfirmation

**Conditions for Interactive Debugging (11 tools) to achieve ✅ Success:**
1. **xdebug_connect**: "Xdebug connected! Connection established successfully" displayed
2. **xdebug_set_breakpoint**: "Breakpoint ID: XXXXXXX" successful breakpoint setting  
3. **xdebug_get_variables**: "$variable_name (type): value" successful variable value display
4. **xdebug_continue**: "Continuing to breakpoint..." successful continue execution
5. **xdebug_step_over**: "Step over the condition..." successful step execution
6. Other 6 features similarly require actual operation confirmation

**⚠️ Important**: 
- Confirming MCP tool name existence only results in "partial success"
- Actual debugging session operation confirmation achieves "complete success"
- Speculation or assumption-based judgments are forbidden

**IMPORTANT**: This test will reveal the **true implementation completeness** of the XdebugMCP server. This is the final verification of whether AI can actually master all 47 features.

Good luck! 🚀