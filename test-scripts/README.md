# Test Scripts Directory

This directory contains comprehensive test prompts for validating all 47 XdebugMCP server features.

## Available Tests

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