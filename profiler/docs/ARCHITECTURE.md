# Architecture Documentation

## Overview

The Xdebug Trace Analyzer is designed as a modular, AI-first analysis system that transforms raw Xdebug trace data into structured, actionable insights.

## Design Principles

### 1. **AI-First Architecture**
- **JSON-centric**: All output designed for AI consumption
- **Structured data**: Hierarchical organization for easy parsing
- **Context-aware**: Every analysis includes contextual metadata
- **Schema-driven**: Strict JSON Schema compliance for predictable AI interaction

### 2. **Separation of Concerns**
- **Analysis Engine**: Core trace parsing and data extraction
- **Utilities**: Comparison, summarization, and specialized analysis
- **CLI Interface**: User-friendly command-line tools
- **Schema Definition**: Formal data structure specification

### 3. **Performance-Oriented**
- **Streaming parsing**: Process large files without full memory load
- **Efficient data structures**: Optimized for both speed and memory usage
- **Lazy evaluation**: Generate complex analyses only when requested

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         CLI Layer                           │
├─────────────────┬─────────────────┬─────────────────────────┤
│  trace-analyze  │ trace-summary   │ trace-compare/bottlenecks│
└─────────────────┴─────────────────┴─────────────────────────┘
                             │
┌─────────────────────────────────────────────────────────────┐
│                    Business Logic Layer                     │
├─────────────────────────────┬───────────────────────────────┤
│    XdebugTraceAnalyzerV2    │         TraceUtils           │
│                             │                               │
│  - parseTraceFile()         │  - compareTwoAnalyses()      │
│  - performPerformanceAnalysis() │  - generateSummaryReport() │
│  - generateWarnings()       │  - extractTopBottlenecks()   │
│  - searchFunction()         │                               │
└─────────────────────────────┴───────────────────────────────┘
                             │
┌─────────────────────────────────────────────────────────────┐
│                     Data Layer                              │
├─────────────────────────────┬───────────────────────────────┤
│      Raw Trace Data         │        JSON Schema           │
│                             │                               │
│  - Xdebug .xt files         │  - trace-analysis-schema.json│
│  - Function calls           │  - Validation rules          │
│  - Timing data              │  - AI strategies             │
│  - Memory usage             │  - Type definitions          │
└─────────────────────────────┴───────────────────────────────┘
```

## Core Components

### XdebugTraceAnalyzerV2

**Purpose**: Main analysis engine that transforms raw trace data into structured JSON

**Key Methods**:
```php
public function analyzeTrace(string $context = ''): array
public function searchFunction(string $functionName): array  
public function exportJson(array $options = []): string
public function validateSchema(): array
```

**Data Flow**:
1. **parseTraceFile()**: Stream-parse .xt file, extract function calls
2. **performPerformanceAnalysis()**: Calculate durations, memory deltas, rankings
3. **generateWarnings()**: Detect performance issues, classify severity
4. **exportJson()**: Format as schema-compliant JSON

### TraceUtils

**Purpose**: Advanced analysis utilities for comparison and summarization

**Key Methods**:
```php
public static function compareTwoAnalyses(array $analysis1, array $analysis2): array
public static function generateSummaryReport(array $analysis): array
public static function extractTopBottlenecks(array $analysis, int $limit = 10): array
```

**Capabilities**:
- **Performance comparison**: Before/after analysis with regression detection
- **Executive summaries**: High-level insights for stakeholders
- **Bottleneck ranking**: Priority-based optimization targets

## Data Structures

### Analysis Result Schema

```json
{
  "📊 metadata": {
    "🕒 generated_at": "2025-08-31T12:00:00Z",
    "📁 source_trace_file": "/tmp/trace.xt",
    "⏱️ total_execution_time": 1.234,
    "💾 peak_memory_usage": 7654321
  },
  "📈 statistics": {
    "🔢 unique_functions_count": 123,
    "📂 unique_files_count": 45,
    "🏷️ vendor_vs_user": {
      "👤 user_functions": 67,
      "📦 vendor_functions": 34,
      "⚙️ internal_functions": 22
    }
  },
  "🚀 performance_analysis": {
    "🐌 slowest_functions": [...],
    "💾 memory_intensive_functions": [...],
    "⚠️ performance_warnings": [...]
  },
  "🗂️ function_index": {...},
  "📂 file_index": {...}
}
```

### Performance Entry Structure

```json
{
  "🏷️ function_name": "Class::method",
  "⏱️ duration_seconds": 0.123,
  "💾 memory_delta_bytes": 1048576,
  "📊 memory_delta_formatted": "+1.0MB",
  "📍 trace_start_line": 1234,
  "🏗️ call_depth_level": 5,
  "💡 optimization_priority": "medium",
  "📊 impact_score": 75.5
}
```

## Processing Pipeline

### 1. **Input Processing**
```php
// Raw Xdebug trace format
"2    15    0    0.001234  1048576  MyClass::method  1  /path/file.php  42"
```

### 2. **Data Extraction**
```php
$parts = preg_split('/\t/', $line);
$level = (int)$parts[0];           // Call depth
$functionNumber = (int)$parts[1];   // Unique ID
$type = $parts[2];                  // Entry(0)/Exit(1)/Return(R)
$time = (float)$parts[3];          // Timestamp
$memory = (int)$parts[4];          // Memory usage
$functionName = $parts[5];         // Function name
```

### 3. **Analysis Processing**
```php
// Calculate function duration
$duration = $exitTime - $entryTime;
$memoryDelta = $exitMemory - $entryMemory;

// Classify performance
$priority = $this->calculateOptimizationPriority($duration, $memoryDelta);
$impactScore = $this->calculateImpactScore($duration, $memoryDelta);
```

### 4. **JSON Generation**
```php
// Structure data according to schema
$result = [
    '📊 metadata' => [...],
    '📈 statistics' => [...],
    '🚀 performance_analysis' => [
        '🐌 slowest_functions' => $this->rankByDuration(),
        '💾 memory_intensive_functions' => $this->rankByMemory(),
        '⚠️ performance_warnings' => $this->generateWarnings()
    ]
];
```

## Performance Characteristics

### Time Complexity
- **Parsing**: O(n) where n = number of trace lines
- **Analysis**: O(f log f) where f = number of functions  
- **Sorting**: O(f log f) for performance rankings
- **Search**: O(f) for function search

### Space Complexity
- **Memory usage**: ~2x trace file size during processing
- **Output size**: ~0.1-0.5x trace file size (compressed JSON)
- **Index structures**: O(f) for function/file indexes

### Scalability Limits
- **Small traces** (< 1MB): < 1 second processing
- **Medium traces** (1-10MB): 1-10 seconds processing
- **Large traces** (10-100MB): 10-60 seconds processing  
- **Very large traces** (> 100MB): May require streaming optimization

## Error Handling

### Exception Hierarchy
```php
RuntimeException
├── FileNotFoundException
├── ParseException  
├── ValidationException
└── MemoryExhaustedException
```

### Error Response Format
```json
{
  "error": "Detailed error message",
  "trace_file": "/path/to/trace.xt",
  "line": 123,
  "file": "TraceAnalyzer.php",
  "context": "Additional context information"
}
```

## Extension Points

### 1. **Custom Analyzers**
```php
interface AnalyzerInterface {
    public function analyze(array $traceData): array;
    public function getSchema(): string;
}
```

### 2. **Output Formats**
```php
interface FormatterInterface {
    public function format(array $analysisData): string;
    public function getMimeType(): string;
}
```

### 3. **Data Sources**
```php
interface TraceReaderInterface {
    public function readTrace(string $source): Generator;
    public function validateFormat(string $source): bool;
}
```

## Security Considerations

### 1. **Input Validation**
- **File path validation**: Prevent directory traversal
- **Size limits**: Prevent memory exhaustion
- **Format validation**: Ensure valid trace format

### 2. **Output Sanitization**
- **JSON encoding**: Prevent XSS in JSON output
- **Path sanitization**: Clean file paths in output
- **Error message filtering**: Avoid information disclosure

### 3. **Resource Limits**
- **Memory limits**: Configurable maximum memory usage
- **Time limits**: Configurable maximum execution time
- **File size limits**: Maximum trace file size

## Testing Strategy

### 1. **Unit Tests**
- **Parser validation**: Test trace line parsing
- **Analysis correctness**: Verify calculation accuracy
- **Schema compliance**: Validate JSON output format

### 2. **Integration Tests**
- **End-to-end CLI**: Test complete workflows
- **File processing**: Various trace file formats
- **Error handling**: Exception scenarios

### 3. **Performance Tests**
- **Scalability**: Large file processing
- **Memory usage**: Monitor resource consumption
- **Speed benchmarks**: Processing time measurements

### 4. **Schema Validation**
- **JSON Schema**: Automated validation against schema
- **AI compatibility**: Test with actual AI systems
- **Backward compatibility**: Ensure schema evolution compatibility

## Future Architecture Considerations

### 1. **Microservices Architecture**
- **Analysis service**: Core processing logic
- **Comparison service**: Before/after analysis
- **Reporting service**: Summary generation
- **API gateway**: Unified interface

### 2. **Streaming Processing**
- **Real-time analysis**: Process traces as they're generated
- **Incremental updates**: Update analysis as new data arrives
- **Memory efficiency**: Process without full file loading

### 3. **Distributed Analysis**
- **Horizontal scaling**: Process large traces across multiple nodes
- **MapReduce pattern**: Distribute analysis workload
- **Result aggregation**: Combine distributed analysis results
