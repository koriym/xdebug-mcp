# AI Evaluation Framework for Xdebug MCP Slash Commands

This directory contains evaluation materials for testing the AI-friendliness and effectiveness of the xdebug-mcp slash commands implementation.

## 🎯 Purpose

Evaluate the MCP slash commands (`/x-debug`, `/x-trace`, `/x-profile`, `/x-coverage`) from a **fresh Claude perspective** to identify:
- Usability issues
- Documentation gaps  
- AI analysis quality
- Forward Trace™ debugging effectiveness

## 🧠 Why Fresh Claude Evaluation?

**Implementation Bias Problem:**
- The implementing Claude knows internal details
- Expects specific behaviors 
- Unconsciously works around issues
- Cannot provide genuine user experience feedback

**Fresh Claude Benefits:**
- True user perspective
- Documentation-only understanding
- Discovers unexpected usage patterns
- Provides honest error reactions

## 📋 Evaluation Instructions

### Step 1: Launch Fresh Claude Session
1. Open a **new Claude conversation** (critical - do not use existing context)
2. Navigate to the xdebug-mcp project directory
3. Read only the main README.md initially

### Step 2: Setup Attempt
1. Try to understand the project from README.md alone
2. Attempt MCP server setup following documentation
3. **Document any confusion or missing steps**

### Step 3: Slash Commands Testing
Test each command systematically:

#### Basic Usage Tests
```bash
# Test prompts discovery
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/list"}' | php bin/xdebug-mcp

# Test x-trace
/x-trace script="tests/fake/loop-counter.php" context="Performance analysis"

# Test x-debug  
/x-debug script="tests/fake/array-manipulation.php" context="Debug test" breakpoints="tests/fake/array-manipulation.php:10"

# Test x-profile
/x-profile script="tests/fake/loop-counter.php" context="Profiling test"

# Test x-coverage
/x-coverage script="vendor/bin/phpunit tests/Unit/McpServerTest.php" context="Coverage test"
```

#### Context Memory Tests
```bash
# Test 'last' functionality
/x-trace script="test1.php" context="First test"
/x-trace last="true"  # Should reuse previous settings
/x-trace last="true" context="Modified context"  # Should merge settings
```

#### Error Handling Tests
```bash
# Test with invalid files
/x-trace script="nonexistent.php"

# Test with missing required parameters
/x-trace context="No script specified"

# Test with invalid breakpoints
/x-debug script="tests/fake/loop-counter.php" breakpoints="invalid:format"
```

### Step 4: AI Analysis Evaluation
For each successful execution, evaluate:

1. **JSON Output Quality**
   - Is the structure clear and useful?
   - Are error messages helpful?
   - Is the debug data comprehensive?

2. **AI Analysis Effectiveness**
   - Can you understand the execution flow from the trace?
   - Are variable states clearly presented?
   - Can you identify performance bottlenecks?
   - Are failure points obvious?

3. **Forward Trace™ Methodology**
   - Does this feel like "AI-first" debugging?
   - Is it non-invasive (no code modification needed)?
   - Does it provide focused, actionable insights?

### Step 5: Integration Testing
Test PHPUnit integration scenarios:

```bash
# Test with composer x-test
composer x-test

# Test specific test patterns
composer x-test tests/Unit/McpServerTest.php::testConstructorWithValidScript

# Test failure scenarios
/x-debug script="vendor/bin/phpunit --stop-on-failure tests/Unit/DebugServerTest.php" context="First failure debugging"
```

## 📊 Evaluation Criteria

### Usability (1-5 scale)
- [ ] **Documentation Clarity**: Can you understand setup from README alone?
- [ ] **Command Discovery**: Are available commands obvious?
- [ ] **Error Messages**: Are errors helpful and actionable?
- [ ] **Learning Curve**: How quickly can you become productive?

### AI Analysis Quality (1-5 scale)  
- [ ] **Execution Flow Understanding**: Can you follow program execution?
- [ ] **Problem Identification**: Can you spot issues quickly?
- [ ] **Performance Insights**: Are bottlenecks obvious?
- [ ] **Debugging Effectiveness**: Does it help solve real problems?

### Forward Trace™ Methodology (1-5 scale)
- [ ] **Non-Invasiveness**: No code modification required?
- [ ] **Focus**: Does it avoid information overload?
- [ ] **AI-Optimized**: Feels designed for AI analysis?
- [ ] **Efficiency**: Quick time-to-insight?

## 📝 Recording Results

Create your evaluation report in: `tests/ai/evaluation-results/YYYY-MM-DD-fresh-claude-evaluation.md`

Include:
- Setup experience and issues
- Each command test result
- AI analysis effectiveness examples
- Usability pain points
- Suggestions for improvement
- Overall rating and recommendations

## 🚨 Important Notes

- **Do NOT read the source code** during initial evaluation
- **Do NOT consult implementation details** 
- **Document every confusion or error** - these are valuable insights
- **Use only README.md and help outputs** for initial understanding
- **Report honestly** - negative feedback is valuable for improvement

## 📁 Directory Structure

```
tests/ai/
├── README.md                    # This file
├── scenarios/                   # Specific test scenarios
├── evaluation-results/          # Fresh Claude evaluation reports
├── expected-outputs/            # Reference outputs for comparison
└── improvement-tracking/        # Issues and enhancement tracking
```

## 🎯 Success Metrics

A successful evaluation should demonstrate:
- Fresh Claude can set up and use the system following docs alone
- Slash commands provide genuinely useful AI debugging insights  
- Forward Trace™ methodology feels natural and effective
- The system enhances rather than complicates the debugging workflow

**Ready to start? Launch that fresh Claude session!** 🚀