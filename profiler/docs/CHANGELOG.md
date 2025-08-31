# Changelog

All notable changes to the Xdebug Trace Analyzer will be documented in this file.

## [2.0.0] - 2025-08-31

### Added
- **Complete JSON-based output system** replacing text-based reports
- **AI-optimized JSON schema** with emoji prefixes for enhanced readability
- **MCP (Model Context Protocol) compliance** for seamless AI integration
- **Comprehensive CLI tools** with four specialized executables:
  - `trace-analyze`: Main analysis engine with search capabilities
  - `trace-summary`: Executive summary and reporting tool
  - `trace-compare`: Before/after performance comparison
  - `trace-bottlenecks`: Performance bottleneck extraction and ranking
- **Advanced performance analysis** with priority classification (critical/high/medium/low)
- **Memory usage analysis** with detailed delta tracking and formatting
- **Vendor vs user code classification** for focused debugging
- **Contextual analysis support** with `--context` flag for AI interpretation
- **Schema validation** built into CLI tools
- **Compact JSON output** option for large-scale processing

### Changed
- **Complete rewrite** of analysis engine for JSON-first output
- **Improved performance** with optimized parsing and reduced memory usage
- **Enhanced error handling** with detailed JSON error responses
- **Better CLI interface** with comprehensive help and examples

### Removed
- **Text-based output formats** (top-slow-functions.txt, memory-usage.txt)
- **Legacy analysis methods** replaced with structured JSON approach

### Technical Details
- **JSON Schema**: Full Draft 07 compliance with detailed type definitions
- **AI Integration**: Embedded analysis strategies and interpretation guides
- **Performance**: 50KB trace files analyzed in ~0.1 seconds
- **Scalability**: Handles traces from 50KB to 125MB+ efficiently
- **Memory Efficiency**: ~2x trace file size RAM requirement

## [1.0.0] - Initial Version

### Features
- Basic trace file parsing
- Function call analysis
- Performance data extraction
- Text-based reporting

---

## Roadmap

### [2.1.0] - Planned Features
- **Real-time trace analysis** during application execution
- **Database query extraction** from PDO function calls
- **Security analysis** for privilege escalation detection
- **Business flow analysis** for user journey mapping
- **Integration with additional profilers** (Blackfire, XHProf)

### [2.2.0] - Advanced Features
- **Machine learning insights** for anomaly detection
- **Historical trend analysis** across multiple trace files
- **Custom metric definitions** for domain-specific analysis
- **Interactive web dashboard** for trace visualization

### Future Considerations
- **Real-time streaming analysis** for production monitoring
- **Distributed tracing support** for microservices
- **Integration with APM tools** (New Relic, DataDog)
- **Plugin system** for custom analyzers
