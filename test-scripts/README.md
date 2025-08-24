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

### Core Debugging Scripts
- **`buggy_calculation_code.php`** - Basic calculation with intentional bugs for debugging practice
- **`simple_test.php`** - Minimal PHP script for basic trace/profile testing

### Recursion and Performance Testing
- **`deep_recursion_test.php`** - Recursive functions (countdown, factorial) for depth analysis and stack tracing
- **`complex_test.php`** - Complex execution flow with multiple function calls and nested operations

### Database Query Analysis
- **`sqlite_db_test.php`** - N+1 query problem demonstration with SQLite (in-memory database)
- **`database_test.php`** - General database operation testing patterns
- **`real_db_test.php`** - Real database connection testing scenarios

### Comparative Analysis
- **`simple_comparison_test.php`** - Simple script for comparing Profile vs Trace tool accuracy

## Usage Examples

### Trace Analysis
```bash
# Analyze execution flow and function calls
./bin/xdebug-trace test-scripts/deep_recursion_test.php

# Database query analysis (N+1 detection)
./bin/xdebug-trace test-scripts/sqlite_db_test.php
```

### Performance Profiling
```bash
# Performance bottleneck identification
./bin/xdebug-profile test-scripts/complex_test.php

# Memory and timing analysis
./bin/xdebug-profile test-scripts/deep_recursion_test.php
```

### Interactive Debugging
```bash
# Step-by-step debugging with breakpoints
./bin/xdebug-debug test-scripts/buggy_calculation_code.php
```

### Coverage Analysis
```bash
# Code coverage analysis
./bin/xdebug-coverage test-scripts/complex_test.php
```

## Test Script Specializations

**Database Analysis**: `sqlite_db_test.php`, `database_test.php`, `real_db_test.php`
- N+1 query problem detection
- Database function call counting
- SQL performance analysis

**Recursion Analysis**: `deep_recursion_test.php`
- Maximum call depth tracking
- Recursive pattern detection
- Stack depth analysis

**Performance Testing**: `complex_test.php`
- Function call overhead analysis
- Memory usage patterns
- Execution timing analysis

**Bug Demonstration**: `buggy_calculation_code.php`
- Intentional logic errors for debugging practice
- Variable state tracking
- Error condition analysis