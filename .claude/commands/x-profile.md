# PHP Performance Profiling

Profile the specified PHP script to identify performance bottlenecks.

## Usage
```
/x-profile <php-script-or-command>
```

## Instructions

Run the following command to profile PHP execution:

```bash
./bin/xdebug-profile "$ARGUMENTS"
```

After execution, analyze the profile data to identify:
- Functions consuming the most execution time
- Call counts and cumulative time
- Memory allocation hotspots
- Optimization opportunities

Provide specific recommendations for performance improvements based on the profiling results.

If no arguments provided, ask the user which PHP script to profile.
