# Troubleshooting Guide

Common issues and solutions for PHP Xdebug MCP Server users.

## 📖 Before You Start

**Essential Reading**: 
- [docs/debug_guideline_for_ai.md](docs/debug_guideline_for_ai.md) - AI debugging methodology
- [docs/debug_guideline_for_humans.md](docs/debug_guideline_for_humans.md) - Human workflow guide

This guide explains Forward Trace™ methodology and proper debugging workflows. Many issues stem from not following Forward Trace best practices (e.g., using `var_dump()` instead of conditional breakpoints).

## 🚨 Quick Diagnostics

Run these commands first to identify the problem:

```bash
./bin/check-env          # Check Xdebug installation
composer test-json       # Test MCP functionality
MCP_DEBUG=1 php bin/xdebug-mcp  # Enable debug logging
```

## 🔧 Environment Issues

### 1. Xdebug Not Available

**Issue**: `Xdebug extension not found` or `xdebug_* function undefined`

**Check Installation**:
```bash
php -m | grep xdebug          # Should show 'xdebug'
php -r "var_dump(extension_loaded('xdebug'));"  # Should show 'bool(true)'
```

**Solutions by OS**:
```bash
# macOS with Homebrew
brew install php-xdebug

# Ubuntu/Debian
sudo apt-get install php-xdebug

# CentOS/RHEL
sudo yum install php-xdebug

# Manual installation
sudo pecl install xdebug
```

**Verify Configuration**:
```bash
php -dzend_extension=xdebug -r "echo 'Xdebug version: ' . phpversion('xdebug');"
```

### 2. Wrong PHP Version

**Issue**: Tools fail with `Parse error` or `syntax error`

**Check PHP Version**:
```bash
php -v  # Must be PHP 8.0+
which php
```

**Solution**: Update PHP or adjust PATH:
```bash
# macOS: Use Homebrew PHP
export PATH="/usr/local/bin:$PATH"

# Ubuntu: Install PHP 8.x
sudo apt-get install php8.1-cli php8.1-xdebug
```

### 3. Port Conflicts

**Issue**: `Address already in use` or connection timeouts

**Check Port Usage**:
```bash
lsof -i :9004  # Xdebug MCP Server port
lsof -i :9003  # IDE debugging port
```

**Solution**: Kill conflicting processes:
```bash
pkill -f "xdebug"
pkill -f "9004"
```

## 🚀 Forward Trace Issues

### 1. Conditional Breakpoints Not Triggering

**Issue**: Breakpoint never hits despite condition being true

**Common Mistakes**:
```bash
# ❌ Wrong file path (relative path issues)
--break="User.php:42:$id==null"

# ✅ Correct (absolute or properly relative path)
--break="src/User.php:42:$id==null"
--break="/full/path/to/User.php:42:$id==null"
```

**Debug Solutions**:
```bash
# Verify file exists and line number is valid
wc -l src/User.php  # Check total lines
head -n 50 src/User.php | tail -n 10  # Check around line 42

# Test with simple breakpoint first
./bin/xdebug-debug --break="src/User.php:42" -- php script.php
```

### 2. Step Recording Not Working

**Issue**: `--steps` parameter ignored or produces no output

**Check Command Format**:
```bash
# ✅ Correct format
./bin/xdebug-debug --break="loop.php:15" --steps=100 --json -- php script.php

# ❌ Wrong format
./bin/xdebug-debug --break="loop.php:15" --steps 100  # Missing equals sign
```

**Verify Output**:
```bash
# Check if JSON contains "breaks" array with step data
./bin/xdebug-debug --break="test.php:1" --steps=5 --json -- php -r "echo 'test';" | jq '.breaks'
```

### 3. Context Memory Problems

**Issue**: `last="true"` shows "No previous context found"

**Solutions**:
```bash
# Clear stale context
rm /tmp/xdebug-mcp-context.json

# Check permissions
ls -la /tmp/xdebug-mcp-context.json

# Test context saving
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"demo.php","context":"Test run"}}}' | php bin/xdebug-mcp

# Then test retrieval
echo '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"x-trace","arguments":{"last":"true"}}}' | php bin/xdebug-mcp
```

## 📊 MCP Protocol Issues

### 1. JSON-RPC Errors

**Issue**: `Parse error` or `Invalid request` from MCP server

**Common JSON Issues**:
```json
// ❌ Missing quotes in condition
{"breakpoints": "User.php:42:$id==null"}

// ✅ Properly escaped condition  
{"breakpoints": "User.php:42:$id==null"}

// ❌ Invalid JSON structure
{"script": "test.php" "context": "test"}

// ✅ Valid JSON structure
{"script": "test.php", "context": "test"}
```

**Test JSON Validity**:
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php bin/xdebug-mcp
```

### 2. MCP Server Not Responding

**Issue**: Commands hang or timeout

**Debug Steps**:
```bash
# Test basic connectivity
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"capabilities":{}}}' | timeout 10 php bin/xdebug-mcp

# Check for background processes
ps aux | grep xdebug-mcp

# Enable debug logging
MCP_DEBUG=1 echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php bin/xdebug-mcp
```

## 🎯 Usage Pattern Issues

### 1. Performance Problems

**Issue**: Tools run slowly or consume excessive memory

**Optimizations**:
```bash
# Use specific conditions to limit scope
./bin/xdebug-debug --break='file.php:42:$specific_condition' --exit-on-break

# Limit step recording
./bin/xdebug-debug --break='file.php:42' --steps=50  # Instead of 1000+

# Clean old trace files
rm /tmp/trace.*.xt /tmp/cachegrind.out.*
```

### 2. Output Format Issues

**Issue**: Output not in expected format or missing data

**Format Solutions**:
```bash
# Ensure JSON output for AI processing
./bin/xdebug-profile --json -- php script.php

# Verify schema compliance
./bin/validate-profile-json profile-output.json

# Check output structure
./bin/xdebug-debug --json --break="test.php:1" --steps=1 -- php -r "echo 'test';" | jq '.'
```

### 3. File Path Issues

**Issue**: `File not found` errors despite file existing

**Path Solutions**:
```bash
# Use absolute paths
./bin/xdebug-trace -- php /full/path/to/script.php

# Check working directory
pwd
ls -la script.php

# Fix permissions
chmod 644 script.php
chmod 755 $(dirname script.php)
```

## 🧪 Testing Issues

### 1. PHPUnit Integration Problems

**Issue**: `./bin/xdebug-phpunit` not working with tests

**Solutions**:
```bash
# Verify PHPUnit installation
vendor/bin/phpunit --version

# Test basic PHPUnit functionality
vendor/bin/phpunit tests/Unit/

# Check Xdebug + PHPUnit integration
./bin/xdebug-phpunit --context="Test integration" tests/Unit/McpServerTest.php
```

### 2. Fake Test Environment

**Issue**: Tests fail in development without real Xdebug

**Use Fake Environment**:
```bash
# Run comprehensive fake tests
php tests/fake/demo.php

# Test specific fake scenarios
php tests/fake/run.php array-manipulation
php tests/fake/run.php conditional-logic
```

## 🔄 Reset Procedures

### Complete Environment Reset

```bash
# 1. Clear all temporary files
rm /tmp/xdebug-mcp-context.json
rm /tmp/trace.*.xt
rm /tmp/cachegrind.out.*

# 2. Reinstall dependencies
composer install --no-cache

# 3. Verify installation
./bin/check-env
composer test-json

# 4. Test basic functionality
echo '<?php echo "Reset test\n";' > reset-test.php
./bin/xdebug-trace --context="Reset verification" -- php reset-test.php
rm reset-test.php
```

## 📝 Error Reference

### Exit Codes

| Code | Meaning | Solution |
|------|---------|----------|
| 0 | Success | Normal operation |
| 1 | General error | Check error message and fix accordingly |
| 125 | File not found | Verify file path and permissions |
| 126 | Permission denied | Fix file/directory permissions |
| 127 | Command not found | Check PHP installation and PATH |
| 255 | PHP Fatal Error | Usually syntax or breakpoint format issues |

### Common Error Messages

**"Xdebug extension not found"**
→ Install Xdebug extension for your PHP version

**"Script file not found"** 
→ Use absolute path or verify working directory

**"Invalid breakpoint format"**
→ Use format: `file.php:line` or `file.php:line:condition`

**"Address already in use"**
→ Kill conflicting processes using port 9004

**"Parse error: syntax error"**
→ Check PHP version compatibility (requires PHP 8.0+)

## 🆘 Getting Help

### Before Reporting Issues

1. **Run diagnostics**:
   ```bash
   ./bin/check-env > diagnostics.txt
   php -v >> diagnostics.txt
   php -m | grep xdebug >> diagnostics.txt
   ```

2. **Create minimal reproduction**:
   ```bash
   echo '<?php echo "Test\n";' > minimal-test.php
   ./bin/xdebug-trace --json --context="Minimal test" -- php minimal-test.php
   ```

3. **Include system information**:
   - Operating system and version
   - PHP version and installation method
   - Xdebug version
   - Full error message with stack trace

### Issue Reporting Template

```
**Environment:**
- OS: [e.g., macOS 13.5, Ubuntu 22.04]
- PHP: [output of `php -v`]
- Xdebug: [output of `php -r "echo phpversion('xdebug');"`]

**Command that fails:**
[exact command that reproduces the issue]

**Expected behavior:**
[what you expected to happen]

**Actual behavior:**
[what actually happened]

**Error message:**
[complete error message]

**Diagnostics output:**
[output of `./bin/check-env`]
```

This guide covers the most common issues. For complex problems, enable debug mode (`MCP_DEBUG=1`) and examine the detailed logs.