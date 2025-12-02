# PHP Code Coverage Analysis

Collect code coverage data for the specified PHP script or test suite.

## Usage
```
/x-coverage <php-script-or-command>
```

## Instructions

Run the following command to collect code coverage:

```bash
./bin/xdebug-coverage -- "$ARGUMENTS"
```

After execution, analyze the coverage data to identify:
- Lines executed vs not executed
- Uncovered code paths
- Test coverage percentage
- Areas needing additional tests

The output is in JSON format with the schema:
```json
{
  "coverage": {
    "/path/to/file.php": {"10": 1, "11": -1, "12": 1}
  }
}
```
- `1` = line executed
- `-1` = line not executed (executable but not covered)

If no arguments provided, ask the user which PHP script or test to analyze.
