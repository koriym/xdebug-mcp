# Repository Guidelines

## Project Overview

**xdebug-mcp** (`koriym/xdebug-mcp`) is a trace-based PHP debugging toolkit built on Xdebug 3.x, designed to be "AI native": stateless, one-shot CLI commands that emit schema-backed JSON instead of interactive debugger sessions. It is distributed as a Composer library (MIT license) and can be driven three ways:

- **CLI tools** in `bin/` — the primary interface.
- **MCP server** (`bin/xdebug-mcp`, `src/McpServer.php`) — exposes `xtrace`, `xprofile`, `xstep`, `xcoverage`, `xback` over JSON-RPC stdio for MCP-capable clients.
- **AI integrations** — Claude Code plugin (`.claude-plugin/`) and Codex/agent Skill (`skills/xdebug/SKILL.md`).

The tools work by launching the target PHP script with Xdebug loaded on-demand (Xdebug stays disabled in php.ini for daily use) and collecting trace, profile, coverage, or DBGp breakpoint data, returning structured JSON with a `$schema` URL (schemas in `docs/schemas/`).

## Technology Stack

- PHP 8.2+ (`ext-sockets`, `ext-xml` required); Xdebug 3.x must be installed on the target PHP but **not** enabled by default.
- Async runtime: `amphp/socket` and `amphp/http-server` (the DBGp debug server in `src/DebugServer.php` is built on Amp).
- PSR-3 logging (`psr/log`).
- Dev tooling: PHPUnit 11, PHPStan 2 (level 10, with a custom rule in `src/PHPStan/Rules`), Doctrine Coding Standard via PHPCS, Rector 2 (PHP 8.1 level sets, `rector.php`).
- Package metadata: `composer.json` (no Node/other build system). Autoloading is PSR-4: `Koriym\XdebugMcp\` → `src/`, `Koriym\XdebugMcp\Tests\` → `tests/`.

## Project Structure & Module Organization

- `src/` — core code under the `Koriym\XdebugMcp` namespace:
  - `McpServer.php` — JSON-RPC stdio MCP server dispatching to CLI tools.
  - `DebugServer.php` — DBGp protocol server (Amp-based) used by breakpoint tools (`xstep`, `xback`, `xcompare`, `xrepl`).
  - `XdebugTracer.php`, `TraceHelper.php`, `TraceSubscriber.php`, `TraceExtension.php`, `prepend_trace.php`, `prepend_filter.php` — execution trace capture and formatting.
  - `XdebugRunner.php`, `XdebugFinder.php` — locate the Xdebug extension and launch target PHP processes (works with Docker/Podman/Kubectl and older PHP binaries like `php@7.2`).
  - `CompareRunner.php` — runs the same script twice (or against another git worktree) and diffs variable snapshots.
  - `Dbgp/` — DBGp XML parsing. `DTO/` — typed value objects (JSON-RPC, trace stats, MCP tool lists). `Profiler/` — cachegrind analysis. `Utilities/` — path normalization, PHP command parsing, vendor filtering. `Exceptions/` — domain exceptions.
- `bin/` — CLI entrypoints (PHP scripts): `xstep`, `xtrace`, `xprofile`, `xcoverage`, `xback`, `xcompare`, `xrepl` (interactive REPL debugger), `xdebug-mcp` (MCP server), `check-env` (environment checker / installer), `test-json` (JSON regression script), `validate-profile-json`, `debug-server-mcp`.
- `tests/` — `Unit/`, `Integration/` (incl. `Integration/Cli/`), `docker/` (container integration, has its own README), `ai/` (AI tool-discoverability evaluation, has its own README), `fixtures/`, `fake/`. `tests/SimpleTest.php` is excluded from PHPCS.
- `demo/` — sample scripts (`buggy.php`, `slow.php`, `coverage.php`) and `run-demo.sh` (`composer demo`).
- `docs/` — published documentation site assets: JSON schemas (`docs/schemas/*.json`, one per tool), debugging guidelines, ADR-001 (forward-trace-only approach), `TROUBLESHOOTING.md`, `llms.txt`.
- `profiler/` — standalone trace-analysis helper subproject with its own README and schemas.
- `skills/xdebug/` — agent Skill definition; `.claude-plugin/`, `.codex/`, `.claude/` — AI assistant integration configs.
- Quality configs: `phpcs.xml`, `phpstan.neon`, `rector.php`, `phpunit.xml`.

## Build, Test, and Development Commands

- Install deps: `composer install`.
- Fast test run: `composer test` (PHPUnit).
- Full gate: `composer tests` (coding standard, static analysis, then PHPUnit).
- Coverage: `composer coverage` (uses `bin/xcoverage` + PHPUnit, writes to `build/coverage`); alternatives: `composer phpdbg`, `composer pcov`.
- Static analysis: `composer sa` (PHPStan level 10 over `src/`, including the custom `NoMixedInArrayShapeRule`).
- Coding standard: `composer cs` / autofix with `composer cs-fix`.
- JSON regression: `composer test-json`; end-to-end demo of all CLI tools: `composer demo`.
- Environment helpers: `composer check-env`, `composer install-mcp`, `composer install-desktop`.
- CI (`.github/workflows/ci.yml`) runs on pushes/PRs to `1.x`, `main`, `develop`: PHPUnit matrix over PHP 8.2–8.5 (with sockets, xml, xdebug), PHPStan, and PHPCS jobs.

## Public Output Hygiene

- Do not include local shell aliases, machine-specific PHP selector names, absolute private setup paths, or private environment setup details in public-facing docs, commits, PR bodies, issue comments, release notes, or examples.
- When reporting validation publicly, translate local setup commands to portable project commands such as `composer test`, `composer demo`, `composer cs`, `composer validate --strict`, or `php -d memory_limit=512M ./vendor/bin/phpstan`.

## Coding Style & Naming Conventions

- Follow PSR-12 with Doctrine Coding Standard tweaks (`phpcs.xml`); 4-space indent, trailing commas where allowed, single quotes unless interpolation needed.
- Every PHP file starts with `declare(strict_types=1);`; native functions are explicitly imported (`use function ...;`).
- Namespace paths mirror `src/` layout; classes/interfaces use `PascalCase`, methods/properties `camelCase`; tests end with `*Test.php`.
- Prefer typed properties/parameters/returns; keep public surface minimal and log through PSR-3.
- Run `composer cs` and `composer sa` before pushing; commit fixes in separate change sets when practical.

## Testing Guidelines

- Framework: PHPUnit 11 (`phpunit.xml`, testdox output; suites `Unit` and `Integration`). Place new unit specs in `tests/Unit`, integration in `tests/Integration`; name files `{Subject}Test.php`.
- Use data providers for matrix cases; prefer deterministic fixtures in `tests/fixtures` and fake data in `tests/fake`.
- For coverage-sensitive work, run `composer coverage`; for end-to-end CLI checks, `composer test-json` and `composer demo`.
- Include failing test reproduction when fixing bugs; keep Docker-related scenarios under `tests/docker` (see its README for `docker compose` usage).
- AI tool-discoverability evaluations live in `tests/ai/` and are run through an AI assistant, not PHPUnit.

## Commit & Pull Request Guidelines

- Commit messages are short and prefixed by scope (e.g., `docs: ...`, `refactor: ...`, `fix: ...`); group unrelated changes into separate commits.
- PRs should describe intent, approach, and risk; link issues when available and paste relevant CLI output (test/coverage snapshots, `check-env` for environment changes).
- Add screenshots or logs when touching docs/UX or CLI output formatting; note any required Xdebug/runtime configuration adjustments.

## Security & Configuration Notes

- Xdebug must be installed but normally disabled; the tools load it on-demand. Use `bin/check-env` to verify and to set up MCP/Claude integration (`.mcp.json` or `.claude/skills` symlink).
- `xcompare` options `--run-a`, `--run-b`, and `--run` are executed through the shell (quoting, redirection, and env vars work as expected) — only pass trusted input to them.
- Breakpoint tools open local DBGp sockets (Amp); Docker/Podman/Kubectl runs are supported with automatic networking detection.
- Avoid committing secrets in example configs; prefer environment variables and keep sample credentials in `demo/` only.
