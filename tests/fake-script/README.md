# Forward Trace Test Scripts

This directory contains test scripts demonstrating different debugging patterns for Forward Trace analysis.

## 🎯 Purpose

These scripts showcase how Forward Trace debugging captures variable state evolution across breakpoints, enabling AI to understand code behavior through runtime observation rather than static analysis.

## 📁 Test Patterns

### 1. **loop-counter.php** - Loop Progression Pattern
```php
// Tests: Counter incrementation, array iteration, result accumulation
./bin/xdebug-debug --context="Testing loop counter progression" --break=tests/fake-script/loop-counter.php:8,13,17,24,29 --exit-on-break -- php tests/fake-script/loop-counter.php
```
**Demonstrates:**
- Variable progression through loop iterations
- Array processing and result building
- Counter state changes at each breakpoint

### 2. **array-manipulation.php** - Array Filtering Pattern
```php
// Tests: User filtering, statistical accumulation, conditional processing
./bin/xdebug-debug --context="Array manipulation with user filtering" --break=tests/fake-script/array-manipulation.php:8,14,18,21,23,29,33 --exit-on-break -- php tests/fake-script/array-manipulation.php
```
**Demonstrates:**
- Dynamic array building based on conditions
- Statistical counter incrementation
- Complex data structure manipulation

### 3. **object-state.php** - Object State Evolution
```php
// Tests: Method chaining, object property changes, state tracking
./bin/xdebug-debug --context="Object state tracking with method chaining" --break=tests/fake-script/object-state.php:12,19,23,32,34,38,40,42 --exit-on-break -- php tests/fake-script/object-state.php
```
**Demonstrates:**
- Object property evolution through method calls
- Method chaining state changes
- History tracking and state comparison

### 4. **conditional-logic.php** - Flag-Based Logic Pattern
```php
// Tests: Boolean flag evolution, conditional branching, running totals
./bin/xdebug-debug --context="Conditional logic with flag tracking" --break=tests/fake-script/conditional-logic.php:8,18,21,24,27,32,37 --exit-on-break -- php tests/fake-script/conditional-logic.php
```
**Demonstrates:**
- Boolean flag state changes
- Complex conditional logic execution
- Running sum calculations with conditions

### 5. **nested-loops.php** - Complex Iteration Pattern
```php
// Tests: Matrix processing, nested accumulation, pattern detection
./bin/xdebug-debug --context="Nested loops matrix processing" --break=tests/fake-script/nested-loops.php:8,17,21,22,25,32,34,39,41 --exit-on-break -- php tests/fake-script/nested-loops.php
```
**Demonstrates:**
- Multi-dimensional array processing
- Nested loop variable evolution
- Complex accumulation patterns

### 6. **error-simulation.php** - Error Handling Pattern
```php
// Tests: Error collection, null/empty handling, edge cases
./bin/xdebug-debug --context="Error handling simulation" --break=tests/fake-script/error-simulation.php:12,15,18,21,25,30,31,36 --exit-on-break -- php tests/fake-script/error-simulation.php
```
**Demonstrates:**
- Error state accumulation
- Null and empty value handling
- Edge case processing and validation

## 🚀 Running Tests

### Individual Test
```bash
./bin/xdebug-debug --context="Your description here" --break=file.php:line1,line2 --exit-on-break -- php tests/fake-script/pattern.php > output.json
```

### All Tests (JSON Output)
```bash
php tests/fake-script/run.php > all_tests.json
```

### Schema Validation
```bash
vendor/bin/validate-json output.json schema/xdebug-debug.json
```

## 📊 Expected Output

Each test generates JSON conforming to the `xdebug-debug.json` schema:

```json
{
    "$schema": "https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json",
    "context": "Your description here", 
    "breaks": [
        {
            "step": 1,
            "location": {"file": "pattern.php", "line": 8},
            "variables": {
                "$counter": "int: 0",
                "$items": "array: [0: \"apple\", 1: \"banana\"]"
            }
        }
    ],
    "trace": {
        "file": "/tmp/trace.123456.xt",
        "content": ["Version: 3.4.4", "..."]
    }
}
```

## 🔍 Analysis Focus

These patterns enable AI to:

1. **Track Variable Evolution**: See how values change over time
2. **Understand Control Flow**: Follow actual execution paths
3. **Identify Patterns**: Recognize common programming constructs
4. **Debug Issues**: Spot where variables become unexpected values
5. **Performance Analysis**: Observe memory and execution patterns

## 🌟 Forward Trace Benefits

- **No Code Modification**: Original scripts remain unchanged
- **Complete Context**: Every variable state captured
- **AI-Friendly**: Structured JSON output for analysis
- **Self-Documenting**: Context field explains purpose
- **Reproducible**: Same input always produces same trace

## 💡 Usage Tips

1. **Use Context**: Always provide `--context` for self-explanatory data
2. **Strategic Breakpoints**: Place at variable mutation points
3. **Schema Validation**: Verify output with `validate-json`
4. **Save Output**: Redirect to `.json` files for sharing
5. **Multiple AI Analysis**: Same JSON works across different AI systems

These test scripts demonstrate the power of Forward Trace debugging: understanding code through runtime observation rather than static analysis guesswork.
