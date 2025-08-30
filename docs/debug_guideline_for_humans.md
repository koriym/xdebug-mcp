---
title: "Forward Trace™ Debugging Guide for Developers"
description: "Complete guide for PHP developers: From var_dump debugging to Forward Trace methodology for runtime analysis without code modifications"
permalink: /debug-guidelines/
layout: default
---

# Forward Trace™ Debugging Guide for Developers

## 🚀 Quick Start: From var_dump to Forward Trace

**Problem**: Traditional debugging modifies your code with temporary debug statements.  
**Solution**: Forward Trace captures runtime behavior without touching your source code.

### Before vs After
```php
// ❌ OLD WAY: Modify code, test, clean up, repeat
var_dump($user);
echo "Debug checkpoint 1";
print_r($cart);
die("HERE"); // Risk: accidentally commit debug code

// ✅ NEW WAY: One command, complete analysis
./bin/xdebug-debug --break='Auth.php:42:$user==null' --exit-on-break -- php app.php
// Result: Complete execution trace when $user is null, no code changes
```

## 🎯 Daily Development Workflows

### 1. Variable Inspection (Replaces var_dump)
```bash
# Instead of adding var_dump($variable) to your code
./bin/xdebug-debug --break='file.php:line' --steps=1 --context="Checking variable state" -- php script.php

# Example: Check what's in $user at line 42
./bin/xdebug-debug --break='Auth.php:42' --steps=1 --context="User object inspection" -- php login.php
```

**Benefits**:
- No code modification
- No risk of committing debug statements
- See ALL variables in scope, not just one
- Complete execution context included

### 2. Loop Debugging (Replaces multiple var_dumps)
```bash
# Instead of: foreach($items as $item) { var_dump($item); }
./bin/xdebug-debug --break='DataProcessor.php:45' --steps=100 --context="Processing loop analysis" -- php import.php

# Watch variables evolve through 100 iterations
# Memory usage, performance, variable states all captured
```

### 3. Intermittent Bug Hunting
```bash
# For bugs that "sometimes happen"
./bin/xdebug-debug --break='Payment.php:*:$total<0' --exit-on-break --context="Negative total bug hunt" -- php checkout.php

# Runs normally until the bug occurs, then captures complete trace
# No more "I can't reproduce it" situations
```

### 4. Performance Investigation
```bash
# Find bottlenecks in your code
./bin/xdebug-profile --context="API endpoint performance analysis" -- php api.php

# AI analyzes results:
# "fetchUser() called 847 times (72% execution time). Add caching at line 42."
```

## 🛠️ Command Patterns by Use Case

### Authentication & User Management
```bash
# Null user debugging
./bin/xdebug-debug --break='Auth.php:*:$user==null' --exit-on-break --context="Authentication failure analysis" -- php login.php

# Permission issues
./bin/xdebug-debug --break='Security.php:15:!$hasPermission' --exit-on-break --context="Permission denied investigation" -- php dashboard.php

# Session problems
./bin/xdebug-debug --break='Session.php:*:empty($_SESSION)' --exit-on-break --context="Session management debug" -- php app.php
```

### Database & API Issues
```bash
# Failed queries
./bin/xdebug-debug --break='DB.php:*:$result===false' --exit-on-break --context="Database query failure" -- php data.php

# Slow queries (>500ms)
./bin/xdebug-debug --break='DB.php:*:$queryTime>0.5' --exit-on-break --context="Slow query identification" -- php reports.php

# API errors
./bin/xdebug-debug --break='ApiClient.php:*:$response["error"]' --exit-on-break --context="API integration issues" -- php sync.php
```

### Data Processing & Validation
```bash
# Invalid data detection
./bin/xdebug-debug --break='Validator.php:*:count($errors)>0' --exit-on-break --context="Validation error analysis" -- php form.php

# Memory leaks in loops
./bin/xdebug-debug --break='Import.php:150' --steps=200 --context="Memory usage during data import" -- php import.php

# Array manipulation issues
./bin/xdebug-debug --break='Transform.php:*:empty($result)' --exit-on-break --context="Data transformation problems" -- php process.php
```

## 🤝 Working with AI Analysis

### The Human-AI Workflow
1. **Human**: Identifies there's a problem
2. **Human**: Sets up Forward Trace capture
3. **AI**: Analyzes the runtime data
4. **Human**: Implements the suggested fix

### Example Collaboration
```bash
# 1. Human sets up capture
./bin/xdebug-debug --break='Cart.php:89:$total<0' --exit-on-break --context="Negative cart total investigation" -- php checkout.php

# 2. Forward Trace captures the problem moment
# Output: Complete execution trace + JSON data

# 3. Human asks AI to analyze
claude --continue "Analyze why the cart total became negative"

# 4. AI provides specific analysis:
# "At line 67, removeItem() doesn't call recalculateDiscount(). 
#  $50 discount applied to $30 cart = -$20 total.
#  Fix: Add $this->recalculateDiscount() after removeItem()"

# 5. Human implements the fix (no more guesswork!)
```

## 📋 Best Practices for Development Teams

### 1. Context Documentation
Always use meaningful `--context` descriptions:
```bash
# ✅ GOOD: Self-explanatory
--context="User registration with invalid email format"
--context="Payment processing timeout during peak hours"
--context="File upload failure with large PDF documents"

# ❌ BAD: Requires external knowledge
--context="Bug fix"
--context="Testing"
--context="Debug session"
```

### 2. Conditional Breakpoint Strategy
Target specific problem conditions, not normal flow:
```bash
# ✅ Target problems
--break='file.php:line:$error_condition'

# ❌ Break on normal execution
--break='file.php:line'  # Will break every time
```

### 3. Team Debug Session Sharing
```bash
# Generate portable debug session
./bin/xdebug-debug --break='bug.php:42' --steps=100 --json --context="Customer #12345 checkout failure" > debug-session.json

# Share with team
git add debug-session.json
git commit -m "Add debug session for checkout issue #456"

# Any team member or AI can analyze the same data
claude --file debug-session.json "Analyze this debug session"
```

## 🔧 IDE and Editor Integration

### VS Code Integration
```json
// .vscode/tasks.json
{
  "version": "2.0.0",
  "tasks": [
    {
      "label": "Debug Current File",
      "type": "shell",
      "command": "./bin/xdebug-debug",
      "args": [
        "--break=${file}:${lineNumber}",
        "--steps=10",
        "--context=IDE debugging session",
        "--",
        "php",
        "${file}"
      ],
      "group": "build"
    }
  ]
}
```

### Command Line Aliases
```bash
# Add to ~/.bashrc or ~/.zshrc
alias xd-var='./bin/xdebug-debug --break'
alias xd-profile='./bin/xdebug-profile --context'
alias xd-trace='./bin/xdebug-trace --context'

# Usage examples
xd-var 'Auth.php:42:$user==null' --exit-on-break -- php login.php
xd-profile "Performance analysis" -- php slow-script.php
```

### Git Integration
```bash
# .gitignore additions for Forward Trace files
/tmp/xdebug_trace_*
/tmp/cachegrind.out.*
debug-session-*.json

# But keep important debug sessions
!debug-sessions/critical-bug-*.json
```

## 🚨 Troubleshooting Common Issues

### 1. Xdebug Not Loading
```bash
# Check if Xdebug is available
php -m | grep xdebug

# If not found, install
# macOS with Homebrew:
brew install php-xdebug

# Ubuntu/Debian:
sudo apt-get install php-xdebug

# Manual installation:
pecl install xdebug
```

### 2. Trace Files Not Generated
```bash
# Check permissions
ls -la /tmp/

# Make sure /tmp is writable
chmod 755 /tmp

# Test trace generation
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace test.php
ls -la /tmp/xdebug_trace*
```

### 3. Breakpoints Not Hitting
```bash
# Verify file path is correct (use absolute paths)
./bin/xdebug-debug --break='/full/path/to/file.php:42' -- php script.php

# Check if line number exists
wc -l file.php  # Should be > your line number

# Verify condition syntax
# Use simple conditions first: $var==null, $var>0, empty($var)
```

### 4. Performance Issues
```bash
# Only load Xdebug when needed
php script.php                                     # Normal execution
php -dzend_extension=xdebug.so script.php         # With Xdebug

# Use specific conditions to limit scope
./bin/xdebug-debug --break='file.php:line:$specific_condition' --exit-on-break
```

## 📊 Understanding Output

### JSON Output Structure
```json
{
  "$schema": "https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json",
  "context": "User authentication analysis",
  "breaks": [
    {
      "step": 1,
      "location": {"file": "Auth.php", "line": 42},
      "variables": {
        "$user": "null",
        "$token": "string: abc123",
        "$session": "array: [...]"
      }
    }
  ],
  "trace": {
    "file": "/tmp/trace.1034012359.xt",
    "summary": "224 function calls, 15ms execution time"
  }
}
```

### Trace File Quick Analysis
```bash
# Find the problem function
grep -E "(main|your_function)" /tmp/trace.*.xt

# Check memory usage
awk '{print $3}' /tmp/trace.*.xt | sort -n | tail -5

# Find slow operations
awk '{print $2, $0}' /tmp/trace.*.xt | sort -n | tail -10
```

## 🎯 Migration Guide: From Debug Statements to Forward Trace

### Week 1: Replace Critical Debug Points
- Identify your most common debug locations
- Replace them with Forward Trace breakpoints
- Keep a reference sheet of your common commands

### Week 2: Team Training
- Share successful Forward Trace examples
- Create team-specific command aliases
- Set up shared debug session storage

### Week 3: Process Integration
- Add Forward Trace to bug report templates
- Include in code review checklists
- Set up CI integration for automated profiling

### Week 4: Full Migration
- Remove all var_dump() statements from codebase
- Set up linting rules to prevent debug statement commits
- Celebrate your debugging revolution!

## 🏆 Success Indicators

You'll know Forward Trace is working when:
- ✅ No more accidentally committed debug statements
- ✅ Bug reproduction becomes reliable and fast
- ✅ Performance bottlenecks identified with precision
- ✅ Team debug sessions are shareable and analyzable
- ✅ AI provides specific, actionable debugging insights
- ✅ Debugging time decreases from hours to minutes

---

## 🌟 The Developer Experience Revolution

Forward Trace transforms debugging from a disruptive, invasive process into a seamless, professional workflow. No more code pollution, no more guesswork, no more time wasted on reproduction attempts.

**Your code stays clean. Your debugging gets smarter. Your AI gets superpowers.**