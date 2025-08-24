# Test Scripts Directory

This directory contains comprehensive test prompts for validating all 47 XdebugMCP server features, plus various PHP test scripts for debugging analysis.

## Comprehensive Test Suites

### `COMPREHENSIVE_MCP_TEST.md` (Japanese)
Complete autonomous testing prompt for AI assistants to validate all 47 XdebugMCP features:
- 42 MCP tools across 5 categories
- 5 CLI tools 
- Interactive debugging workflow
- Real execution validation (no speculation allowed)

**Test Results:** 100% success rate (47/47 features working)

### `COMPREHENSIVE_MCP_TEST_EN.md` (English)
English version of the complete testing suite with identical functionality.

## Usage

### For New AI Sessions
Copy and paste the entire content of either test file into a new Claude Code session:

```bash
# Start new Claude Code session
claude

# Then copy-paste the complete content from either:
# - COMPREHENSIVE_MCP_TEST.md (Japanese)  
# - COMPREHENSIVE_MCP_TEST_EN.md (English)
```

### Expected Results
When properly executed, AI should achieve:
- ✅ **Success: 47/47 (100%)**
- ❌ **Failures: 0/47 (0%)**
- 🚫 **Unimplemented: 0/47 (0%)**

## Test Categories Covered

1. **Profiling & Performance** (4 tools) - Profile analysis, Cachegrind output
2. **Code Coverage** (6 tools) - Line/function coverage, HTML/XML reports  
3. **Interactive Debugging** (11 tools) - Breakpoints, step execution, variable inspection
4. **Trace Analysis** (4 tools) - Execution flow tracing, function monitoring
5. **Configuration & Diagnostics** (17 tools) - Settings, memory usage, error collection
6. **CLI Tools** (5 tools) - Standalone debugging utilities

## Quality Assurance

These tests enforce:
- **No speculation**: AI must actually execute each feature
- **Real validation**: File generation, data collection, actual results
- **Proper sequencing**: Correct connection setup for interactive debugging
- **Comprehensive coverage**: Every single MCP tool and CLI utility

## Development History

This represents the culmination of step debugging implementation work, providing definitive proof that:
- All 47 features are fully implemented
- AI assistants can successfully utilize the complete XdebugMCP suite
- Interactive debugging works with proper connection sequencing
- The project achieves its goal of AI-driven PHP runtime analysis

Perfect for regression testing, feature validation, and demonstrating AI debugging capabilities.

## PHP Test Scripts

### Core Scripts (3 Files)
- **`buggy_calculation_code.php`** - Basic calculation with intentional bugs for step debugging practice
- **`deep_recursion_test.php`** - Recursive functions (countdown, factorial) for max depth analysis and trace validation  
- **`sqlite_db_test.php`** - N+1 query problem demonstration with real SQLite PDO calls for database query counting

## Usage Examples

### Human Usage (Direct CLI)

**Trace Analysis - Human readable output:**
```bash
# Recursion depth and function call analysis
./bin/xdebug-trace -- php test-scripts/deep_recursion_test.php
# ✅ Trace complete: /tmp/trace.1034012359.xt
# 📊 50 lines generated, 🔄 10 max call depth

# N+1 database query detection  
./bin/xdebug-trace -- php test-scripts/sqlite_db_test.php
# ✅ Trace complete: /tmp/trace.1034012360.xt  
# 📊 83 lines generated, 🗃️ 22 database queries
```

**Performance Profiling - Human readable output:**
```bash  
# Recursion performance analysis
./bin/xdebug-profile -- php test-scripts/deep_recursion_test.php
# ✅ Profile complete: /tmp/cachegrind.out.1755719364
# 📊 Size: 2.1K, Functions: 12, Calls: 45, 🎯 Top functions: countdown, factorial

# Database query performance
./bin/xdebug-profile -- php test-scripts/sqlite_db_test.php
# ✅ Profile complete: /tmp/cachegrind.out.1755719365
# 📊 Size: 3.2K, Functions: 18, ⏱️ 0.002s, 💾 420KB
```

**Interactive Debugging:**
```bash
# Step debugging with breakpoints and variable inspection  
./bin/xdebug-debug test-scripts/buggy_calculation_code.php
# ✅ Interactive debugging session with breakpoints
```

**Coverage Analysis - Human readable output:**
```bash
# Code path coverage analysis
./bin/xdebug-coverage -- php test-scripts/deep_recursion_test.php  
# ✅ Coverage complete: HTML report generated
# 📊 Coverage: 85.2% lines, 92.1% functions
```

### AI Usage (JSON Output for Analysis)

**For AI Analysis - Always use --json flag:**
```bash
# AI receives structured JSON data for precise analysis
./bin/xdebug-trace --json -- php test-scripts/sqlite_db_test.php
# Returns: {"trace_file":"/tmp/trace.xt","total_lines":83,"unique_functions":10,"max_call_depth":3,"database_queries":22}

./bin/xdebug-profile --json -- php test-scripts/deep_recursion_test.php  
# Returns: {"profile_file":"/tmp/cachegrind.out.123","size":"2.1K","functions":12,"calls":45}

./bin/xdebug-coverage --json -- php test-scripts/deep_recursion_test.php
# Returns: {"lines_covered":85.2,"functions_covered":92.1,"untested_lines":[15,23,35]}
```

## Specialized Use Cases

**`buggy_calculation_code.php`**
- Step-by-step debugging practice
- Variable state inspection
- Breakpoint and execution control

**`deep_recursion_test.php`**  
- Maximum call depth tracking (Profile vs Trace comparison)
- Recursive pattern analysis
- Function call counting accuracy validation

**`sqlite_db_test.php`**
- Real N+1 query problem demonstration  
- Database function counting (PDO->exec, PDOStatement->execute)
- SQL performance bottleneck identification