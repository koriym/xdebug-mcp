#!/usr/bin/env bash
# Run every CLI command documented in README.md against the demo scripts
# and report PASS/FAIL for each. Used both as a developer smoke test
# (`composer demo`) and as a hands-on tour of every tool.

set -u

cd "$(dirname "$0")/.." || {
    printf 'Failed to change directory to repository root\n' >&2
    exit 1
}

PASS=0
FAIL=0
FAIL_NAMES=()
LOG=$(mktemp -t xdebug-mcp-demo.XXXXXX)
trap 'rm -f "$LOG"' EXIT

run() {
    local label="$1"
    shift
    printf '\n=== %s ===\n' "$label"
    printf '  $ %s\n' "$*"
    if "$@" >"$LOG" 2>&1; then
        printf '  PASS\n'
        PASS=$((PASS + 1))
        return 0
    fi
    local code=$?
    printf '  FAIL (exit %d)\n' "$code"
    sed 's/^/    /' "$LOG" | tail -10
    FAIL=$((FAIL + 1))
    FAIL_NAMES+=("$label")
    return 1
}

run "xstep: breakpoint debugging" \
    ./bin/xstep --break="demo/buggy.php:22" -- php demo/buggy.php

run "xstep: conditional breakpoint" \
    ./bin/xstep --break='demo/buggy.php:22:$a==10' -- php demo/buggy.php

run "xstep: --pretty / --max-value-bytes / --max-depth" \
    ./bin/xstep --break="demo/buggy.php:22" --pretty --max-value-bytes=200 --max-depth=3 -- php demo/buggy.php

run "xtrace: execution flow" \
    ./bin/xtrace --context="Demo run" -- php demo/buggy.php

run "xprofile: performance profiling" \
    ./bin/xprofile --json -- php demo/slow.php

run "xcoverage: coverage analysis (raw mode)" \
    ./bin/xcoverage --raw -- php demo/coverage.php

run "xback: stack trace at breakpoint" \
    ./bin/xback --break="demo/buggy.php:44" -- php demo/buggy.php

run "xcompare: compare two runs" \
    ./bin/xcompare --break="demo/buggy.php:22" \
        --run-a="php demo/buggy.php" \
        --run-b="php demo/buggy.php"

run "xrepl: CLI availability (--help smoke check)" \
    ./bin/xrepl --help

printf '\n=========================\n'
printf 'PASS: %d  FAIL: %d\n' "$PASS" "$FAIL"
if [ "$FAIL" -gt 0 ]; then
    printf 'Failed:\n'
    for name in "${FAIL_NAMES[@]}"; do
        printf '  - %s\n' "$name"
    done
fi
exit "$FAIL"
