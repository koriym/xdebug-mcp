# Profiler Integration Plan

## 🎯 Strategic Vision: AI Navigation Tools

### Core Philosophy
**Existing xdebug-mcp**: Real-time analysis & debugging (< 10MB)
**New Integration**: Large trace navigation & AI indexing (> 10MB)

## 📋 Integration Roadmap

### Phase 1: Core Component Integration (Week 1)
```
1. Copy key files to xdebug-mcp:
   - profiler/src/TraceAnalyzer.php → src/Navigation/TraceNavigator.php
   - profiler/src/TraceUtils.php → src/Navigation/TraceUtils.php
   - profiler/schemas/ → schemas/navigation/

2. Namespace integration:
   - Koriym\XdebugMcp\Navigation\TraceNavigator
   - Maintain compatibility with existing structure
```

### Phase 2: MCP Tool Definitions (Week 1)
```json
{
  "name": "xdebug_navigate_trace",
  "description": "Navigate large trace files with AI-optimized indexing",
  "parameters": {
    "trace_file": "Path to .xt trace file",
    "context": "Analysis context for AI",
    "search": "Function name to search for (optional)",
    "time_range": "Time range filter (optional)"
  }
}
```

### Phase 3: CLI Integration (Week 2) - SINGLE TOOL APPROACH
```bash
# ONE unified tool for all trace analysis needs
./bin/xdebug-analyze trace.xt --context="login failure analysis"

# All functionality through options:
./bin/xdebug-analyze trace.xt --summary                    # Summary generation
./bin/xdebug-analyze trace1.xt trace2.xt --compare         # Trace comparison  
./bin/xdebug-analyze trace.xt --bottlenecks=10             # Extract bottlenecks
./bin/xdebug-analyze trace.xt --search="UserController"    # Function search
```

### Phase 4: Documentation & Testing (Week 2)
- Update CLAUDE.md with navigation tools usage
- Add integration tests
- Create example workflows

## 🔧 Technical Implementation

### Directory Structure After Integration
```
xdebug-mcp/
├── src/
│   ├── McpServer.php           # Add new MCP tools
│   ├── XdebugFinder.php        # Existing (recently added)  
│   ├── XdebugProfiler.php      # Existing profiler
│   ├── XdebugTracer.php        # Existing tracer
│   └── Profiler/               # NEW - Advanced profiling tools
│       ├── TraceAnalyzer.php   # Core large-file analyzer
│       ├── TraceUtils.php      # Comparison & utilities
│       ├── IndexBuilder.php    # AI-optimized indexing
│       └── NavigationTools.php # CLI integration helper
├── bin/
│   └── xdebug-analyze         # NEW - Unified trace analysis tool
├── schemas/
│   └── navigation/            # NEW
│       └── trace-analysis.json
```

### Key Classes to Create/Modify

#### 1. New Navigation Classes
```php
namespace Koriym\XdebugMcp\Navigation;

class TraceNavigator 
{
    // Based on XdebugTraceAnalyzerV2
    public function navigateTrace(string $file, array $options): array;
    public function searchFunctions(string $pattern): array;
    public function filterTimeRange(string $start, string $end): array;
}

class IndexBuilder
{
    // Hybrid functionality
    public function buildIndex(string $traceFile): array;
    public function generateAISummary(array $index, string $context): array;
}
```

#### 2. Extended McpServer
```php
// Add to existing McpServer.php
public function handleNavigateTrace(array $args): array;
public function handleIndexTrace(array $args): array;
public function handleCompareTraces(array $args): array;
public function handleBottlenecks(array $args): array;
```

## 🚀 Implementation Steps

### Step 1: File Structure Setup
1. Create `src/Navigation/` directory
2. Copy and adapt core classes with proper namespacing
3. Update autoloader in composer.json

### Step 2: MCP Integration
1. Add 4 new MCP tool definitions in McpServer.php
2. Implement handler methods
3. Test MCP protocol compliance

### Step 3: CLI Tools Creation
1. Create 4 new bin/ executables
2. Ensure consistent argument parsing
3. Integrate with existing XdebugFinder for Xdebug detection

### Step 4: Schema Integration
1. Copy JSON schema to schemas/navigation/
2. Update all outputs to comply with schema
3. Add schema validation option

### Step 5: Documentation Update
1. Update CLAUDE.md with new tools usage
2. Add examples in README.md
3. Create migration guide

## 🎯 Success Metrics

### Technical Metrics
- [ ] All existing MCP tools continue working unchanged
- [ ] New tools handle files > 10MB efficiently
- [ ] Memory usage < 2x of trace file size
- [ ] Analysis speed < 10 seconds for 125MB files

### User Experience Metrics
- [ ] Clear distinction between trace/navigate tools
- [ ] Consistent CLI interface
- [ ] AI-optimized JSON output
- [ ] Comprehensive documentation

### Integration Quality
- [ ] Zero breaking changes to existing functionality  
- [ ] Seamless namespace integration
- [ ] Consistent error handling
- [ ] Complete test coverage

## 📝 Risk Mitigation

### Potential Issues & Solutions
1. **Memory Usage**: Use streaming analysis approach
2. **Performance**: Implement indexing and caching
3. **Compatibility**: Maintain existing API contracts
4. **Complexity**: Clear documentation and examples

### Rollback Plan
All new functionality is additive - can be removed without affecting existing features.

## 🏁 Definition of Done

Integration is complete when:
1. ✅ 4 new MCP tools working
2. ✅ 4 new CLI commands available
3. ✅ Large file navigation demonstrated (> 50MB)
4. ✅ AI-optimized output validated
5. ✅ Documentation updated
6. ✅ Tests passing
7. ✅ Zero regression in existing functionality

---

**Next Action**: Begin Phase 1 implementation with file structure setup.