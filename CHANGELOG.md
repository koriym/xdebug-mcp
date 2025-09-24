# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.1] - 2025-09-24

### Fixed
- Escape special glob characters in trace_output_name pattern
- Improve error messages for trace file detection

### Changed
- Remove obsolete skipped tests
- Clean up test suite

## [0.3.0] - Previous Release

### Added
- **🤖 AI-Optimized Help Documentation**: Revolutionary comprehensive help system for all xdebug tools
  - Enhanced `--help` output for `xdebug-coverage`, `xdebug-debug`, `xdebug-profile`, and `xdebug-trace`
  - AI-first design philosophy with clear value propositions and workflow integration
  - Practical usage patterns and benefits comparison vs traditional debugging methods
  - Recommended AI prompts: "Run [tool] --help first to understand this tool"
  
### Enhanced  
- **🎯 xdebug-coverage**: Superior alternative to PHPUnit HTML/XML coverage for AI analysis
  - Auto-detection of PHPUnit with `--no-coverage` and TestDox format integration
  - Mixed output streams: PHPUnit results + JSON coverage data in single command
  - Reduced cognitive load for AI analysis vs browser-based HTML reports
  - AI-optimized JSON schema with comprehensive coverage metadata
  
- **🔍 xdebug-debug**: Non-invasive interactive debugging with comprehensive AI guidance  
  - Revolutionary approach emphasizing zero source code modification
  - Conditional breakpoints and step recording capabilities highlighted
  - JSON output optimization for AI consumption and analysis
  - Clear workflow: Set breakpoints → Analyze runtime data → Done (no cleanup needed)
  
- **📊 xdebug-trace**: Ultimate alternative to static code analysis
  - Runtime reality vs theoretical analysis emphasis
  - Complete execution flow with function calls, parameters, and timing data
  - Universal compatibility with PHPUnit, frameworks, and any PHP script
  - AI capabilities: identify unexpected paths, performance bottlenecks, parameter issues
  
- **⚡ xdebug-profile**: Scientific performance optimization with precision metrics
  - AI-driven optimization workflow with before/after measurements
  - Comprehensive metrics: CPU time, memory usage, function calls, I/O operations
  - Precision bottleneck identification with microsecond timing accuracy
  - Production-safe profiling without code modification

### Documentation
- Updated README.md with AI-first design philosophy section
- Enhanced PACKAGE_USAGE.md with AI-optimized help guidance
- Added unified recommendation: Always run `--help` first for optimal AI collaboration

## [0.2.1] - 2025-08-31

### Added
- **Unified Trace Analysis System**: New comprehensive trace analysis infrastructure
  - Added `xdebug-analyze` unified CLI tool for all trace analysis needs
  - Support for multiple analysis modes: summary, comparison, bottlenecks extraction, function search
  - Emoji-prefixed JSON output for enhanced AI readability and navigation
  - Context-aware analysis with `--context` option for self-explanatory debugging data
- **Advanced Profiler Integration**: Complete profiler directory structure
  - `TraceAnalyzer.php` and `TraceUtils.php` for large file processing (>10MB)
  - Comprehensive bin tools: `trace-analyze`, `trace-bottlenecks`, `trace-compare`, `trace-summary`
  - AI integration strategy and architecture documentation
  - JSON schema for trace analysis standardization
- **AI-Optimized Debugging Workflow**: Strategic integration planning
  - `INTEGRATION_PLAN.md` with phased implementation roadmap
  - Navigation tools for large trace file analysis
  - AI-driven performance bottleneck identification
  - Self-contained analysis data with contextual metadata

### Enhanced
- **Trace Analysis Capabilities**: Multi-mode analysis support
  - Executive summary generation for quick insights
  - Performance comparison between trace files
  - Targeted bottleneck extraction with configurable limits
  - Function search and call pattern analysis
- **Documentation**: Complete profiler architecture documentation
  - AI integration strategy and technical implementation details
  - Usage examples and workflow guidelines
  - Schema validation and output standardization

## [0.2.0] - 2025-08-30

### Added
- **Dynamic Vendor Filtering System**: Intelligent vendor package inclusion/exclusion
  - CLI `--include-vendor` option with pattern matching support using `fnmatch()`
  - Support for specific packages (`bear/resource,ray/di`), wildcards (`bear/*`), and full inclusion (`*/*`)
  - Integration with MCP tools via `include_vendor` parameter for AI-driven filtering
  - Robust CLI argument parsing using `getopt()` instead of manual parsing
  - Backward compatibility with default vendor exclusion behavior
- **Ultra-Simple Coverage Tool**: New 25-line `xdebug-coverage` implementation
  - Native Xdebug format output with JSON schema validation
  - Automatic vendor/ and tests/ directory filtering using `xdebug_set_filter()`
  - Works with any PHP script, not just PHPUnit
  - Clean JSON output with comprehensive schema documentation

### Changed
- **Enhanced Vendor Filtering**: Replaced environment variable-based filtering with advanced CLI argument system
- **CLI Tool Consistency**: Standardized argument handling across all tools with `--` separator support
- **Slide Presentation Completion**: Finalized "Forward Trace Revolution" presentation
  - Removed framework-specific examples (Laravel/Pest) for universal focus
  - Enhanced AI Code Quality messaging beyond "Tests Pass"
  - Streamlined coverage tool advantages to core value propositions
- **Documentation Improvements**: Fixed broken links and updated tool references
  - Fixed `docs/index.html` broken links pointing to non-existent directories
  - Updated all `test-coverage` references to `xdebug-coverage` in CLAUDE.md
  - Enhanced JSON schema documentation with xdebug.org links

### Removed  
- Environment variable-based vendor filtering (`XDEBUG_MCP_DISABLE_VENDOR_FILTER`)
- Redundant test methods for simplified tools
- "Test framework independent" obvious advantage from coverage tool messaging

### Fixed
- Command injection vulnerabilities through proper argument escaping
- Tool reliability issues by using PHP_BINARY instead of shell execution
- Test suite alignment with simplified tool implementations

## [0.1.0] - 2025-08-26

### Added
- **JSON Schema Support for AI-Optimized Debugging**
  - Added `--json` flag to `xdebug-debug` CLI for structured output
  - Implemented JSON output with trace metadata: `trace_file`, `lines`, `size`, `command`
  - Created comprehensive JSON Schema (draft-07) with AI guidance links
  - Added GitHub Pages publication for documentation

- **AI Debugging Integration**
  - Published AI debugging principles to `docs/ai-debugging-with-debugger.md`
  - Created practical AI analysis guidelines in `docs/debug_guideline_for_ai.md`
  - Integrated file size-based AI reading strategies (small/medium/large files)
  - Added Xdebug trace format documentation for AI systems

- **Documentation Enhancements**
  - Added "From Guesswork to Evidence" narrative with Var_Dump Age vs AI-Native Age comparison
  - Enhanced README with debugging evolution examples
  - Created comprehensive debugging workflow documentation
  - Added JSON debugging integration guidelines

- **Quality Improvements**
  - Implemented command argument escaping with `escapeshellarg()` for security
  - Added URL validation GitHub Actions workflow
  - Created versioned JSON schema with proper validation
  - Enhanced error handling and input validation

### Security
- Command arguments are now properly escaped to prevent shell injection
- JSON output uses secure string handling

### Infrastructure
- Added GitHub Actions workflow for documentation URL validation
- Enhanced CI pipeline with link checking capabilities
- Improved schema validation and documentation integrity

---

## About This Release

This initial release introduces revolutionary AI-powered PHP debugging capabilities that eliminate the need for traditional `var_dump()` debugging. The JSON Schema support enables AI assistants to perform evidence-based debugging analysis using actual runtime data from Xdebug traces.

### Key Innovation
- **Evidence-Based Debugging**: Move from guesswork-driven to technology-driven debugging
- **AI Integration**: Native support for AI analysis of execution traces
- **Non-Invasive**: Zero source code modification required
- **Comprehensive**: Full execution visibility with conditional breakpoints

### Next Steps
- Gather user feedback on AI debugging workflows
- Expand JSON support to additional Xdebug tools
- Enhance AI analysis capabilities based on real-world usage
- Build community around modern PHP debugging practices

[0.2.1]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.2.1
[0.2.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.2.0
[0.1.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.1.0