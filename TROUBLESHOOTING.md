# Troubleshooting Guide

Common issues and solutions for xdebug-mcp users.

## 📖 Before You Start

**Essential Reading**: [docs/debug-guidelines.md](docs/debug-guidelines.md)

This guide explains the Forward Trace methodology and proper debugging workflows. Many issues stem from not following Forward Trace best practices (e.g., using `var_dump()` instead of conditional breakpoints).

## 🚨 Common Issues

### 1. MCP Server Connection Issues

**Issue**: `Connection refused` or server doesn't start
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | php bin/xdebug-mcp
# Error: Connection refused
```

**Solutions**:
- Check PHP path: `which php`
- Verify script permissions: `chmod +x bin/xdebug-mcp`
- Test basic PHP execution: `php bin/xdebug-mcp --help`

### 2. Xdebug Not Available

**Issue**: `Xdebug not available` error messages

**Check Xdebug Installation**:
```bash
./bin/check-env
```

**Solutions**:
- **macOS**: `brew install php-xdebug`
- **Ubuntu/Debian**: `apt-get install php-xdebug`
- **CentOS/RHEL**: `yum install php-xdebug`

**Verify Installation**:
```bash
php -dzend_extension=xdebug -m | grep xdebug
```

### 3. Context Memory Not Working

**Issue**: `/x-trace last="true"` shows "Script argument is required"

**Solutions**:
- Ensure you've run the command at least once before using `last="true"`
- Check permissions on temp directory: `ls -la /tmp/xdebug-mcp-context.json`
- Clear context memory: `rm /tmp/xdebug-mcp-context.json`

**Test Context Memory**:
```bash
# First, run a command to populate memory
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"tests/fake/loop-counter.php","context":"Test"}}}' | php bin/xdebug-mcp

# Then test 'last' functionality
echo '{"jsonrpc":"2.0","id":2,"method":"prompts/get","params":{"name":"x-trace","arguments":{"last":"true"}}}' | php bin/xdebug-mcp
```

### 4. Invalid Breakpoint Format

**Issue**: PHP Fatal Error when setting breakpoints

**Correct Formats**:
- `file.php:line` - Simple breakpoint
- `file.php:line:condition` - Conditional breakpoint
- `file1.php:5,file2.php:10` - Multiple breakpoints

**Examples**:
```bash
# ✅ Good
"breakpoints": "src/User.php:42"
"breakpoints": "src/User.php:42:$id>100"
"breakpoints": "src/User.php:15,src/Auth.php:25"

# ❌ Bad
"breakpoints": "invalid:format:test"
"breakpoints": "User.php" # Missing line number
"breakpoints": "src/User.php:abc" # Non-numeric line
```

### 5. File Not Found Errors

**Issue**: `Script file not found` errors

**Solutions**:
- Use absolute paths: `"/full/path/to/script.php"`
- Check working directory: `pwd`
- Verify file exists: `ls -la tests/fake/loop-counter.php`
- Check file permissions: `chmod 644 script.php`

### 6. Permission Denied

**Issue**: `Permission denied accessing` file

**Solutions**:
```bash
# Fix script permissions
chmod 755 bin/xdebug-mcp
chmod 644 tests/fake/*.php

# Check parent directory permissions
ls -la tests/fake/
```

### 7. Composer x-test Not Working

**Issue**: `composer x-test` command not found

**Solutions**:
- Update Composer: `composer self-update`
- Reinstall dependencies: `composer install`
- Check script definition: `composer show-scripts`

## 🔧 Diagnostic Commands

### Environment Check
```bash
./bin/check-env
```

### MCP Server Status
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' | php bin/xdebug-mcp
```

### Available Commands
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/list"}' | php bin/xdebug-mcp
```

### Test Basic Functionality
```bash
composer test-json
```

## 🐛 Debug Mode

Enable detailed logging:
```bash
MCP_DEBUG=1 php bin/xdebug-mcp
```

## 📝 Error Codes

| Exit Code | Meaning | Solution |
|-----------|---------|----------|
| 0 | Success | Normal operation |
| 1 | General error | Check error message |
| 255 | PHP Fatal Error | Usually breakpoint or file issues |

## 🆘 Getting Help

### 1. Check Logs
```bash
# Enable debug mode
MCP_DEBUG=1 echo '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"test.php"}}}' | php bin/xdebug-mcp
```

### 2. Validate Environment
```bash
./bin/check-env
composer test-json
```

### 3. Minimal Reproduction
Create a minimal test case:
```bash
# Test with simple script
echo '<?php echo "Hello World";' > test.php
echo '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"x-trace","arguments":{"script":"test.php","context":"Test"}}}' | php bin/xdebug-mcp
```

### 4. Report Issues
When reporting issues, include:
- Output of `./bin/check-env`
- Full error message
- Minimal reproduction case
- Operating system and PHP version

## 🔄 Reset and Clean Start

### Clear Context Memory
```bash
rm /tmp/xdebug-mcp-context.json
```

### Reinstall Dependencies
```bash
composer install --no-cache
```

### Reset Xdebug Configuration
```bash
# Check current Xdebug config
php -dzend_extension=xdebug -r "var_dump(ini_get_all('xdebug'));"
```

## ⚡ Performance Tips

### 1. Use Specific Test Patterns
```bash
# Instead of running all tests
composer x-test

# Run specific tests only
composer x-test tests/Unit/UserTest.php::testLogin
```

### 2. Optimize Breakpoints
```bash
# Use conditional breakpoints to reduce noise
"breakpoints": "src/User.php:42:$user->id>100"
```

### 3. Clear Old Trace Files
```bash
# Clean up old trace files
rm /tmp/trace.*.xt
```

This troubleshooting guide should help users resolve the most common issues encountered when using xdebug-mcp.