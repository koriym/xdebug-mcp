# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2025-08-26

### Added
- **JSON Schema Support for AI-Optimized Debugging**
  - Added `--json` flag to `xdebug-debug` CLI for structured output
  - Implemented JSON output with trace metadata: `trace_file`, `lines`, `size`, `command`
  - Created comprehensive JSON Schema (draft-07) with AI guidance links
  - Added GitHub Pages publication for documentation

- **AI Debugging Integration**
  - Published AI debugging principles to `docs/ai-debugging-with-debugger.md`
  - Created practical AI analysis guidelines in `docs/debug-guidelines.md`
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

[0.1.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.1.0