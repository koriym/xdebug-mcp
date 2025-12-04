# Docker Integration Tests for xdebug-mcp

This directory contains Docker-based test environments for verifying xdebug-mcp's container integration.

## Quick Start

```bash
# Build the test container
docker compose build

# Verify container works
docker compose run --rm php php -v
docker compose run --rm php php -m | grep xdebug

# Run test script directly
docker compose run --rm php php /app/test_script.php
```

## Testing with xdebug-mcp Tools

### From the project root directory:

```bash
# Test trace functionality
./bin/xdebug-trace --context="Docker trace test" -- \
  docker compose -f tests/docker/docker-compose.yml run --rm php php /app/test_script.php

# Test profile functionality
./bin/xdebug-profile --context="Docker profile test" -- \
  docker compose -f tests/docker/docker-compose.yml run --rm php php /app/test_performance.php

# Test coverage functionality
./bin/xdebug-coverage -- \
  docker compose -f tests/docker/docker-compose.yml run --rm php php /app/test_coverage.php
```

### Using docker compose exec (for running containers):

```bash
# Start container in background
docker compose -f tests/docker/docker-compose.yml up -d

# Run trace
./bin/xdebug-trace -- \
  docker compose -f tests/docker/docker-compose.yml exec -T php php /app/test_script.php

# Stop container
docker compose -f tests/docker/docker-compose.yml down
```

## Test Scripts

| Script | Purpose |
|--------|---------|
| `test_script.php` | Basic function calls, recursion, array operations |
| `test_coverage.php` | Code coverage testing with branches and conditionals |
| `test_performance.php` | Performance profiling with intentionally slow algorithms |

## How XdebugRunner Works

The `XdebugRunner` class handles Docker integration by:

1. **Detecting container commands**: Recognizes `docker`, `podman`, `kubectl`
2. **Finding PHP position**: Locates the `php` command within the Docker command
3. **Injecting Xdebug args**: Inserts `-dxdebug.*` options after `php`

### Example transformation:

```
Input:  docker compose run --rm php php /app/test.php
Output: docker compose run --rm php php -dxdebug.mode=trace -dxdebug.start_with_request=yes ... /app/test.php
```

## Troubleshooting

### Trace/Profile files not found

Ensure `/tmp` is mounted in the container:
```yaml
volumes:
  - /tmp:/tmp
```

### Xdebug not working

Check Xdebug is installed:
```bash
docker compose run --rm php php -m | grep xdebug
```

### Permission issues

The container runs as root by default. If you need specific user permissions:
```yaml
user: "${UID}:${GID}"
```

## Architecture

```
Host Machine
├── xdebug-mcp tools (bin/xdebug-*)
├── XdebugRunner class (src/XdebugRunner.php)
│   └── Detects Docker and injects Xdebug args
└── /tmp (shared volume)
    ├── trace.*.xt
    └── cachegrind.out.*

Docker Container
├── PHP 8.4 + Xdebug
├── /app (test scripts)
└── /tmp (mounted from host)
```
