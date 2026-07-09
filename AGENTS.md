# Repository Guidelines

## Project Structure & Module Organization
- Core code lives in `src/` under the `Koriym\XdebugMcp` namespace; CLI entrypoints are in `bin/` (`xstep`, `xtrace`, `xprofile`, `xcoverage`, `xback`, `check-env`).
- Tests are in `tests/` (`Unit/`, `Integration/`, `docker/`, `ai/`), with fixtures under `tests/fixtures` and fake data under `tests/fake`.
- Documentation and assets reside in `docs/`; sample scenarios are under `demo/`; profiler helpers live in `profiler/`.
- Quality configs: `phpcs.xml`, `phpstan.neon`, `rector.php`, `phpunit.xml`.

## Build, Test, and Development Commands
- Install deps: `composer install`.
- Fast test run: `composer test` (PHPUnit).
- Full gate: `composer tests` (coding standard, static analysis, then PHPUnit).
- Coverage: `composer coverage` (uses `bin/xcoverage` + PHPUnit, writes to `build/coverage`).
- Static analysis: `composer sa` (PHPStan).
- Coding standard: `composer cs` / autofix with `composer cs-fix`.
- Environment helpers: `composer check-env`, `composer install-mcp`, `composer install-desktop`.

## Public Output Hygiene
- Do not include local shell aliases, machine-specific PHP selector names, absolute private setup paths, or private environment setup details in public-facing docs, commits, PR bodies, issue comments, release notes, or examples.
- When reporting validation publicly, translate local setup commands to portable project commands such as `composer test`, `composer demo`, `composer cs`, `composer validate --strict`, or `php -d memory_limit=512M ./vendor/bin/phpstan`.

## Coding Style & Naming Conventions
- Follow PSR-12 with Doctrine Coding Standard tweaks (`phpcs.xml`); 4-space indent, trailing commas where allowed, single quotes unless interpolation needed.
- Namespace paths mirror `src/` layout; classes/interfaces use `PascalCase`, methods/properties `camelCase`; tests end with `*Test.php`.
- Prefer typed properties/parameters/returns; keep public surface minimal and log through PSR-3.
- Run `composer cs` and `composer sa` before pushing; commit fixes in separate change sets when practical.

## Testing Guidelines
- Framework: PHPUnit (`phpunit.xml`). Place new unit specs in `tests/Unit`, integration in `tests/Integration`; name files `{Subject}Test.php`.
- Use data providers for matrix cases; prefer deterministic fixtures in `tests/fixtures`.
- For coverage-sensitive work, run `composer coverage`; for end-to-end CLI checks, `composer test-json`.
- Include failing test reproduction when fixing bugs; keep Docker-related scenarios under `tests/docker`.

## Commit & Pull Request Guidelines
- Commit messages are short and prefixed by scope (e.g., `docs: ...`, `refactor: ...`, `fix: ...`); group unrelated changes into separate commits.
- PRs should describe intent, approach, and risk; link issues when available and paste relevant CLI output (test/coverage snapshots, `check-env` for environment changes).
- Add screenshots or logs when touching docs/UX or CLI output formatting; note any required Xdebug/runtime configuration adjustments.

## Security & Configuration Notes
- Xdebug must be installed but normally disabled; use `bin/check-env` to verify and to set up MCP/Claude integration (`.mcp.json` or `.claude/skills` symlink).
- Avoid committing secrets in example configs; prefer environment variables and keep sample credentials in `demo/` only.
