# xcompare - Breakpoint Comparison Tool

Compare variable states at the same breakpoint across two different PHP executions.

## When to Use

Use this skill when the user wants to:
- Compare how different inputs affect variable states
- Debug edge cases by comparing normal vs problematic inputs
- Analyze differences between success and failure executions
- Understand how code behaves with different conditions

## Usage

```bash
./bin/xcompare --break=FILE:LINE --run-a="CMD" --run-b="CMD" [options]
```

### Required Arguments

| Argument | Description | Example |
|----------|-------------|---------|
| `--break` | Breakpoint location | `src/Calculator.php:25` |
| `--run-a` | First execution command | `php calc.php 10` |
| `--run-b` | Second execution command | `php calc.php 0` |

### Optional Arguments

| Argument | Description |
|----------|-------------|
| `--label-a` | Label for run A (default: command string) |
| `--label-b` | Label for run B (default: command string) |
| `--context` | Context description for AI analysis |
| `--steps` | Steps to record after breakpoint (default: 1) |
| `--include-vendor` | Include vendor packages in trace |

## Examples

### Compare normal vs edge case input

```bash
./bin/xcompare \
  --break=src/Calculator.php:25 \
  --run-a="php calc.php 10" \
  --run-b="php calc.php 0" \
  --label-a="Normal input" \
  --label-b="Edge case (zero)" \
  --context="Compare division behavior with normal vs zero input"
```

### Compare authentication success vs failure

```bash
./bin/xcompare \
  --break=src/Auth.php:42 \
  --run-a="php login.php valid_user" \
  --run-b="php login.php invalid_user" \
  --context="Compare authentication flow"
```

### Compare loop iterations

```bash
./bin/xcompare \
  --break=src/Loop.php:15 \
  --run-a="php loop.php 1" \
  --run-b="php loop.php 100" \
  --context="Compare first vs last iteration"
```

## Output

JSON with the following structure:

- `breakpoint`: Location being compared
- `run_a`: First execution result with variables
- `run_b`: Second execution result with variables
- `diff`: Computed differences
  - `changed`: Variables with different values
  - `unchanged`: Variables with same values
  - `only_in_a`: Variables only in first run
  - `only_in_b`: Variables only in second run
- `analysis_hints`: Human/AI-readable summary

## Schema

Full JSON schema: https://koriym.github.io/xdebug-mcp/schemas/xcompare.json
