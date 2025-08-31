# Basic Usage Examples

This document provides practical examples of using the Xdebug Trace Analyzer.

## Quick Start Examples

### 1. Basic Analysis
```bash
# Generate a trace file first
php -dxdebug.mode=trace -dxdebug.start_with_request=yes -dxdebug.trace_output_dir=/tmp your-app.php

# Analyze the trace
./xdebug-profiler/bin/trace-analyze /tmp/trace.12345.xt --context="Basic application analysis"
```

### 2. Performance Investigation
```bash
# Analyze with context
./bin/trace-analyze /tmp/slow-request.xt --context="Investigating slow API endpoint performance"

# Generate summary
./bin/trace-summary summary analysis-result.json

# Extract top bottlenecks
./bin/trace-bottlenecks analysis-result.json 5
```

## Real-World Scenarios

### Scenario 1: Login Performance Issue

**Problem**: Users report slow login process

```bash
# Step 1: Generate trace during login
php -dxdebug.mode=trace app/login.php

# Step 2: Analyze focusing on authentication
./bin/trace-analyze /tmp/trace.*.xt --context="User login performance investigation"

# Step 3: Find authentication-related functions
./bin/trace-analyze /tmp/trace.*.xt --search="authenticate"

# Step 4: Get executive summary
./bin/trace-summary summary login-analysis.json
```

**Expected Output**:
```json
{
  "📋 executive_summary": {
    "⏱️ total_execution_time": 2.5,
    "🎯 top_optimization_targets": [
      {
        "🏷️ function_name": "User::validateCredentials",
        "⏱️ duration_seconds": 1.8,
        "💡 optimization_priority": "critical"
      }
    ]
  }
}
```

### Scenario 2: Before/After Optimization

**Problem**: Compare performance before and after adding caching

```bash
# Before optimization
./bin/trace-analyze /tmp/before-cache.xt --context="Before implementing cache" > before.json

# After optimization  
./bin/trace-analyze /tmp/after-cache.xt --context="After implementing cache" > after.json

# Compare results
./bin/trace-compare before.json after.json
```

**Expected Output**:
```json
{
  "📈 execution_time_diff": {
    "📊 percentage_change": -65.2,
    "⏱️ time_difference": -1.8
  },
  "💡 interpretation": [
    "🚀 Significant performance improvement: 65.2% faster execution",
    "✅ Overall function performance improved (15 improvements vs 2 regressions)"
  ]
}
```

### Scenario 3: Memory Leak Investigation

**Problem**: Application consuming too much memory

```bash
# Analyze memory-intensive execution
./bin/trace-analyze /tmp/memory-issue.xt --context="Memory leak investigation in data processing"

# Extract memory-intensive functions
./bin/trace-bottlenecks memory-analysis.json 10
```

**Analysis Focus**:
```json
{
  "💾 memory_intensive_functions": [
    {
      "🏷️ function_name": "DataProcessor::loadLargeDataset",
      "💾 memory_delta_formatted": "+50.2MB",
      "💡 optimization_priority": "critical",
      "💡 suggestion": "Consider streaming or chunked processing"
    }
  ]
}
```

## Integration Examples

### With CI/CD Pipeline

```yaml
# .github/workflows/performance.yml
name: Performance Analysis
on: [push, pull_request]

jobs:
  performance:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP with Xdebug
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
          extensions: xdebug
          
      - name: Run Performance Test
        run: |
          php -dxdebug.mode=trace tests/PerformanceTest.php
          
      - name: Analyze Trace
        run: |
          ./xdebug-profiler/bin/trace-analyze /tmp/trace.*.xt \
            --context="CI Performance Test - Commit ${{ github.sha }}" \
            > performance-result.json
            
      - name: Generate Report
        run: |
          ./xdebug-profiler/bin/trace-summary summary performance-result.json \
            > performance-report.json
            
      - name: Check Performance Regression
        run: |
          # Fail build if performance score < 80
          SCORE=$(jq '.["📋 executive_summary"]["📊 performance_score"]' performance-report.json)
          if (( $(echo "$SCORE < 80" | bc -l) )); then
            echo "Performance regression detected: Score $SCORE"
            exit 1
          fi
```

### With Docker

```dockerfile
# Dockerfile for analysis environment
FROM php:8.1-cli

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Copy analyzer
COPY xdebug-profiler /app/xdebug-profiler

# Configure Xdebug for trace
RUN echo "xdebug.mode=trace" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.trace_output_dir=/tmp" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /app
```

```bash
# Use with Docker
docker build -t trace-analyzer .
docker run -v $(pwd):/app trace-analyzer \
  /app/xdebug-profiler/bin/trace-analyze /tmp/trace.xt --context="Docker analysis"
```

### Batch Processing

```bash
#!/bin/bash
# analyze-batch.sh - Process multiple trace files

TRACE_DIR="/tmp/traces"
OUTPUT_DIR="/tmp/analysis-results"
mkdir -p "$OUTPUT_DIR"

for trace_file in "$TRACE_DIR"/trace.*.xt; do
    filename=$(basename "$trace_file")
    context="Batch analysis of $filename"
    
    echo "Analyzing $filename..."
    
    # Generate analysis
    ./bin/trace-analyze "$trace_file" --context="$context" \
        > "$OUTPUT_DIR/analysis-$filename.json"
    
    # Generate summary
    ./bin/trace-summary summary "$OUTPUT_DIR/analysis-$filename.json" \
        > "$OUTPUT_DIR/summary-$filename.json"
        
    # Extract bottlenecks
    ./bin/trace-bottlenecks "$OUTPUT_DIR/analysis-$filename.json" 5 \
        > "$OUTPUT_DIR/bottlenecks-$filename.json"
done

echo "Batch analysis complete. Results in $OUTPUT_DIR"
```

## Debugging Specific Issues

### N+1 Database Query Problem

```bash
# Look for database-related functions
./bin/trace-analyze trace.xt --search="query" --context="N+1 query investigation"
```

### Recursive Function Issues

```bash
# Find functions with high call depth
./bin/trace-bottlenecks analysis.json 20 | jq '.["🎯 top_bottlenecks"][] | select(.["🏗️ call_depth_level"] > 10)'
```

### Framework Integration Issues

```bash
# Analyze framework-specific functions (if vendor code included in trace)
./bin/trace-analyze trace.xt --search="Framework" --context="Framework integration debugging"
```

## Output Interpretation Guide

### Performance Scores
- **90-100**: Excellent performance
- **70-89**: Good performance, minor optimizations possible
- **50-69**: Fair performance, optimization recommended
- **30-49**: Poor performance, optimization required
- **0-29**: Critical performance issues

### Priority Levels
- **Critical**: Functions taking >1s or using >10MB memory
- **High**: Functions taking >0.5s or using >5MB memory  
- **Medium**: Functions taking >0.1s or using >1MB memory
- **Low**: All other functions

### Memory Delta Interpretation
- **Positive values**: Memory allocation (normal)
- **Large positive values** (>5MB): Potential memory inefficiency
- **Negative values**: Memory deallocation (rare but possible)

## Troubleshooting

### Common Issues

1. **"Trace file not found"**
   ```bash
   # Check if Xdebug generated the trace
   ls -la /tmp/trace.*.xt
   
   # Verify Xdebug configuration
   php -m | grep xdebug
   ```

2. **"Invalid JSON" errors**
   ```bash
   # Check file permissions
   ls -la analysis-result.json
   
   # Validate JSON manually
   cat analysis-result.json | python -m json.tool
   ```

3. **Large file processing**
   ```bash
   # Use compact mode for large traces
   ./bin/trace-analyze large-trace.xt --compact --context="Large file analysis"
   ```

4. **Memory issues during analysis**
   ```bash
   # Increase PHP memory limit
   php -d memory_limit=1G ./bin/trace-analyze trace.xt
   ```
