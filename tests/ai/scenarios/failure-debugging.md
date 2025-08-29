# Failure Debugging Scenarios

Test debugging capabilities focused on identifying and analyzing failures, particularly the proposed `--trace-only-failure` functionality.

## Scenario 1: PHPUnit Test Failure Analysis
**Objective**: Test debugging failing PHPUnit tests using Forward Trace methodology.

### Setup: Create a Failing Test
First, verify there's a test that fails:
```bash
# Run tests to identify any failing tests
vendor/bin/phpunit --stop-on-failure
```

### Test x-debug with PHPUnit Failure
```bash
# Debug first failure with context
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"vendor/bin/phpunit --stop-on-failure tests/","context":"First test failure analysis"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should capture execution up to first test failure
- Should provide trace data showing failure point
- Should include variable states at failure
- Should give actionable debugging information

**Evaluation Points:**
- Can you identify why the test failed from the trace?
- Is the failure point clearly highlighted?
- Are variable states at failure useful?
- Would this help you fix the failing test?

## Scenario 2: PHP Script Error Debugging
**Objective**: Debug a PHP script that contains intentional errors.

### Test with Syntax Error
```bash
# Debug script with parse error (if available)
echo '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"tests/fake/buggy-script.php","context":"Syntax error debugging"}}}' | php bin/xdebug-mcp
```

### Test with Runtime Error
```bash
# Debug script with runtime error
echo '{"jsonrpc":"2.0","id":3,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"tests/fake/division-by-zero.php","context":"Runtime error analysis","breakpoints":"auto-detect"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should handle error conditions gracefully
- Should provide useful error context
- Should show execution leading up to error
- Should suggest potential fixes

**Evaluation Points:**
- Are error messages helpful and actionable?
- Can you understand what caused the error?
- Is the error context sufficient for debugging?
- Would this help you fix the bug quickly?

## Scenario 3: Performance Problem Identification
**Objective**: Use profiling to identify performance bottlenecks in slow code.

### Test Performance Analysis
```bash
# Profile potentially slow script
echo '{"jsonrpc":"2.0","id":4,"method":"prompts/get","params":{"name":"x-profile","arguments":{"script":"tests/fake/slow-algorithm.php","context":"Performance bottleneck identification"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should identify slow functions/methods
- Should provide timing information
- Should suggest optimization opportunities
- Should highlight performance hotspots

**Evaluation Points:**
- Are performance bottlenecks obvious from the output?
- Can you prioritize which optimizations to make first?
- Is the timing data actionable?
- Would this help improve performance effectively?

## Scenario 4: Logic Error Investigation
**Objective**: Debug subtle logic errors that produce wrong results.

### Test Logic Flow Analysis
```bash
# Debug script with logic error
echo '{"jsonrpc":"2.0","id":5,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"tests/fake/wrong-calculation.php","context":"Logic error investigation","breakpoints":"tests/fake/wrong-calculation.php:15,tests/fake/wrong-calculation.php:25"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should show variable evolution through execution
- Should highlight where values become incorrect
- Should provide execution flow clarity
- Should help identify the faulty logic

**Evaluation Points:**
- Can you pinpoint where the logic goes wrong?
- Are variable states tracked clearly?
- Is the execution flow easy to follow?
- Would this help you understand and fix the bug?

## Scenario 5: Integration Test Failure
**Objective**: Debug complex integration test failures with multiple components.

### Test Complex Failure Scenario
```bash
# Debug integration test failure
echo '{"jsonrpc":"2.0","id":6,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"vendor/bin/phpunit --stop-on-failure tests/Integration/","context":"Integration test failure analysis"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should handle complex test scenarios
- Should isolate failure to specific components
- Should show interaction between components
- Should provide focused debugging data

**Evaluation Points:**
- Can you isolate which component is failing?
- Is the component interaction visible?
- Are external dependencies clearly handled?
- Would this help debug integration issues?

## Scenario 6: Edge Case and Boundary Testing
**Objective**: Test debugging behavior with edge cases and unusual inputs.

### Test with Empty/Null Inputs
### Test with Very Long Context
```bash
# Test with extremely long context string
echo '{"jsonrpc":"2.0","id":8,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"tests/fake/loop-counter.php","context":"This is an extremely long context description that tests how the system handles verbose context information that might be provided by users who want to give detailed background about what they are trying to debug and analyze in their code execution flow"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should handle edge cases gracefully
- Should provide clear error messages for invalid inputs
- Should not crash or hang on unusual inputs
- Should maintain functionality despite edge cases

**Evaluation Points:**
- Are error messages clear and helpful?
- Does the system handle edge cases gracefully?
- Are there any unexpected behaviors?
- Is the robustness adequate for real-world use?

## Forward Trace™ Methodology Evaluation

For each failure debugging scenario, specifically evaluate:

### Non-Invasive Analysis
- [ ] No need to modify source code to debug
- [ ] Can analyze failures without adding debug statements
- [ ] Maintains original code integrity during debugging

### AI-First Design
- [ ] Output is structured for AI consumption
- [ ] Information is focused and actionable
- [ ] Reduces noise and irrelevant data
- [ ] Enables quick problem identification

### Focus and Efficiency
- [ ] Provides targeted information about failure points
- [ ] Avoids information overload
- [ ] Enables quick time-to-insight
- [ ] Supports iterative debugging workflow

## Success Criteria

### Failure Detection (Pass/Fail)
- [ ] Successfully identifies failure points
- [ ] Provides actionable debugging information
- [ ] Shows execution context leading to failure
- [ ] Suggests potential solutions or next steps

### Debugging Effectiveness (1-5 Rating)
- **Speed**: How quickly can you identify the problem?
- **Accuracy**: How precisely does it pinpoint issues?
- **Completeness**: Does it provide sufficient context?
- **Actionability**: Can you fix the problem with this information?

### Forward Trace™ Methodology (1-5 Rating)
- **Non-Invasiveness**: No code modification required?
- **AI-Friendliness**: Optimized for AI analysis?
- **Focus**: Avoids information overload?
- **Efficiency**: Quick problem identification?

## Notes for Fresh Claude

When evaluating failure debugging scenarios:

- **Focus on real-world applicability**: Would this help in actual development?
- **Compare to traditional debugging**: Is this better than adding print statements?
- **Evaluate AI-friendliness**: Can you quickly understand and act on the information?
- **Consider workflow integration**: Does this fit naturally into development process?
- **Note missing features**: What additional information would be helpful?

Remember: The goal is to make debugging failures faster and more effective through AI-optimized trace analysis rather than manual stepping through code.