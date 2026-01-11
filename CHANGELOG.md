# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.7.0] - 2026-01-11

### Changed
- **xcoverage**: PHPUnit mode is now the default
  - Uses `--coverage-clover` for stable PHPUnit version compatibility
  - Respects `@codeCoverageIgnore` annotations and `phpunit.xml` settings
  - No php-code-coverage dependency (parses clover XML directly)

### Added
- **xcoverage**: `--raw` option for original Xdebug direct coverage behavior
  - Use for non-PHPUnit projects or when annotation support is not needed
  - `--branch-coverage` and `--include-vendor` work in raw mode only

## [0.6.1] - 2026-01-11

### Added
- **Plugin Marketplace**: Full plugin marketplace support with `marketplace.json`
  - Install via: `/plugin marketplace add koriym/xdebug-mcp` then `/plugin install xdebug@xdebug-mcp`

### Fixed
- **Plugin Discovery**: Add `skills` field to `plugin.json` for skill registration
- **README**: Add complete installation and usage instructions

## [0.6.0] - 2026-01-11

### Added
- **Claude Code Plugin**: Plugin marketplace support (`.claude-plugin/plugin.json`)
  - Install via: `/plugin marketplace add koriym/xdebug-mcp`

### Changed
- **Token Optimization**: Reduce xcoverage output by 89% using range notation
- **Key Naming**: Improve readability (`user_functions`, `db_queries`)
- **README**: Update for plugin-first approach, add "Why xdebug-mcp?" section

### Fixed
- **Schema**: Support cross-platform path regex (Windows/Unix)

## [0.3.2] - 2025-11-03

### Fixed
- **Standalone Execution Support**: Fixed MCP server to work when invoked from any working directory ([#27](https://github.com/koriym/xdebug-mcp/issues/27))
  - Replaced relative paths (`./bin/*`) with absolute paths using `dirname(__DIR__)`
  - Enables Claude Code to invoke xdebug-mcp tools regardless of current working directory
  - All MCP tools (x-trace, x-debug, x-profile, x-coverage) now work standalone
- **Profiler Filename Placeholder**: Fixed cachegrind output filename to display actual process ID ([#28](https://github.com/koriym/xdebug-mcp/issues/28))
  - Changed placeholder from `%s` (script name) to `%p` (process ID) in profiler_output_name
  - Output now shows `/tmp/cachegrind.out.12345` instead of `/tmp/cachegrind.out.%s`
  - Makes profile files easy to identify and use with analysis tools
- **JSON Output Parsing**: Fixed `xdebug-profile --json` to suppress script output for clean JSON ([#28](https://github.com/koriym/xdebug-mcp/issues/28))
  - Script stdout/stderr now automatically suppressed in JSON mode
  - Enables clean piping to jq: `... | jq '.["🎯 bottleneck_functions"]'`
  - Human-readable mode unchanged (still shows script output)
- **Windows Compatibility**: Cross-platform output suppression in JSON mode
  - Detect platform using `PHP_OS_FAMILY`
  - Use 'NUL' on Windows, '/dev/null' on Unix for output redirection
  - Fixes `xdebug-profile --json` to work on Windows systems

## [0.3.1] - 2025-09-24

### Fixed
- Escape special glob characters in trace_output_name pattern
- Improve error messages for trace file detection

### Changed
- Remove obsolete skipped tests
- Clean up test suite

## [0.3.0] - 2025-01-02

### Added
- **AI-Optimized Help Documentation**: Comprehensive `--help` output for all xdebug tools with AI-first design philosophy

### Fixed
- **MCP Tool Security**: Added proper shell escaping with `escapeshellarg()` to prevent command injection
- **Claude Code Compatibility**: Fixed x-coverage MCP tool not responding in interface
- **Argument Parsing**: Improved handling of quotes and spaces in MCP tool arguments
- **Default Behavior**: x-coverage now defaults to `php vendor/bin/phpunit --no-coverage` when no arguments provided

### Enhanced
- **xdebug-coverage**: AI-optimized JSON output with PHPUnit integration and automatic `--no-coverage` flag
- **xdebug-debug**: Non-invasive debugging with JSON output for AI consumption
- **xdebug-trace**: Runtime execution analysis as alternative to static code analysis
- **xdebug-profile**: Precision performance metrics with microsecond timing
- **MCP Tools**: All 4 slash commands (x-trace, x-debug, x-profile, x-coverage) now work reliably in Claude Code

### Security
- Fixed shell injection vulnerability in x-coverage tool
- Added comprehensive argument validation across all MCP tools

### Documentation
- Updated README.md with AI-first design philosophy
- Enhanced PACKAGE_USAGE.md with tool usage guidance

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

[0.3.2]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.3.2
[0.3.1]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.3.1
[0.3.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.3.0
[0.2.1]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.2.1
[0.2.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.2.0
[0.1.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.1.0