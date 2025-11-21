# Docker Integration Test Environment

This directory contains a test environment to verify xdebug-mcp works correctly with Docker.

## Quick Start

### 1. Build and Start Container

```bash
cd tests/docker

# Build the container
docker compose build

# Start the container
docker compose up -d

# Verify it's running
docker compose ps
```

### 2. Run Test Scripts

```bash
# Test 1: Basic script execution
docker compose run --rm php php /app/test_script.php

# Test 2: Symfony-like application simulation
docker compose run --rm php php /app/test_symfony_like.php

# Test 3: Coverage test
docker compose run --rm php php /app/test_coverage.php

# Test 4: With Xdebug trace mode
docker compose run --rm -e XDEBUG_MODE=trace php \
  php -dxdebug.mode=trace -dxdebug.start_with_request=yes /app/test_script.php

# Test 5: With Xdebug profile mode
docker compose run --rm -e XDEBUG_MODE=profile php \
  php -dxdebug.mode=profile -dxdebug.start_with_request=yes /app/test_script.php
```

**Note:** The xdebug-mcp CLI tools (`xdebug-trace`, `xdebug-profile`, etc.) require `-- php script.php` format and cannot be directly combined with `docker compose` commands. Instead, use `docker compose run` with appropriate Xdebug configuration flags as shown above.

### 3. Expected Output

**Trace test:**
```
✅ Trace complete: /tmp/trace.12345.xt
🎯 Context: Docker test - basic trace
📊 XXX lines generated
💡 claude "Analyze /tmp/trace.12345.xt"
```

**Profile test:**
```
📊 Profiling: /app/test_script.php
✅ Profile complete: /tmp/cachegrind.out.12345
📊 Size: X.XKB (XXX lines)
...
```

**Coverage test:**
```
✅ Coverage complete
📊 Analyzed X files
📈 XX.X% overall coverage
...
```

### 4. Cleanup

```bash
docker compose down

# Optional: Clean trace/profile files
rm /tmp/trace.*.xt
rm /tmp/cachegrind.out.*
```

## Test Scripts

### test_script.php
Basic PHP script that exercises common patterns:
- Recursive functions (fibonacci)
- Array processing
- Memory allocation

**Purpose:** Verify basic trace/profile functionality.

### test_symfony_like.php
Simulates a Symfony REST API application:
- Request/Response handling
- Routing
- Service layer
- Controller pattern

**Purpose:** Test realistic web application scenarios.

### test_coverage.php
Simple class with multiple methods:
- Calculator operations
- Prime number detection

**Purpose:** Verify code coverage analysis.

## Troubleshooting

### Container won't start

```bash
# Check logs
docker compose logs

# Verify Xdebug installation
docker compose exec php php -m | grep xdebug
```

### Trace files not found

```bash
# Verify /tmp mount
docker compose exec php ls -la /tmp

# Check Xdebug output directory
docker compose exec php php -i | grep xdebug.output_dir
```

### Permission denied on trace files

```bash
# Fix permissions
sudo chown $(whoami):$(whoami) /tmp/trace.*.xt
```

## Advanced Usage

### Test with environment variables

```bash
# Override Xdebug mode
XDEBUG_MODE=profile docker compose up -d
```

### Test with vendor filtering

```bash
# Include vendor packages in trace (if you add dependencies)
../../bin/xdebug-trace --include-vendor="*/*" -- \
  docker compose exec -T php php /app/test_script.php
```

### Test with JSON output

```bash
# Get machine-readable output
../../bin/xdebug-profile --json -- \
  docker compose exec -T php php /app/test_script.php | jq '.'
```

## Integration with CI/CD

This test environment can be used in GitHub Actions:

```yaml
- name: Test Docker Integration
  run: |
    cd tests/docker
    docker compose up -d
    ../../bin/xdebug-trace -- docker compose exec -T php php /app/test_script.php
    docker compose down
```

## Next Steps

After verifying the test environment works:

1. Apply the same pattern to your own Docker projects
2. Add `/tmp:/tmp` volume mount to your docker-compose.yml
3. Use host-based xdebug-mcp installation for simplicity
4. Refer to [DOCKER_INTEGRATION.md](../../docs/DOCKER_INTEGRATION.md) for detailed guide
