#!/bin/sh

# POSIX shell wrapper to maintain compatibility with existing test-all.sh usage
# Delegates to the refactored test-working-tools.php

# Get script directory
script_dir="$(dirname "$0")"

# Execute PHP script with all arguments forwarded safely
exec php -f "$script_dir/test-working-tools.php" -- "$@"