# Xdebug Conditional Debugging for AI Analysis

This file provides guidance for AI systems analyzing Xdebug conditional debugging results.

## What You Receive

When xdebug-debug runs with --json flag, you get structured data:
```json
{"trace_file":"/tmp/trace.1034012359.xt","lines":11,"size":0.3,"command":"php demo.php"}
```

## Reading Strategy Based on File Size

**Small files (size < 1KB):** Read the entire trace file directly. These contain focused execution data up to the breakpoint hit.

**Medium files (1-100KB):** Use grep to find the breakpoint hit location first, then read surrounding context. Focus on the final 50-100 lines where the condition was triggered.

**Large files (>100KB):** Use tail to examine the end where the breakpoint hit occurred. The conditional breakpoint means you only care about execution leading to the problem condition.

## Understanding the Command Field

The command shows exactly what was executed:
- `--break='file.php:line:condition'` tells you what condition triggered
- `--exit-on-break` means execution stopped at first condition match
- The target script and arguments show the execution context

## Trace File Format

Xdebug trace files (.xt) use tab-separated format:
```
Level   FuncID  Time    Memory  Function  UserDef  File:Line  Params/Return
2       1       0.003   396784  rand      0        test.php:3  0 1
2       1       1       0.004   396848                         R 0
```

**Key columns for debugging:**
- Level: Call stack depth (higher = deeper nested)
- Function: What function was called
- UserDef: 1=your code, 0=built-in PHP function  
- Params/Return: Arguments passed or return values (R prefix)

## Analyzing Conditional Breakpoints

**Your goal:** Understand why the condition was true at that moment.

1. **Find the breakpoint hit:** Look for execution around the target file and line
2. **Trace variable changes:** Follow parameter values and return values leading to the condition
3. **Identify the trigger:** What specific function call or value change caused the condition to become true

## Common Debugging Patterns

**Null value debugging** (`$var==null`): Look for where the variable was last assigned or passed as parameter.

**Negative value debugging** (`$total<0`): Find the calculation that made the value negative.

**Empty collection debugging** (`empty($array)`): Trace where items should have been added to the array.

## Practical Analysis Steps

1. Check the trace file size to choose your reading strategy
2. Parse the command to understand what condition was triggered
3. Read the trace file focusing on the execution path to the breakpoint
4. Identify the specific function calls and variable states that led to the condition
5. Provide specific recommendations based on the actual runtime data

Remember: This is real runtime data, not static code analysis. You can see exactly what happened, not what should have happened.

## Links for Reference

- Xdebug trace format: https://xdebug.org/docs/trace
- Trace format settings: https://xdebug.org/docs/all_settings#trace_format