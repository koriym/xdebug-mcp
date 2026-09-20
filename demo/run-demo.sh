#!/usr/bin/env bash
# Exercise every documented feature of the CLI tools and the MCP server
# against the demo scripts, reporting PASS/FAIL/SKIP for each. Used both as
# a developer smoke test (`composer demo`) and as a hands-on tour.
#
# Checks that need something this machine may not have — a second PHP
# version, a container runtime — are reported as SKIP rather than failing.
#
# Out of scope: the interactive REPL (`xrepl` needs a TTY, so only `--help`
# is invoked) and the `--claude` flags (they shell out to the Claude CLI).

set -u

cd "$(dirname "$0")/.." || {
    printf 'Failed to change directory to repository root\n' >&2
    exit 1
}

PASS=0
FAIL=0
SKIP=0
FAIL_NAMES=()
LOG=$(mktemp -t xdebug-mcp-demo.XXXXXX)
trap 'rm -f "$LOG"' EXIT

HOST_PHP=$(php -r 'echo PHP_BINARY;')
HOST_VERSION=$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')

section() {
    printf '\n\n########## %s ##########\n' "$1"
}

run() {
    local label="$1"
    shift
    printf '\n=== %s ===\n' "$label"
    printf '  $ %s\n' "$*"
    "$@" >"$LOG" 2>&1
    local code=$?
    if [ "$code" -eq 0 ]; then
        printf '  PASS\n'
        PASS=$((PASS + 1))
        return 0
    fi
    printf '  FAIL (exit %d)\n' "$code"
    sed 's/^/    /' "$LOG" | tail -10
    FAIL=$((FAIL + 1))
    FAIL_NAMES+=("$label")
    return 1
}

# Run a shell snippet that must both succeed and print the expected text, for
# checks where a zero exit code alone would not show the feature worked.
run_expect() {
    local label="$1" expected="$2" snippet="$3"
    printf '\n=== %s ===\n' "$label"
    printf '  $ %s\n' "$snippet"
    bash -c "$snippet" >"$LOG" 2>&1
    local code=$?
    if [ "$code" -eq 0 ] && grep -q -- "$expected" "$LOG"; then
        printf '  PASS (found: %s)\n' "$expected"
        PASS=$((PASS + 1))
        return 0
    fi
    printf '  FAIL (exit %d, expected to find: %s)\n' "$code" "$expected"
    sed 's/^/    /' "$LOG" | tail -10
    FAIL=$((FAIL + 1))
    FAIL_NAMES+=("$label")
    return 1
}

skip() {
    printf '\n=== %s ===\n' "$1"
    printf '  SKIP (%s)\n' "$2"
    SKIP=$((SKIP + 1))
}

# A PHP on PATH whose version differs from the one running this script, used
# for the cross-version checks. Set XDEBUG_MCP_DEMO_ALT_PHP to name one
# explicitly; otherwise common versioned names are tried.
find_alternate_php() {
    if [ -n "${XDEBUG_MCP_DEMO_ALT_PHP:-}" ]; then
        printf '%s' "$XDEBUG_MCP_DEMO_ALT_PHP"
        return
    fi

    local candidate path version
    for candidate in php8.5 php8.4 php8.3 php8.2 php8.1 php8.0 php7.4 php7.3 php7.2; do
        path=$(command -v "$candidate" 2>/dev/null) || continue
        # A target can print startup warnings to stdout before the answer.
        version=$("$path" -r 'echo "\n", PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null | tail -1)
        if [ -n "$version" ] && [ "$version" != "$HOST_VERSION" ]; then
            printf '%s' "$path"
            return
        fi
    done
}

section "Environment"

# check-env exits 1 to report an environment it considers suboptimal — most
# often Xdebug being loaded unconditionally, which is how CI images ship it.
# That is a verdict about the machine, not a failure of the tool, so only a
# crash (anything past exit 1) counts against the demo.
printf '\n=== %s ===\n' "check-env: Xdebug detection and extension check"
printf '  $ %s\n' "./bin/check-env"
./bin/check-env >"$LOG" 2>&1
CHECK_ENV_CODE=$?
sed 's/^/    /' "$LOG"
if [ "$CHECK_ENV_CODE" -le 1 ]; then
    printf '  PASS (exit %d)\n' "$CHECK_ENV_CODE"
    PASS=$((PASS + 1))
else
    printf '  FAIL (exit %d)\n' "$CHECK_ENV_CODE"
    FAIL=$((FAIL + 1))
    FAIL_NAMES+=("check-env: Xdebug detection and extension check")
fi

section "xtrace — execution flow"

run "xtrace: default (human-readable summary)" \
    ./bin/xtrace -- php demo/buggy.php

run_expect "xtrace: --json emits a schema-backed document" '"schema"' \
    './bin/xtrace --json -- php demo/buggy.php'

run "xtrace: --context for AI analysis" \
    ./bin/xtrace --context="Why does the average come out wrong?" -- php demo/buggy.php

run "xtrace: --include-vendor keeps selected packages" \
    ./bin/xtrace --json --include-vendor="psr/log" -- php demo/buggy.php

section "xprofile — performance"

run "xprofile: default (compact report)" \
    ./bin/xprofile -- php demo/slow.php

run_expect "xprofile: --json reports bottlenecks" '"bottlenecks"' \
    './bin/xprofile --json --context="Find the slow path" -- php demo/slow.php'

section "xcoverage — coverage"

run "xcoverage: --raw (default compact format)" \
    ./bin/xcoverage --raw -- php demo/coverage.php

run_expect "xcoverage: --raw --json reports a summary" '"summary"' \
    './bin/xcoverage --raw --json -- php demo/coverage.php'

run "xcoverage: --raw --branch-coverage" \
    ./bin/xcoverage --raw --branch-coverage -- php demo/coverage.php

run "xcoverage: --raw --source restricts the measured paths" \
    ./bin/xcoverage --raw --json --source=demo -- php demo/coverage.php

run "xcoverage: --raw --include-vendor" \
    ./bin/xcoverage --raw --json --include-vendor="psr/log" -- php demo/coverage.php

run "xcoverage: --cwd runs from another project root" \
    ./bin/xcoverage --raw --cwd=demo -- php coverage.php

run "xcoverage: --php selects the target binary" \
    ./bin/xcoverage --raw --php="$HOST_PHP" -- php demo/coverage.php

run "xcoverage: PHPUnit mode (honors @codeCoverageIgnore)" \
    ./bin/xcoverage -- ./vendor/bin/phpunit --filter SimpleTest

section "xstep — breakpoint debugging"

run_expect "xstep: breakpoint captures variables" '"variables"' \
    './bin/xstep --break="demo/buggy.php:22" -- php demo/buggy.php'

run "xstep: conditional breakpoint" \
    ./bin/xstep --break='demo/buggy.php:22:$a==10' -- php demo/buggy.php

run "xstep: --steps records variable evolution" \
    ./bin/xstep --break="demo/buggy.php:22" --steps=5 -- php demo/buggy.php

run "xstep: --watch records only when an expression changes" \
    ./bin/xstep --break="demo/buggy.php:22" --steps=5 --watch='$a' -- php demo/buggy.php

run "xstep: --pretty / --max-value-bytes / --max-depth" \
    ./bin/xstep --break="demo/buggy.php:22" --pretty --max-value-bytes=200 --max-depth=3 -- php demo/buggy.php

run "xstep: interpreter options before the script" \
    ./bin/xstep --break="demo/buggy.php:22" -- php -d memory_limit=256M demo/buggy.php

run "xstep: absolute PHP binary after --" \
    ./bin/xstep --break="demo/buggy.php:22" -- "$HOST_PHP" demo/buggy.php

section "xback — call stack"

run_expect "xback: stack at a breakpoint" '"stack"' \
    './bin/xback --break="demo/buggy.php:44" -- php demo/buggy.php'

run_expect "xback: without --break, the first executable line" '"stack"' \
    './bin/xback -- php demo/buggy.php'

run "xback: --depth bounds the reported frames" \
    ./bin/xback --break="demo/buggy.php:44" --depth=20 -- php demo/buggy.php

run "xback: --cwd runs from another project root" \
    ./bin/xback --break="buggy.php:44" --cwd=demo -- php buggy.php

run "xback: --php selects the target binary" \
    ./bin/xback --break="demo/buggy.php:44" --php="$HOST_PHP" -- php demo/buggy.php

section "xcompare — two runs side by side"

run_expect "xcompare: --run-a / --run-b" '"diff"' \
    './bin/xcompare --break="demo/buggy.php:22" --run-a="php demo/buggy.php" --run-b="php demo/buggy.php"'

run "xcompare: --label-a / --label-b / --steps / --context" \
    ./bin/xcompare --break="demo/buggy.php:22" \
        --run-a="php demo/buggy.php" --run-b="php demo/buggy.php" \
        --label-a="baseline" --label-b="candidate" --steps=3 \
        --context="Same input twice, so nothing should differ"

if git rev-parse --verify --quiet HEAD~1 >/dev/null 2>&1; then
    run_expect "xcompare: --compare-with a git ref" '"run_b"' \
        './bin/xcompare --break="demo/buggy.php:22" --run="php demo/buggy.php" --compare-with=HEAD~1'
else
    skip "xcompare: --compare-with a git ref" "no HEAD~1 in this checkout"
fi

section "Cross-version targets"

ALT_PHP=$(find_alternate_php)
if [ -n "$ALT_PHP" ]; then
    ALT_VERSION=$("$ALT_PHP" -r 'echo "\n", PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null | tail -1)
    printf '\n  Using %s (PHP %s) as the target; this script runs on PHP %s\n' \
        "$ALT_PHP" "$ALT_VERSION" "$HOST_VERSION"

    run "xtrace: target on another PHP version" \
        ./bin/xtrace --json -- "$ALT_PHP" demo/buggy.php

    run "xstep: target on another PHP version" \
        ./bin/xstep --break="demo/buggy.php:22" -- "$ALT_PHP" demo/buggy.php

    run "xback: --php targeting another PHP version" \
        ./bin/xback --break="demo/buggy.php:44" --php="$ALT_PHP" -- php demo/buggy.php
else
    skip "cross-version targets (xtrace / xstep / xback)" \
        "no second PHP version on PATH; set XDEBUG_MCP_DEMO_ALT_PHP to enable"
fi

section "MCP server (JSON-RPC over stdio)"

# These confirm the request/response path: the server starts, negotiates, and
# dispatches to each tool. Whether a given argument changed the command it
# built is asserted in the test suite, not here.

run_expect "MCP: server/discover advertises protocol versions" '"supportedVersions"' \
    'printf "%s\n" "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"server/discover\"}" | ./bin/xdebug-mcp 2>/dev/null'

run_expect "MCP: initialize negotiates a legacy session" '"serverInfo"' \
    'printf "%s\n" "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"2025-11-25\",\"capabilities\":{},\"clientInfo\":{\"name\":\"demo\",\"version\":\"1\"}}}" | ./bin/xdebug-mcp 2>/dev/null'

run_expect "MCP: tools/list exposes the one-shot tools" '"xcompare"' \
    'printf "%s\n" "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\"}" | ./bin/xdebug-mcp 2>/dev/null'

run_expect "MCP: tools/call runs xtrace" '"content"' \
    'printf "%s\n" "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"xtrace\",\"arguments\":{\"script\":\"php demo/buggy.php\",\"context\":\"demo\"}}}" | ./bin/xdebug-mcp 2>/dev/null'

run_expect "MCP: tools/call runs xcoverage in raw mode" '"content"' \
    'printf "%s\n" "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"xcoverage\",\"arguments\":{\"script\":\"php demo/coverage.php\",\"raw\":\"true\"}}}" | ./bin/xdebug-mcp 2>/dev/null'

# A stateless request carries both _meta fields; omitting clientCapabilities
# is rejected as invalid params before the version is even looked at.
run_expect "MCP: unsupported protocol version is rejected" '\-32022' \
    'printf "%s\n" "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/list\",\"params\":{\"_meta\":{\"io.modelcontextprotocol/protocolVersion\":\"1999-01-01\",\"io.modelcontextprotocol/clientCapabilities\":{\"tools\":{}}}}}" | ./bin/xdebug-mcp 2>/dev/null'

section "Containers"

# Gated on the image already being cached: a demo run must not start a
# multi-hundred-megabyte pull behind the user's back.
if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
    skip "xtrace: Docker target" "no usable docker daemon"
elif ! docker image inspect php:8.3-cli >/dev/null 2>&1; then
    skip "xtrace: Docker target" "php:8.3-cli not pulled; run 'docker pull php:8.3-cli' to enable"
else
    run "xtrace: Docker target" \
        ./bin/xtrace -- docker run --rm --pull=never -v "$PWD:/app" -w /app php:8.3-cli php demo/buggy.php
fi

section "Interactive"

run "xrepl: presence check (--help; the REPL itself needs a TTY)" \
    ./bin/xrepl --help

printf '\n-------------------------\n'
printf 'PASS: %d  FAIL: %d  SKIP: %d\n' "$PASS" "$FAIL" "$SKIP"
if [ "$FAIL" -gt 0 ]; then
    printf 'Failed:\n'
    for name in "${FAIL_NAMES[@]}"; do
        printf '  - %s\n' "$name"
    done
fi
exit "$FAIL"
