# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.10.2] - 2026-06-15

### Added
- xcoverage raw mode now supports collecting coverage for inline PHP passed via `-r`/`--run` (#83).
- xprofile JSON mode supports profiling inline PHP via `-r`/`--run` (#83).

### Fixed
- Inline code handling preserves `__LINE__` and leading `declare(strict_types=1)` (#83).
- `--branch-coverage` is rejected for PHP inline code with a clear error message (#83).
- Safer git worktree creation and cleanup in xcompare with improved failure recovery (#84).
- xcompare diff output is now deterministic with consistent ordering (#84).
- CLI error messages on runtime failures are now concise without stack traces (#84).

## [0.10.1] - 2026-05-21

### Added
- Add `AGENTS.md` contributor guidance, including public output hygiene for
  PRs, issues, release notes, docs, and examples (#82).

### Changed
- Align CLI help, bin documentation, troubleshooting examples, and the internal
  JSON regression script with the current `x*` command set (#82).
- Stop publishing `bin/test-json` as a Composer bin command; it remains an
  internal regression script (#82).

### Fixed
- Apply shared vendor filtering across trace, step, profile analysis, and raw
  coverage paths so `--include-vendor` behavior matches the CLI help (#82).
- Update profile schema validation references to the current `xprofile.json`
  schema (#82).

## [0.10.0] - 2026-05-08

### Added
- xcoverage / xback: `--cwd`, `--php` to support running against external
  projects without changing directory (#80).
- xcoverage: `--source=PATH[,...]` to restrict raw-mode coverage; mutually
  exclusive with `--include-vendor` (#80).

### Fixed
- DBGp XML sanitization: strip invalid numeric character references
  (`&#0;`, `&#x0;`, surrogates, beyond `U+10FFFF`) (#81).
- xback: `--php` override now reaches `DebugServer` (#80).

## [0.9.0] - 2026-04-30

### Added
- **xstep JSON ergonomics** (#74):
  - `--pretty` for indented JSON output
  - `--max-value-bytes=N` to truncate long string values (UTF-8 safe via `mb_strcut`)
  - `--max-depth=N` to cap nested array/object rendering
  - `function` and `stack` context attached to each break
  - Scalar before/after `diff` and shallow key diffs between consecutive snapshots
  - `breakpoint.id` / `breakpoint.label` attached to each break
  - `recording_type` field (`full` | `diff`) documented in `docs/schemas/xstep.json`
- **xcompare**: Compare variable states at breakpoint across two different executions
  - Runs xstep twice with different commands/inputs
  - Computes diff: changed, unchanged, only_in_a, only_in_b
  - Provides analysis_hints for AI-readable summary
  - JSON schema: `docs/schemas/xcompare.json`
  - Use case: Debug edge cases by comparing normal vs problematic inputs
  - `--compare-with=REF` mode to compare current code vs another git branch/commit
    - `xcompare --break=file.php:25 --run="php test.php" --compare-with=main`
    - Automatically creates temporary worktree, runs comparison, cleans up
- **`composer demo`** (#76): runs every CLI tool documented in the README against
  the bundled `demo/` scripts and reports PASS/FAIL. Smoke test for users and CI.
- **`bin/xrepl` and `bin/xcompare` exposed via `composer global require`** (#76):
  both shipped in `bin/` but were missing from `composer.json`'s `bin` array.

### Changed
- **BREAKING — Drop PHP 8.1 support** (#75): minimum PHP version is now `^8.2`.
  - Upgrade to PHPUnit 11 (`^11.0`), migrate test attributes (`#[DataProvider]`
    static providers, removed `expectDeprecation*`, `assertObjectHasAttribute`).
  - Remove `composer audit ignore` entries no longer needed under 8.2+.
  - CI matrix runs on PHP 8.2 / 8.3 / 8.4 / 8.5.

### Fixed
- **README CLI examples** (#76):
  - Drop the broken `--exit-on-break` flag from `xstep` examples (the parser
    silently dropped it; default JSON mode already exits after recording).
  - Interactive REPL section now invokes `xrepl` (not `xstep`).
  - `xcoverage --raw` is required for plain PHP scripts; the previous example
    failed with "Class coverage cannot be found" because the default PHPUnit
    mode interpreted the script path as a test class.
  - Replace hard-coded `~/.composer/vendor/bin/check-env` path with
    `composer global config bin-dir`-based lookup so it works under XDG.
- Document `--pretty` / `--max-value-bytes` / `--max-depth` in README CLI Usage.
- Stale `php8.1` references in `profiler/examples/basic-usage.md` and
  `src/McpServer.php` example messages updated to `php8.2`.

## [0.8.0] - 2026-01-30

### Added
- **xstep --watch**: Expression-based step filtering for efficient loop debugging
  - `--watch=EXPR` evaluates PHP expressions at each step, records only when value changes
  - Multiple watches supported: `--watch="$i" --watch="count($items)"`
  - Detects array/object internal mutations via content hashing
  - Handles scope transitions (`initial`, `changed`, `out_of_scope`)
  - JSON schema updated (`docs/schemas/xstep.json`) with `watches` field

### Changed
- **Coding Standards**: Enforce Doctrine Coding Standard 13 strict rules
  - Enable `EarlyExit` rule: all `else` blocks converted to early return pattern
  - Enable `PropertyTypeHint.MissingNativeTypeHint`
  - Enable `ReturnTypeHint.MissingNativeTypeHint`
  - Enable `PropertyTypeHint.MissingAnyTypeHint`

### Fixed
- Prevent duplicate JSON output in step recording

## [0.7.1] - 2026-01-16

### Added
- **MCP Tool Search Optimization**: Add server-level `instructions` field to initialize response
  - Helps Claude Code's Tool Search feature find Xdebug tools when dynamically loading
  - Keywords: PHP debugging, profiling, coverage, tracing
- **Documentation**: Add "Skills vs MCP Tools: Dual Interface Design" section to CLAUDE.md
  - Explains difference between user-initiated Skills (`/xtrace`) and AI-initiated MCP Tools
  - Documents Tool Search optimization behavior

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

[0.8.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.8.0
[0.7.1]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.7.1
[0.7.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.7.0
[0.6.1]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.6.1
[0.6.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.6.0
[0.3.2]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.3.2
[0.3.1]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.3.1
[0.3.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.3.0
[0.2.1]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.2.1
[0.2.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.2.0
[0.1.0]: https://github.com/koriym/xdebug-mcp/releases/tag/v0.1.0
