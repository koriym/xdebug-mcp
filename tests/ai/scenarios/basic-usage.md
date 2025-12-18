# Basic Usage Scenarios

Test the fundamental functionality of MCP slash commands.

## Scenario 1: Command Discovery
**Objective**: Verify that Fresh Claude can discover available slash commands.

```bash
# Test MCP prompts list
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/list"}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Returns JSON with 4 prompts: x-trace, x-debug, x-profile, x-coverage
- Each prompt has clear description and required/optional arguments
- Response is well-formatted and comprehensible

**Evaluation Points:**
- Are command names intuitive with x- prefix?
- Are descriptions helpful for understanding purpose?
- Are argument specifications clear?

## Scenario 2: Simple Trace Execution
**Objective**: Execute basic trace command on a simple script.

```bash
# Test x-trace with loop counter
echo '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"tests/fake/loop-counter.php","context":"Basic trace test"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Command executes without errors
- Returns structured response with execution data
- Context is preserved in response
- Output includes trace information in readable format

**Evaluation Points:**
- Is the JSON response structure intuitive?
- Can you understand what the script did from the trace?
- Are error messages (if any) helpful?

## Scenario 3: Debug with Breakpoints
**Objective**: Test debugging functionality with specific breakpoints.

```bash
# Test x-debug with breakpoint
echo '{"jsonrpc":"2.0","id":3,"method":"prompts/get","params":{"name":"x-debug","arguments":{"script":"tests/fake/array-manipulation.php","context":"Debug array operations","breakpoints":"tests/fake/array-manipulation.php:10"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Sets breakpoint at specified location
- Captures variable states at breakpoint
- Provides execution flow leading to breakpoint
- Returns JSON with debug data

**Evaluation Points:**
- Is breakpoint syntax intuitive?
- Can you see variable values at the breakpoint?
- Does the execution flow make sense?

## Scenario 4: Performance Profile
**Objective**: Test profiling capability on a performance-sensitive script.

```bash
# Test x-profile
echo '{"jsonrpc":"2.0","id":4,"method":"prompts/get","params":{"name":"x-profile","arguments":{"script":"tests/fake/loop-counter.php","context":"Performance analysis test"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Generates performance profile data
- Identifies function call costs
- Provides actionable performance insights
- Returns structured profiling results

**Evaluation Points:**
- Are performance bottlenecks obvious?
- Is the profiling data useful for optimization?
- Can you understand which functions are expensive?

## Scenario 5: Code Coverage Analysis
**Objective**: Test coverage analysis on test files.

```bash
# Test x-coverage with PHPUnit
echo '{"jsonrpc":"2.0","id":5,"method":"prompts/get","params":{"name":"x-coverage","arguments":{"script":"vendor/bin/phpunit tests/Unit/McpServerTest.php","context":"Coverage analysis test"}}}' | php bin/xdebug-mcp
```

**Expected Behavior:**
- Runs coverage analysis on specified tests
- Reports line/branch/function coverage
- Identifies uncovered code sections
- Provides coverage statistics

**Evaluation Points:**
- Are coverage statistics clear and actionable?
- Can you identify what code needs more testing?
- Is the coverage report comprehensive?

## Success Criteria

For each scenario, evaluate:

### Functionality (Pass/Fail)
- [ ] Command executes without critical errors
- [ ] Returns expected JSON structure
- [ ] Provides meaningful debugging/analysis data
- [ ] Context is preserved and displayed appropriately

### Usability (1-5 Rating)
- **Clarity**: How clear is the output?
- **Usefulness**: How useful is the information provided?
- **Efficiency**: How quickly can you get insights?
- **Learnability**: How easy is it to understand and use?

### AI Analysis Quality (1-5 Rating)
- **Comprehension**: Can you understand what the code did?
- **Problem Detection**: Can you spot issues or inefficiencies?
- **Actionability**: Do you know what to do with the information?
- **Focus**: Is the information focused and relevant?

## Notes for Fresh Claude

- **Document every step**: Record your thought process
- **Note confusion**: Any unclear aspects are valuable feedback
- **Compare expectations**: What did you expect vs. what you got?
- **Suggest improvements**: How could this be better?
- **Rate honestly**: Your unbiased perspective is crucial

Remember: You are evaluating not just if it works, but if it's genuinely helpful for AI-driven debugging and analysis.