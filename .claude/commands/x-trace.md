# PHP Execution Trace

Execute the specified PHP script with Xdebug tracing enabled and analyze the execution flow.

## Usage
```
/x-trace <php-script-or-command>
```

## Instructions

Run the following command to trace PHP execution:

```bash
./bin/xdebug-trace "$ARGUMENTS"
```

After execution, analyze the trace output to identify:
- Function call hierarchy and execution flow
- Parameter values passed to each function
- Memory usage patterns
- Execution timing for performance insights

If no arguments provided, ask the user which PHP script to trace.
