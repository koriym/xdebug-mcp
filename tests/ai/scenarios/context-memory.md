# Context Memory Scenarios

Test the 'last' functionality and context persistence across command executions.

## Scenario 1: Basic Context Memory
**Objective**: Verify that commands remember previous settings when using 'last'.

### Step 1: Execute Initial Command
```bash
# Execute x-trace with specific context and script
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"tests/fake/loop-counter.php","context":"Initial performance test"}}}' | php bin/xdebug-mcp
```

### Step 2: Use 'last' to Repeat
```bash
# Repeat with 'last' - should use same script and context
echo '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"x-trace","arguments":{"last":"true"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Second command should reuse script="tests/fake/loop-counter.php"
- Should reuse context="Initial performance test"
- Should execute identical command to first one
- Response should indicate that previous settings were used

**Evaluation Points:**
- Does 'last' functionality work as expected?
- Are the reused parameters clearly indicated?
- Is this feature intuitive and useful?

## Scenario 2: Partial Parameter Override
**Objective**: Test mixing 'last' with new parameters to override specific settings.

### Step 1: Execute Debug Command
```bash
# Execute x-debug with specific breakpoints and context
echo '{"jsonrpc":"2.0","id":3,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"tests/fake/array-manipulation.php","context":"Array debugging session","breakpoints":"tests/fake/array-manipulation.php:8,tests/fake/array-manipulation.php:14"}}}' | php bin/xdebug-mcp
```

### Step 2: Override Context Only
```bash
# Use 'last' but override the context
echo '{"jsonrpc":"2.0","id":4,"method":"prompts/get","params":{"name":"x-debug","arguments":{"last":"true","context":"Modified debugging context"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should reuse script and breakpoints from previous command
- Should use new context="Modified debugging context"
- Should maintain all other previous settings
- Response should show the merged parameters

**Evaluation Points:**
- Does parameter merging work correctly?
- Is it clear which parameters were reused vs. overridden?
- Is this behavior intuitive?

## Scenario 3: Cross-Command Memory Isolation
**Objective**: Verify that different commands maintain separate memory contexts.

### Step 1: Execute x-trace
```bash
echo '{"jsonrpc":"2.0","id":5,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"tests/fake/loop-counter.php","context":"Trace context"}}}' | php bin/xdebug-mcp
```

### Step 2: Execute x-profile
```bash
echo '{"jsonrpc":"2.0","id":6,"method":"prompts/get","params":{"name":"x-profile","arguments":{"script":"tests/fake/array-manipulation.php","context":"Profile context"}}}' | php bin/xdebug-mcp
```

### Step 3: Use 'last' on x-trace
```bash
echo '{"jsonrpc":"2.0","id":7,"method":"prompts/get","params":{"name":"x-trace","arguments":{"last":"true"}}}' | php bin/xdebug-mcp
```

### Step 4: Use 'last' on x-profile
```bash
echo '{"jsonrpc":"2.0","id":8,"method":"prompts/get","params":{"name":"x-profile","arguments":{"last":"true"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- x-trace 'last' should use loop-counter.php and "Trace context"
- x-profile 'last' should use array-manipulation.php and "Profile context"
- Each command should maintain its own memory independently
- No cross-contamination between command memories

**Evaluation Points:**
- Are command memories properly isolated?
- Can you use 'last' reliably for different commands?
- Is the behavior predictable and logical?

## Scenario 4: Memory Persistence Across Sessions
**Objective**: Test if context memory persists across MCP server restarts (if implemented).

### Step 1: Execute Command and Stop Server
```bash
# Execute command
echo '{"jsonrpc":"2.0","id":9,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"tests/fake/loop-counter.php","context":"Persistence test","breakpoints":"tests/fake/loop-counter.php:5"}}}' | php bin/xdebug-mcp

# (Server stops after response)
```

### Step 2: Start New Server Session and Test 'last'
```bash
# Try to use 'last' in new session
echo '{"jsonrpc":"2.0","id":10,"method":"prompts/get","params":{"name":"x-debug","arguments":{"last":"true"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- If persistence is implemented: should reuse previous settings
- If not implemented: should provide clear error message
- Behavior should be documented and predictable

**Evaluation Points:**
- Is persistence behavior clearly documented?
- If not persistent, is the error message helpful?
- Would persistence be valuable for this use case?

## Scenario 5: Complex Parameter Memory
**Objective**: Test memory with complex parameter combinations.

### Step 1: Execute Complex x-coverage Command
```bash
echo '{"jsonrpc":"2.0","id":11,"method":"prompts/get","params":{"name":"x-coverage","arguments":{"script":"vendor/bin/phpunit tests/Unit/","context":"Comprehensive unit test coverage","format":"json"}}}' | php bin/xdebug-mcp
```

### Step 2: Modify Single Parameter
```bash
echo '{"jsonrpc":"2.0","id":12,"method":"prompts/get","params":{"name":"x-coverage","arguments":{"last":"true","format":"html"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should maintain script and context from previous command
- Should override format from "json" to "html"
- Should execute with merged parameters
- Response should clearly show the effective parameters used

**Evaluation Points:**
- Does complex parameter merging work correctly?
- Are all parameter types (strings, formats, etc.) handled properly?
- Is the final command construction clear and correct?

## Success Criteria

### Memory Functionality (Pass/Fail)
- [ ] Basic 'last' functionality works
- [ ] Partial parameter override works correctly
- [ ] Command memory isolation is maintained
- [ ] Complex parameter merging works
- [ ] Persistence behavior is predictable

### User Experience (1-5 Rating)
- **Intuitiveness**: How intuitive is the 'last' feature?
- **Reliability**: Can you depend on it working consistently?
- **Usefulness**: How useful is this feature in practice?
- **Clarity**: Is it clear what parameters are being reused?

### AI Workflow Enhancement (1-5 Rating)
- **Efficiency**: Does this speed up iterative debugging?
- **Convenience**: Does this reduce repetitive parameter entry?
- **Predictability**: Can you rely on the behavior?
- **Value**: Is this feature worth the complexity?

## Notes for Fresh Claude

Focus on evaluating:
- **Does this feature feel natural to use?**
- **Would you use 'last' in real debugging scenarios?**
- **Are there edge cases or confusing behaviors?**
- **How could the UX be improved?**

This feature is designed to support iterative debugging workflows where you might want to run similar commands with slight modifications. Evaluate whether it achieves that goal effectively.