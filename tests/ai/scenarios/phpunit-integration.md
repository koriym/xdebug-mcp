# PHPUnit Integration Scenarios

Test integration between xdebug-mcp slash commands and PHPUnit testing workflows.

## Scenario 1: Composer x-test Command
**Objective**: Test the new `composer x-test` command functionality.

### Test Basic x-test Usage
```bash
# Test without arguments - should run standard PHPUnit
composer x-test

# Test with help to see options
composer x-test --help
```

**Expected Behavior:**
- Without arguments: runs standard PHPUnit (no Xdebug overhead)
- With --help: shows xdebug-phpunit wrapper options
- Should be faster than Xdebug-enabled runs for regular testing

**Evaluation Points:**
- Is the performance difference noticeable?
- Is the command intuitive to use?
- Are the help messages clear?

### Test x-test with Specific Tests
```bash
# Test specific test file
composer x-test tests/Unit/McpServerTest.php

# Test specific test method
composer x-test tests/Unit/McpServerTest.php::testConstructorWithValidScript

# Test with pattern filtering
composer x-test --filter=testConnect
```

**Expected Behavior:**
- Should enable Xdebug tracing for specific tests only
- Should provide trace/debug information for targeted tests
- Should avoid overhead for non-targeted tests

**Evaluation Points:**
- Does selective Xdebug activation work correctly?
- Is the tracing information useful for test debugging?
- Is the performance impact reasonable?

## Scenario 2: Test Failure Debugging Workflow
**Objective**: Test the complete workflow for debugging failing tests.

### Step 1: Identify Failing Test
```bash
# Run tests to identify failures
composer x-test --stop-on-failure
```

### Step 2: Debug First Failure
```bash
# Debug the failing test with MCP slash command
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"vendor/bin/phpunit --stop-on-failure tests/Unit/FailingTest.php","context":"First test failure debugging"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should stop at first test failure
- Should provide detailed debugging information about the failure
- Should show test execution context and variable states
- Should help identify why the test is failing

**Evaluation Points:**
- Can you quickly identify the test failure cause?
- Is the debugging information sufficient to fix the test?
- Does this workflow feel natural and efficient?
- Would you use this approach for real test debugging?

## Scenario 3: Performance Testing Integration
**Objective**: Test performance profiling of slow tests.

### Test Performance Profiling
```bash
# Profile potentially slow tests
echo '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"x-profile","arguments":{"script":"vendor/bin/phpunit tests/Performance/SlowTest.php","context":"Test performance analysis"}}}' | php bin/xdebug-mcp
```

### Compare with Direct Profiling
```bash
# Also test direct profiling approach
composer x-test --profile tests/Performance/SlowTest.php
```

**Expected Behavior:**
- Should identify performance bottlenecks in test execution
- Should provide timing information for test methods
- Should help optimize slow-running tests
- Both approaches should provide similar insights

**Evaluation Points:**
- Are test performance bottlenecks clearly identified?
- Can you prioritize which tests need optimization?
- Is the profiling data actionable for test improvement?
- Which approach (MCP slash command vs. direct) is more useful?

## Scenario 4: Code Coverage Analysis
**Objective**: Test code coverage analysis through different approaches.

### Test Coverage via MCP
```bash
# Test coverage through x-coverage slash command
echo '{"jsonrpc":"2.0","id":3,"method":"prompts/get","params":{"name":"x-coverage","arguments":{"script":"vendor/bin/phpunit tests/Unit/McpServerTest.php","context":"Unit test coverage analysis","format":"json"}}}' | php bin/xdebug-mcp
```

### Test Coverage via Composer
```bash
# Test traditional coverage approach
composer coverage
```

**Expected Behavior:**
- Should provide comprehensive coverage statistics
- Should identify uncovered code sections
- Should integrate well with PHPUnit test execution
- Results should be comparable between approaches

**Evaluation Points:**
- Is the coverage information comprehensive and accurate?
- Can you easily identify what code needs more testing?
- Is the JSON format useful for AI analysis?
- Which approach provides better insights?

## Scenario 5: Test Development Workflow
**Objective**: Test the complete TDD/debugging workflow using xdebug-mcp.

### TDD Cycle with xdebug-mcp
```bash
# Step 1: Run failing test
composer x-test --stop-on-failure tests/Unit/NewFeatureTest.php

# Step 2: Debug the failure
echo '{"jsonrpc":"2.0","id":4,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"vendor/bin/phpunit --stop-on-failure tests/Unit/NewFeatureTest.php::testNewFeature","context":"TDD development debugging","breakpoints":"src/NewFeature.php:15"}}}' | php bin/xdebug-mcp

# Step 3: After implementing, verify with trace
echo '{"jsonrpc":"2.0","id":5,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"vendor/bin/phpunit tests/Unit/NewFeatureTest.php::testNewFeature","context":"Verify implementation"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should support iterative development workflow
- Should provide insights at each stage of TDD cycle
- Should help understand both failures and successes
- Should integrate smoothly with development process

**Evaluation Points:**
- Does this enhance the TDD workflow?
- Is the debugging information useful at each stage?
- Would you adopt this workflow for real development?
- Are there workflow improvements you'd suggest?

## Scenario 6: Continuous Integration Considerations
**Objective**: Evaluate suitability for CI/CD environments.

### Test CI-Friendly Usage
```bash
# Test headless/automated usage
echo '{"jsonrpc":"2.0","id":6,"method":"prompts/get","params":{"name":"x-coverage","arguments":{"script":"vendor/bin/phpunit --testsuite=unit","context":"CI coverage analysis","format":"json"}}}' | php bin/xdebug-mcp

# Test failure analysis in CI context
echo '{"jsonrpc":"2.0","id":7,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"vendor/bin/phpunit --stop-on-failure --log-junit=results.xml","context":"CI failure analysis"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Should work in headless/automated environments
- Should provide machine-readable output suitable for CI
- Should integrate with existing CI/CD workflows
- Should not require interactive input

**Evaluation Points:**
- Would this be useful in CI/CD pipelines?
- Is the output suitable for automated processing?
- Are there CI-specific features that would be valuable?
- How would this integrate with existing CI tools?

## Integration Assessment

### PHPUnit Compatibility (Pass/Fail)
- [ ] Works with standard PHPUnit commands
- [ ] Supports PHPUnit options (--filter, --stop-on-failure, etc.)
- [ ] Maintains PHPUnit output formatting when appropriate
- [ ] Doesn't interfere with PHPUnit's normal operation

### Workflow Enhancement (1-5 Rating)
- **Development Speed**: Does this accelerate development?
- **Debugging Efficiency**: Faster problem identification?
- **Test Quality**: Helps write better tests?
- **Adoption Ease**: Easy to integrate into existing workflows?

### Real-World Applicability (1-5 Rating)
- **Daily Use**: Would you use this for regular development?
- **Team Adoption**: Would your team find this valuable?
- **CI/CD Integration**: Suitable for automated environments?
- **Learning Curve**: Reasonable effort to adopt?

## Success Criteria

A successful PHPUnit integration should:
- **Enhance rather than complicate** existing testing workflows
- **Provide actionable insights** for test failures and performance
- **Integrate seamlessly** with standard PHPUnit usage patterns  
- **Support both interactive and automated** usage scenarios
- **Deliver clear value** over traditional debugging approaches

## Notes for Fresh Claude

Focus your evaluation on:
- **Practical utility**: Would you actually use this for test debugging?
- **Workflow fit**: Does this integrate naturally with your development process?
- **Value proposition**: Is this better than traditional debugging methods?
- **Missing features**: What additional PHPUnit integration would be valuable?

Consider both individual developer usage and team/CI adoption scenarios.