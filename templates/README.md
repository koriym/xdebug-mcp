# AI Debugging Configuration Templates

This directory contains templates to configure Claude Code to use runtime analysis instead of traditional debugging methods for PHP projects.

## Purpose: Change AI Debugging Behavior

**Problem**: By default, Claude suggests adding `var_dump()` or `print_r()` statements when debugging PHP code.

**Solution**: These templates configure Claude to automatically use Xdebug profiling and tracing tools instead.

**Result**: 
- **Before**: Claude says "Add var_dump($user) to see the value"
- **After**: Claude automatically runs `./bin/xdebug-profile script.php` and says "Variable $user contains ['id'=>123, 'name'=>'John'] at line 45"

## Template Files

### `CLAUDE_DEBUG_PRINCIPLES.md` 
Complete debugging principles template featuring:
- Runtime data analysis instead of static code analysis
- Automatic Xdebug tool detection (`./bin/xdebug-*`)
- Performance profiling, code coverage, and execution tracing
- Claude Code memory system integration (`~/.claude/` + project memory)
- `@import` syntax for modular debugging principles

## Deployment Strategies

### System-Wide Installation (Affects All Projects)

⚠️ **Warning**: This applies PHP debugging to ALL projects (JavaScript, Python, etc.)

```bash
# Install globally for all projects (use with caution)
mkdir -p ~/.claude
cp templates/CLAUDE_DEBUG_PRINCIPLES.md ~/.claude/
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ~/.claude/CLAUDE.md
```

Benefits: Claude automatically uses runtime analysis across every project
Drawbacks: Non-PHP projects also get PHP-specific debugging suggestions

### Project-Specific Installation (Recommended)

**Standard Installation via Composer**
```bash
# Install the package
composer require koriym/xdebug-mcp

# Enable AI debugging guide for this PHP project only
echo "@vendor/koriym/xdebug-mcp/docs/debug_guideline_for_ai.md" >> CLAUDE.md
```

**Legacy Options (for development/testing)**
```bash
# Option 1: Direct project memory (development only)
cp templates/CLAUDE_DEBUG_PRINCIPLES.md ./CLAUDE.md

# Option 2: Modular integration (development only)
cp templates/CLAUDE_DEBUG_PRINCIPLES.md ./
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ./CLAUDE.md
```

**Option 3: Hybrid Personal + Team**
```bash
# Personal principles in user memory
mkdir -p ~/.claude
cp templates/CLAUDE_DEBUG_PRINCIPLES.md ~/.claude/
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> ~/.claude/CLAUDE.md

# Project-specific tools in project memory
echo "## Project Xdebug Tools" >> ./CLAUDE.md
echo "- Use ./bin/xdebug-profile for performance analysis" >> ./CLAUDE.md
echo "- Use ./bin/xdebug-coverage for test coverage" >> ./CLAUDE.md
```

## ✨ AI Superpowers Unlocked

After deployment, Claude automatically gains these capabilities:

### 🧠 Enhanced Intelligence
- **Precise Diagnosis**: Know exactly what happened, not what should have happened
- **Data-Driven Insights**: Recommendations based on actual execution patterns
- **Context-Aware Solutions**: Full execution context understanding

### 🚀 Revolutionary Capabilities  
- **Performance Oracle**: Microsecond-precision bottleneck identification
- **Variable Detective**: Track any variable through entire codebase execution
- **Execution Archaeologist**: Reconstruct complex application flows
- **Memory Forensics**: Detect issues before they become critical

### 💡 Automatic Tool Detection
Claude automatically detects and uses project-specific debugging tools:

```bash
# If project has these tools, Claude uses them automatically:
./bin/xdebug-profile     # Performance profiling
./bin/xdebug-coverage    # Code coverage analysis  
./bin/xdebug-trace       # Execution tracing
./bin/xdebug-debug       # Interactive & conditional debugging
./bin/xdebug-phpunit     # PHPUnit profiling/integration
./bin/xdebug-mcp         # MCP server entry point
```

### 📋 Before vs After Comparison

**❌ Traditional AI Debugging:**
- "This code might be slow"
- "Try adding var_dump to see the value"
- "The error suggests there's a problem"

**✅ Revolution AI Debugging:**
- "fibonacci() consumed 3,772μs (27.6%) with 24 recursive calls"
- "Variable $user = ['id'=>123] at line 45, becomes null at line 67"
- "Memory usage peaked at 2.3MB during data processing loop"
- "Conditional breakpoint hit: $id==0 occurred at processUser():60 with complete execution trace"

## 🔧 Advanced Integration

### Custom Xdebug Tools Integration
```bash
# If your project has custom debugging tools
echo "## Custom Debugging Tools" >> CLAUDE.md
echo "- Use ./bin/my-profiler for custom profiling" >> CLAUDE.md  
echo "- Use ./scripts/debug-trace.sh for framework-specific tracing" >> CLAUDE.md
```

### Framework-Specific Adaptations
```bash
# Laravel projects
echo "@CLAUDE_DEBUG_PRINCIPLES.md" >> CLAUDE.md
echo "" >> CLAUDE.md
echo "## Laravel Specific" >> CLAUDE.md
echo "- Use ./bin/xdebug-profile artisan commands" >> CLAUDE.md
echo "- Profile queue jobs and scheduled tasks" >> CLAUDE.md

# Symfony projects  
echo "- Profile console commands and controllers" >> CLAUDE.md
echo "- Trace service container resolution" >> CLAUDE.md
```

### Team Memory Strategies
```bash
# Option A: Shared team debugging standards
git add CLAUDE_DEBUG_PRINCIPLES.md
git add CLAUDE.md  # Contains @CLAUDE_DEBUG_PRINCIPLES.md

# Option B: Individual + shared hybrid
# Each developer: personal ~/.claude/CLAUDE.md
# Team: shared ./CLAUDE.md with project specifics
```

## 🚀 Quick Start Guide

### For PHP Developers (Recommended)
```bash
# 1. Install via Composer in your PHP project
composer require koriym/xdebug-mcp

# 2. Enable AI debugging guide
echo "@vendor/koriym/xdebug-mcp/docs/debug_guideline_for_ai.md" >> CLAUDE.md

# 3. Test - Claude now automatically uses Forward Trace tools
# Example: Ask Claude to "analyze this PHP file"
# Result: Claude runs ./bin/xdebug-profile instead of suggesting var_dump()
```

### For Development Teams
```bash
# 1. Add to your project via Composer
composer require koriym/xdebug-mcp

# 2. Enable AI debugging guide for the team
echo "@vendor/koriym/xdebug-mcp/docs/debug_guideline_for_ai.md" >> CLAUDE.md

# 3. Add project-specific notes (optional)
echo "# Project Notes: Focus on authentication and payment debugging" >> CLAUDE.md

# 4. Commit team configuration
git add CLAUDE.md && git commit -m "Enable AI Forward Trace debugging"
```

## 📊 Impact Measurement

**Verify the revolution is working:**

1. **Ask Claude**: "Analyze this PHP file"
2. **Expected behavior**: Claude automatically runs profiling/tracing tools  
3. **Old behavior**: Claude suggests adding var_dump statements

**Success indicators:**
- ✅ Claude proactively uses `./bin/xdebug-*` tools
- ✅ AI provides microsecond-level performance insights  
- ✅ Variable tracking without code modification
- ✅ Memory usage analysis with actual data

---

## 🌟 Revolution Summary

This template transforms any PHP project's AI assistance from **code guessing** to **runtime intelligence**, unlocking unprecedented debugging capabilities that work across all Claude Code sessions.
