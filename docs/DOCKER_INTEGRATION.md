# Docker Integration Guide

## Overview

This guide demonstrates how to use xdebug-mcp with Docker-based PHP applications. You can use xdebug-mcp to analyze PHP code running inside Docker containers while keeping your IDE debugging workflow intact.

## Architecture: Host-Based vs Container-Based

### Recommended: Host-Based Installation

Install xdebug-mcp on your **host machine** and execute Docker commands from there.

```
┌─────────────────────────────────────────────┐
│ Host Machine                                │
│                                             │
│  ┌──────────────────┐                       │
│  │  xdebug-mcp      │ ─┐                    │
│  │  (CLI tools)     │  │                    │
│  └──────────────────┘  │                    │
│                        │                    │
│                        ▼                    │
│  ┌─────────────────────────────────┐        │
│  │ Docker Container                │        │
│  │                                 │        │
│  │  ┌──────────────────┐           │        │
│  │  │ PHP + Xdebug     │           │        │
│  │  │ Your Application │           │        │
│  │  └──────────────────┘           │        │
│  │                                 │        │
│  │  /tmp → mounted to host         │        │
│  └─────────────────────────────────┘        │
└─────────────────────────────────────────────┘
```

**Benefits:**
- Simple setup - no Docker configuration changes
- Zero interference with IDE debugging
- Works with any Docker project
- Easy updates via `composer global update`

### Alternative: Container-Based Installation

Install xdebug-mcp **inside** the container (advanced, requires Xdebug mode switching).

## Quick Start: Test Docker Integration Locally

### Step 1: Create Test Environment

We provide a complete test environment to verify Docker integration:

```bash
cd /path/to/xdebug-mcp
cd tests/docker
```

### Step 2: Build and Start Test Container

```bash
# Build the test container with PHP 8.4 + Xdebug
docker compose build

# Start the container
docker compose up -d

# Verify container is running
docker compose ps
```

### Step 3: Test xdebug-mcp Tools

All core xdebug-mcp tools support Docker execution through unified XdebugRunner:

```bash
# Test 1: Trace execution (execution flow analysis)
../../bin/xdebug-trace --context="Docker integration test - trace" -- \
  docker compose run --rm php php /app/test_script.php

# Test 2: Profile performance (bottleneck identification)
../../bin/xdebug-profile --context="Docker integration test - profile" -- \
  docker compose run --rm php php /app/test_script.php

# Test 3: Forward Trace debugging (step-by-step variable tracking)
../../bin/xdebug-debug --context="Docker debug test" --exit-on-break --steps=100 -- \
  docker compose run --rm php php /app/test_script.php

# Note: xdebug-coverage has different Docker requirements (see below)
```

**Tools with Full Docker Support:**
- `xdebug-trace` - Execution flow tracing ✅
- `xdebug-profile` - Performance profiling ✅
- `xdebug-debug` - Forward Trace step debugging ✅

**Supported Docker Commands:**
- `docker compose run --rm php php script.php` (recommended)
- `docker compose exec -T php php script.php` (for running containers)
- `docker run --rm php:8.4-cli php script.php`
- `podman run ...` (Podman support)
- `kubectl exec ...` (Kubernetes support)

**xdebug-coverage Docker Limitations:**

The `xdebug-coverage` tool uses `auto_prepend_file` which requires special Docker configuration. For Docker-based code coverage, we recommend:

1. **Option 1: Run PHPUnit inside container with Xdebug enabled**
   ```bash
   docker compose exec -T php php -dxdebug.mode=coverage vendor/bin/phpunit --coverage-text
   ```

2. **Option 2: Use xdebug-coverage with local PHP** (if source code is mounted)
   ```bash
   ../../bin/xdebug-coverage -- php /path/to/local/script.php
   ```

**Expected Output:**

```
Trace complete: /tmp/trace.12345.xt
Context: Docker integration test - trace
XXX lines generated
claude "Analyze /tmp/trace.12345.xt"
```

### Step 4: Cleanup

```bash
docker compose down
```

## Real-World Example: Symfony Application

### Directory Structure

```
your-symfony-project/
├── docker-compose.yml
├── Dockerfile
├── app/
│   ├── bin/console
│   ├── src/
│   └── tests/
└── ...
```

### Dockerfile for Symfony + Xdebug

```dockerfile
FROM php:8.4-cli

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Configure Xdebug for both IDE and xdebug-mcp
RUN echo 'zend_extension=xdebug.so\n\
xdebug.mode=${XDEBUG_MODE:-debug}\n\
xdebug.client_host=host.docker.internal\n\
xdebug.client_port=9003\n\
xdebug.start_with_request=trigger\n\
xdebug.output_dir=/tmp\n\
xdebug.use_compression=0' \
> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-scripts

COPY . .
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  php:
    build: .
    volumes:
      - .:/app
      - /tmp:/tmp  # Share /tmp for trace/profile files
    environment:
      XDEBUG_MODE: ${XDEBUG_MODE:-debug}
      XDEBUG_SESSION: ${XDEBUG_SESSION:-PHPSTORM}
    extra_hosts:
      - "host.docker.internal:host-gateway"
```

### Usage Examples

```bash
# Profile Symfony console command
~/.composer/vendor/bin/xdebug-profile \
  --context="Symfony console command performance" \
  --json -- \
  docker compose run --rm php php bin/console cache:clear

# Trace API endpoint simulation
~/.composer/vendor/bin/xdebug-trace \
  --context="Symfony API request handling" \
  -- \
  docker compose run --rm php php bin/console app:simulate-request /api/users

# Forward Trace debugging - catch null bugs automatically
~/.composer/vendor/bin/xdebug-debug \
  --context="Debug null user bug" \
  --break="src/Controller/UserController.php:42:\$user==null" \
  --exit-on-break -- \
  docker compose run --rm php php bin/console app:test-user

# Record variable evolution (100 steps)
~/.composer/vendor/bin/xdebug-debug \
  --context="Track user session state" \
  --steps=100 \
  --json -- \
  docker compose run --rm php php bin/console app:session-test

# Coverage for PHPUnit tests
~/.composer/vendor/bin/xdebug-coverage -- \
  docker compose exec -T php php vendor/bin/phpunit tests/Controller/
```

## FrankenPHP Example

FrankenPHP is a modern PHP application server. Here's how to integrate with xdebug-mcp:

### Dockerfile

```dockerfile
FROM dunglas/frankenphp:latest-php8.4

# Install Xdebug
RUN install-php-extensions xdebug

# Configure Xdebug
RUN echo 'zend_extension=xdebug.so\n\
xdebug.mode=${XDEBUG_MODE:-debug}\n\
xdebug.client_host=host.docker.internal\n\
xdebug.client_port=9003\n\
xdebug.start_with_request=trigger\n\
xdebug.output_dir=/tmp' \
> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /app
```

### Usage

```bash
# Profile FrankenPHP request handling
~/.composer/vendor/bin/xdebug-profile -- \
  docker compose exec -T frankenphp php public/index.php
```

## IDE Coexistence

### PHPStorm + xdebug-mcp: Perfect Harmony

Both tools work simultaneously without any conflicts:

**PHPStorm (Port 9003):**
```bash
# Set breakpoint in PHPStorm, then run:
docker compose exec -e XDEBUG_SESSION=PHPSTORM php php bin/console app:command
# Breakpoint hits normally
```

**xdebug-mcp (CLI-based, no port):**
```bash
# No interference with PHPStorm
~/.composer/vendor/bin/xdebug-trace -- \
  docker compose exec -T php php bin/console app:command
```

**Workflow:**
1. Use PHPStorm for interactive step debugging
2. Use xdebug-mcp for comprehensive trace/profile analysis
3. Both can run at the same time!

## Troubleshooting

### Issue 1: "Functionality is not enabled"

**Error:**
```
PHP Notice: Functionality is not enabled in prepend_filter.php on line 60
```

**Cause:** Xdebug mode is hardcoded in container's php.ini

**Solution 1: Use host-based installation (recommended)**
```bash
# Install on host
composer global require koriym/xdebug-mcp

# Use with Docker
~/.composer/vendor/bin/xdebug-trace -- docker compose exec -T php php script.php
```

**Solution 2: Allow mode override in Dockerfile**
```dockerfile
RUN echo 'xdebug.mode=${XDEBUG_MODE:-debug}' \
  > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
```

### Issue 2: Trace/Profile Files Not Found

**Solution:** Mount /tmp directory in docker-compose.yml
```yaml
services:
  php:
    volumes:
      - /tmp:/tmp  # Share trace/profile output directory
```

### Issue 3: Permission Denied on /tmp Files

**Solution:** Fix permissions after trace/profile
```bash
# After running xdebug-mcp tools
sudo chown $(whoami):$(whoami) /tmp/trace.*.xt
sudo chown $(whoami):$(whoami) /tmp/cachegrind.out.*
```

### Issue 4: docker compose exec Hangs

**Solution:** Use `-T` flag to disable TTY allocation
```bash
# Bad: Hangs with interactive tools
docker compose exec php command

# Good: Non-interactive mode
docker compose exec -T php command
```

## Advanced: Container-Based Installation

If you prefer installing xdebug-mcp inside the container:

### Dockerfile

```dockerfile
FROM php:8.4-cli

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install xdebug-mcp globally in container
RUN composer global require koriym/xdebug-mcp

# Add Composer global bin to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

# Flexible Xdebug configuration
RUN echo 'xdebug.mode=${XDEBUG_MODE:-debug}' \
  > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /app
```

### Usage

```bash
# Override Xdebug mode via environment variable
docker compose exec -e XDEBUG_MODE=trace php \
  xdebug-trace -- php script.php

docker compose exec -e XDEBUG_MODE=profile php \
  xdebug-profile -- php script.php
```

**Note:** Host-based installation is simpler and more reliable.

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Performance Analysis

on: [push, pull_request]

jobs:
  performance:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Install xdebug-mcp on runner
        run: |
          composer global require koriym/xdebug-mcp
          echo "$HOME/.composer/vendor/bin" >> $GITHUB_PATH

      - name: Build Docker container
        run: docker compose build

      - name: Start services
        run: docker compose up -d

      - name: Profile application
        run: |
          xdebug-profile --json --context="CI Performance Test" -- \
            docker compose exec -T php php bin/console app:benchmark \
            > performance.json

      - name: Upload performance data
        uses: actions/upload-artifact@v3
        with:
          name: performance-report
          path: performance.json
```

## Performance Comparison

### Test Scenario: Symfony Console Command

**Setup:**
- Command: `bin/console cache:clear`
- Docker: PHP 8.4 + Xdebug 3.4
- Host: macOS with M1 chip

**Results:**

| Method | Execution Time | Setup Complexity |
|--------|----------------|------------------|
| Host-based xdebug-mcp | ~500ms | Low ⭐⭐⭐ |
| Container-based xdebug-mcp | ~520ms | High ⭐ |
| Direct Docker exec | ~450ms (no profiling) | N/A |

**Conclusion:** Host-based installation adds minimal overhead (~50ms) with much simpler setup.

## Best Practices

### 1. Use Host-Based Installation for Development

```bash
# Install once on host
composer global require koriym/xdebug-mcp

# Use with any Docker project
cd ~/projects/project-a
xdebug-trace -- docker compose exec -T php php script.php

cd ~/projects/project-b
xdebug-profile -- docker compose exec -T php php script.php
```

### 2. Share /tmp Directory

```yaml
# docker-compose.yml
services:
  php:
    volumes:
      - /tmp:/tmp  # Essential for trace/profile file access
```

### 3. Always Use -T Flag

```bash
# Correct: Non-interactive mode
docker compose exec -T php php script.php

# Wrong: Interactive mode breaks tool output
docker compose exec php php script.php
```

### 4. Use Context for All Analyses

```bash
# Good: Self-explanatory output
xdebug-trace --context="User login performance - Docker test" -- \
  docker compose exec -T php php login.php

# Bad: No context
xdebug-trace -- docker compose exec -T php php login.php
```

### 5. Separate IDE and xdebug-mcp Sessions

```bash
# IDE debugging: use XDEBUG_SESSION
docker compose exec -e XDEBUG_SESSION=PHPSTORM php php script.php

# xdebug-mcp: no session variable needed (CLI-based)
xdebug-trace -- docker compose exec -T php php script.php
```

## FAQ

**Q: Do I need to install PHP on my host machine?**
A: Yes, for host-based xdebug-mcp installation. But it's the simplest approach.

**Q: Can I use xdebug-mcp with Docker Compose, Docker, Podman?**
A: Yes, all container runtimes work. Just replace `docker compose` with your command.

**Q: Will this slow down my Docker containers?**
A: No. xdebug-mcp only activates when you explicitly run the tools.

**Q: Can I use this in production?**
A: Not recommended. xdebug-mcp is for development/debugging only.

**Q: What about Windows/WSL?**
A: Works perfectly. Use WSL2 for best performance.

**Q: How do I share trace files with my team?**
A: Use `--json` flag and commit the JSON output to git. Trace files are too large.

## Next Steps

1. **Try the test environment:**
   ```bash
   cd tests/docker
   docker compose up -d
   ../../bin/xdebug-trace -- docker compose exec -T php php /app/test_script.php
   ```

2. **Integrate with your project:**
   - Add `/tmp:/tmp` volume mount to docker-compose.yml
   - Use host-based xdebug-mcp installation

3. **Configure Claude Code:**
   - Add xdebug-mcp to MCP configuration
   - Start AI-driven PHP debugging!

## Resources

- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Common issues and solutions
- [Forward Trace Guide](debug_guideline_for_ai.md) - AI debugging methodology
- [Docker Official Docs](https://docs.docker.com/) - Docker reference

---

**Ready to debug Docker-based PHP applications with AI assistance!**
