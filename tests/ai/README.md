# AI Evaluation Framework for Xdebug MCP Slash Commands

This directory contains evaluation materials for testing the AI-friendliness and effectiveness of the xdebug-mcp slash commands implementation.

## 🎯 Purpose

Evaluate the simplified Xdebug tools (`xdebug-debug`, `xdebug-trace`, `xdebug-profile`, `xdebug-coverage`) from a **fresh Claude perspective** to identify:
- Usability issues
- Documentation gaps  
- AI analysis quality
- Vendor filtering effectiveness (prepend_filter.php)
- Forward Trace™ debugging methodology

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
3. **Read docs/debug_guideline_for_ai.md FIRST** - Essential for understanding Forward Trace methodology
4. Then read the main README.md

### Step 2: Setup Attempt
1. Try to understand the project from README.md alone
2. Attempt MCP server setup following documentation
3. **Document any confusion or missing steps**

### Step 3: Xdebug Tools Testing
Test each simplified tool systematically:

#### Basic Usage Tests
```bash
# Test tool discovery
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php bin/xdebug-mcp

# Test xdebug-trace (execution flow analysis with vendor filtering)
./bin/xdebug-trace --context="Performance analysis" tests/fake/loop-counter.php

# Test xdebug-trace with PHPUnit (no vendor noise)
./bin/xdebug-trace --context="Testing user authentication" -- php vendor/bin/phpunit --filter testToolsListRequest tests/Unit/McpServerTest.php

# Test xdebug-debug (interactive debugging)
./bin/xdebug-debug --context="Debug test" --break="tests/fake/array-manipulation.php:10" tests/fake/array-manipulation.php

# Test xdebug-profile (performance profiling with vendor filtering)
./bin/xdebug-profile --context="Profiling test" tests/fake/loop-counter.php

# Test xdebug-profile with PHPUnit (clean profiling data)
./bin/xdebug-profile --context="Profile functionality verification" -- php vendor/bin/phpunit --filter testToolsListRequest tests/Unit/McpServerTest.php

# Test xdebug-coverage (code coverage analysis)
./bin/xdebug-coverage --context="Coverage test" -- php vendor/bin/phpunit tests/Unit/McpServerTest.php
```

#### Vendor Filtering Verification
```bash
# Verify clean trace output (should be ~83 lines, not 21,000+)
./bin/xdebug-trace --context="Vendor filtering test" -- php vendor/bin/phpunit --filter testToolsListRequest tests/Unit/McpServerTest.php
# Expected: Clean execution trace focusing only on user code, no Composer/vendor noise

# Verify clean profile output
./bin/xdebug-profile --context="Vendor filtering test" -- php vendor/bin/phpunit --filter testToolsListRequest tests/Unit/McpServerTest.php  
# Expected: Profile data focused on user functions, not vendor library overhead

# Test selective vendor inclusion (NEW FEATURE)
./bin/xdebug-trace --include-vendor=bear/resource,ray/di --context="Selective vendor analysis" -- php app.php
# Expected: Trace includes only specified vendor packages, excludes all others

# Test wildcard vendor patterns (NEW FEATURE)  
./bin/xdebug-trace --include-vendor=bear/* --context="Framework component debugging" -- php app.php
# Expected: Trace includes all packages matching pattern (bear/*)

# Test full vendor inclusion (NEW FEATURE)
./bin/xdebug-trace --include-vendor=*/* --context="Complete system analysis" -- php app.php
# Expected: Trace includes all vendor code for comprehensive analysis
```


#### Error Handling Tests
```bash
# Test with invalid files
./bin/xdebug-trace nonexistent.php

# Test with missing required parameters
./bin/xdebug-trace

# Test with invalid breakpoints
./bin/xdebug-debug --break="invalid:format" tests/fake/loop-counter.php
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
Test simplified system integration:

```bash
# Test basic PHPUnit execution with vendor filtering
./bin/xdebug-trace --context="Unit test execution" -- php vendor/bin/phpunit tests/Unit/McpServerTest.php

# Test specific test methods with clean profiling
./bin/xdebug-profile --context="Single test profiling" -- php vendor/bin/phpunit --filter testToolsListRequest tests/Unit/McpServerTest.php

# Test coverage analysis
./bin/xdebug-coverage --context="Test coverage analysis" -- php vendor/bin/phpunit tests/Unit/McpServerTest.php

# Test failure scenarios with clean debugging
./bin/xdebug-debug --context="First failure debugging" --exit-on-break -- php vendor/bin/phpunit --stop-on-failure tests/Unit/DebugServerTest.php

# Test MCP integration with vendor filtering (NEW FEATURE)
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"x-debug","arguments":{"script":"tests/fixtures/debug_test.php","include_vendor":"bear/*","context":"MCP vendor filtering test"}}}' | ./bin/xdebug-mcp
# Expected: MCP tool accepts include_vendor parameter and passes it to underlying debug command
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
- [ ] **Vendor Filtering Effectiveness**: Does selective vendor inclusion help focus analysis? (NEW)

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
- Fresh Claude can set up and use the simplified system following docs alone
- Xdebug tools provide genuinely useful AI debugging insights with minimal vendor noise
- Vendor filtering via prepend_filter.php works seamlessly (83 lines vs 21,000+ unfiltered)
- Forward Trace™ methodology feels natural and effective
- The system enhances rather than complicates the debugging workflow

## 🔧 System Improvements (Post-Simplification)

**Major Simplifications Achieved:**
- **Vendor Filtering**: Replaced 200+ line TraceExtension system with single `xdebug_set_filter()` call
- **Code Reduction**: Removed complex TraceHelper, XdebugPhpunitCommand classes entirely
- **Coverage Improvement**: McpServer.php coverage increased from 13.6% to 61.79%
- **API Compatibility**: Maintained existing tool interfaces while simplifying internals
- **Automatic Filtering**: Uses `auto_prepend_file` for seamless vendor exclusion from startup

**NEW: Dynamic Vendor Filtering (Latest Addition):**
- **CLI Arguments**: `--include-vendor=PATTERNS` support across all tools
- **Pattern Matching**: Supports specific packages, wildcards (`bear/*`), and full inclusion (`*/*`)
- **MCP Integration**: `include_vendor` parameter available in all MCP tools
- **AI-Driven**: Allows AI assistants to dynamically focus analysis on relevant code sections
- **Flexible Filtering**: From complete vendor exclusion to selective package inclusion

**Ready to start? Launch that fresh Claude session!** 🚀