おりじ# Xdebug Trace Analyzer

**AI-Optimized PHP Debugging & Flow Analysis Tool**

A comprehensive trace analysis system designed specifically for AI-assisted debugging, complementing traditional profilers with deep execution flow analysis and real-time data inspection.

## 🎯 Purpose & Differentiation

### **vs Traditional Profilers**
| Tool | Focus | Strength |
|------|-------|----------|
| **Xdebug Profiler** | Performance metrics | Precise timing, call counts |
| **Blackfire** | Production profiling | Advanced performance insights |
| **Our Trace Analyzer** | **Debugging & Flow Analysis** | **Real arguments, execution paths, AI integration** |

### **Unique Value Proposition**
- **Real Data Inspection**: See actual function parameters and return values
- **Execution Path Analysis**: Understand which code branches were actually taken  
- **AI-Optimized Output**: JSON schemas designed for AI consumption
- **Debug-First Approach**: Focus on "why it happened" not just "how long it took"
- **MCP Integration**: Seamless integration with Claude Code and other AI tools

## 🚀 Quick Start

### Installation
```bash
# Clone or copy the xdebug-profiler directory
cd your-project/
git clone <repo> xdebug-profiler/
# or copy the directory manually
```

### Basic Usage
```bash
# Generate trace file (any method)
php -dxdebug.mode=trace -dxdebug.start_with_request=yes -dxdebug.trace_output_dir=/tmp your-script.php

# Analyze with our tool
./xdebug-profiler/bin/trace-analyze /tmp/trace.12345.xt --context="User authentication flow"

# Generate summary report
./xdebug-profiler/bin/trace-summary /tmp/analysis-result.json
```

## 📊 Core Features

### 1. **Deep Execution Analysis**
```json
{
  "🚀 performance_analysis": {
    "🐌 slowest_functions": [
      {
        "🏷️ function_name": "UserService::authenticate",
        "⏱️ duration_seconds": 0.245,
        "💾 memory_delta_formatted": "+2.1MB",
        "💡 optimization_priority": "high",
        "📍 trace_start_line": 1205
      }
    ]
  }
}
```

### 2. **Real Parameter Tracking**
Unlike profilers, see actual data:
```php
// What profilers show:
function_name: validateUser, calls: 5, time: 0.1s

// What our analyzer shows:
validateUser($email = "admin@test.com", $password = "123") → true
validateUser($email = null, $password = "pwd") → ERROR  // Bug found!
validateUser($email = "user@test.com", $password = "secret") → false
```

### 3. **AI-Optimized JSON Schema**
- **Structured Data**: Complete separation of metadata, statistics, performance
- **Emoji Prefixes**: Enhanced AI readability with visual categorization
- **MCP Compliant**: Direct integration with Claude Code and MCP tools
- **Context Aware**: Every analysis includes contextual description

### 4. **Advanced Utilities**
```bash
# Compare two executions
./bin/trace-compare trace1.json trace2.json

# Extract bottlenecks
./bin/trace-bottlenecks analysis.json --limit=10

# Validate against schema
./bin/trace-analyze trace.xt --validate
```

## 🛠️ Architecture

### Directory Structure
```
xdebug-profiler/
├── bin/                    # Executable scripts
│   ├── trace-analyze       # Main analyzer
│   ├── trace-summary       # Summary generator
│   ├── trace-compare       # Comparison tool
│   └── trace-bottlenecks   # Bottleneck extractor
├── src/                    # Source code
│   ├── TraceAnalyzer.php   # Core analysis engine
│   └── TraceUtils.php      # Utility functions
├── schemas/                # JSON schemas
│   └── trace-analysis-schema.json
├── docs/                   # Documentation
├── examples/               # Example analyses
└── README.md              # This file
```

### Core Classes
- **`XdebugTraceAnalyzerV2`**: Main analysis engine with JSON output
- **`TraceUtils`**: Comparison, summarization, and utility functions

## 📋 JSON Schema Structure

Our analysis produces comprehensive JSON with these main sections:

### **📊 Metadata**
- Source file information
- Execution time and memory usage
- Analysis version and timestamp

### **📈 Statistics**  
- Function counts (user/vendor/internal)
- File involvement metrics
- Call depth analysis

### **🚀 Performance Analysis**
- Slowest functions (top 50)
- Memory-intensive operations (top 30) 
- Frequently called functions (top 25)
- Performance warnings with severity levels

### **🗂️ Indexes**
- Complete function call index
- File-based organization
- Searchable execution data

## 🎯 Use Cases & Examples

### **1. Bug Investigation**
```bash
# Analyze problematic execution
./bin/trace-analyze /tmp/bug-trace.xt --context="Login failure investigation"

# Look for specific function
./bin/trace-analyze /tmp/bug-trace.xt --search="authenticate"
```

**Result**: See actual parameters that caused the bug, not just timing data.

### **2. Performance Debugging** 
```bash
# Full performance analysis
./bin/trace-analyze /tmp/slow-trace.xt --context="API endpoint performance analysis"

# Generate executive summary
./bin/trace-summary analysis-result.json
```

**Result**: Identify performance bottlenecks with context and recommendations.

### **3. Before/After Comparisons**
```bash
# Compare before and after optimization
./bin/trace-compare before.json after.json
```

**Result**: Detailed comparison showing improvements and regressions.

### **4. AI-Assisted Analysis**
```bash
# Generate AI-optimized output
./bin/trace-analyze trace.xt --context="BEAR.Sunday DI container analysis" --compact
```

**Result**: Structured JSON perfect for AI consumption and analysis.

## 🔧 Configuration & Options

### **Command Line Options**

#### `trace-analyze`
```bash
Options:
  --context="description"     Add contextual description for AI analysis
  --compact                   Output compact JSON (no pretty printing)
  --search="function"         Search for specific function calls
  --validate                  Validate output against JSON schema
```

#### `trace-summary` 
```bash
Commands:
  summary <analysis.json>     Generate executive summary report
  compare <file1> <file2>     Compare two analysis results  
  bottlenecks <analysis> [N]  Extract top N bottlenecks
```

### **Integration with MCP Tools**
```bash
# Use with Xdebug MCP server
mcp__xdebug__x-trace --script="php app.php" --context="User workflow analysis"

# Analyze MCP-generated traces
./bin/trace-analyze /tmp/mcp-trace.xt --context="MCP-generated trace analysis"
```

## 📊 Performance Characteristics

### **Analysis Speed**
- **50KB trace**: ~0.1 seconds, 31 functions analyzed
- **500KB trace**: ~1 second, 300+ functions analyzed  
- **5MB+ trace**: 10+ seconds, thousands of functions analyzed

### **Memory Usage**
- **Efficient processing**: ~2x trace file size in RAM
- **Structured output**: Optimized JSON generation
- **Scalable design**: Handles large traces without memory exhaustion

## 🤖 AI Integration Features

### **Claude Code Integration**
- **MCP Protocol**: Native support for Claude Code workflows
- **Context Preservation**: Maintains analysis context across sessions
- **Structured Queries**: Enable precise AI questioning about execution

### **JSON Schema Benefits**
- **Predictable Structure**: AI can reliably extract information
- **Semantic Emojis**: Visual categorization aids AI understanding
- **Hierarchical Data**: Enables both high-level and detailed analysis

### **AI Analysis Strategies** 
Embedded in schema for optimal AI consumption:
```json
{
  "x-ai-strategies": {
    "analysis_approach": "performance_first",
    "optimization_workflow": ["warnings", "slowest", "memory", "frequency"],
    "interpretation_guide": {
      "execution_time": "Functions >0.1s warrant investigation",
      "memory_usage": "Deltas >1MB indicate significant allocations"
    }
  }
}
```

## 📖 Advanced Usage

### **Custom Analysis Workflows**
```php
// Programmatic usage
$analyzer = new XdebugTraceAnalyzerV2('/tmp/trace.xt');
$result = $analyzer->analyzeTrace('Custom analysis context');

// Search specific patterns
$authCalls = $analyzer->searchFunction('authenticate');

// Validate results
$errors = $analyzer->validateSchema();
```

### **Batch Processing**
```bash
# Process multiple traces
for trace in /tmp/trace.*.xt; do
    ./bin/trace-analyze "$trace" --context="Batch analysis $(basename $trace)"
done
```

### **Integration with CI/CD**
```yaml
# GitHub Actions example
- name: Performance Analysis
  run: |
    php -dxdebug.mode=trace tests/PerformanceTest.php
    ./xdebug-profiler/bin/trace-analyze /tmp/trace.*.xt --validate
    ./xdebug-profiler/bin/trace-summary analysis-result.json
```

## 🔍 Comparison with Alternatives

### **vs Xdebug Profiler**
- ✅ **Real parameter values** vs timing only
- ✅ **Execution path analysis** vs statistical summary  
- ✅ **AI-optimized output** vs Cachegrind format
- ⚠️ **Larger files** vs compact binary format

### **vs Blackfire**
- ✅ **Open source & free** vs commercial service
- ✅ **Full execution trace** vs sampling
- ✅ **AI integration** vs web dashboard
- ⚠️ **Local analysis only** vs cloud features

### **vs XHProf**
- ✅ **Modern JSON output** vs PHP arrays
- ✅ **Comprehensive analysis** vs basic profiling
- ✅ **Debug-oriented** vs performance-only
- ⚠️ **Xdebug dependency** vs standalone extension

## 🐛 Troubleshooting

### **Common Issues**

#### "File not found" errors
```bash
# Check trace file location
ls -la /tmp/trace.*.xt

# Verify Xdebug trace generation
php -m | grep xdebug
```

#### Large file processing
```bash
# For very large traces, use compact mode
./bin/trace-analyze large-trace.xt --compact > result.json

# Or process specific functions only
./bin/trace-analyze large-trace.xt --search="YourFunction"
```

#### Memory issues
```bash
# Increase PHP memory limit
php -d memory_limit=512M ./bin/trace-analyze trace.xt
```

### **Validation Errors**
```bash
# Validate against schema
./bin/trace-analyze trace.xt --validate

# Check schema compliance
php -c validate-json.php result.json schemas/trace-analysis-schema.json
```

## 📚 Additional Resources

### **Related Projects**
- [Xdebug MCP Server](https://github.com/koriym/xdebug-mcp) - MCP integration
- [BEAR.Sunday](https://bearsunday.github.io/) - Framework used in examples
- [Claude Code](https://claude.ai/code) - AI development environment

### **Documentation**
- [Xdebug Trace Format](https://xdebug.org/docs/trace) - Official trace specification
- [MCP Protocol](https://modelcontextprotocol.io/) - Model Context Protocol
- [JSON Schema Draft 07](https://json-schema.org/draft-07/schema) - Schema specification

### **Contributing**
- Issue reporting: Use GitHub issues for bugs and feature requests
- Pull requests: Welcome for improvements and new features
- Documentation: Help improve examples and usage guides

---

**🚀 Ready to analyze your PHP execution like never before!**

*This tool transforms Xdebug traces from raw execution data into AI-ready insights for better debugging and optimization.*
