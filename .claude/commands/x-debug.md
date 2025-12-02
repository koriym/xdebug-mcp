# PHP Interactive Step Debugging

Start an interactive debugging session for the specified PHP script.

## Usage
```
/x-debug <php-script>
```

## Instructions

Run the following command to start interactive debugging:

```bash
./bin/xdebug-debug --context="<brief description of what you're debugging>" "$ARGUMENTS"
```

The `--context` option creates self-explanatory debugging data for AI analysis.

This starts a debugging session where you can:
- Set breakpoints with `xdebug_set_breakpoint`
- Step through code with `xdebug_step_into`, `xdebug_step_over`, `xdebug_step_out`
- Inspect variables with `xdebug_get_variables`
- Evaluate expressions with `xdebug_eval`
- Continue execution with `xdebug_continue`

**Important**: Use `--exit-on-break` option if you want to stop at the first breakpoint and output debug state:

```bash
./bin/xdebug-debug --exit-on-break "$ARGUMENTS"
```

If no arguments provided, ask the user which PHP script to debug.
