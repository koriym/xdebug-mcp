#!/bin/bash

# AI Debug Setup Script
# Automatically add AI debugging principles to any PHP project

set -e

PROJECT_DIR="${1:-.}"
TEMPLATE_DIR="$(dirname "$0")"

echo "🔧 Setting up AI debugging principles..."
echo "📁 Target project: $(realpath "$PROJECT_DIR")"

# Check if it's a PHP project
if [ ! -f "$PROJECT_DIR/composer.json" ] && [ ! -f "$PROJECT_DIR"/*.php ]; then
    echo "⚠️  Warning: This doesn't appear to be a PHP project"
    echo "   No composer.json or .php files found"
    echo ""
    read -p "Continue anyway? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Cancelled"
        exit 1
    fi
fi

cd "$PROJECT_DIR"

# Backup existing CLAUDE.md if it exists
if [ -f "CLAUDE.md" ]; then
    echo "📋 Backing up existing CLAUDE.md to CLAUDE.md.backup"
    cp CLAUDE.md CLAUDE.md.backup
    
    echo "✅ Adding AI debugging principles to existing CLAUDE.md"
    echo "" >> CLAUDE.md
    echo "# === AI Debugging Principles Added by xdebug-mcp ===" >> CLAUDE.md
    cat "$TEMPLATE_DIR/CLAUDE_DEBUG_PRINCIPLES.md" | grep -A 1000 "^# AI Debugging Principles" >> CLAUDE.md
else
    echo "✅ Creating new CLAUDE.md with AI debugging principles"
    cp "$TEMPLATE_DIR/CLAUDE_DEBUG_PRINCIPLES.md" CLAUDE.md
fi

# Create basic Xdebug trace command if no custom debug setup exists
if [ ! -f "debug.sh" ] && [ ! -f "bin/debug" ] && [ ! -f "scripts/debug.sh" ]; then
    echo "🔍 Creating basic debug trace command..."
    
    mkdir -p scripts
    cat << 'EOF' > scripts/debug-trace.sh
#!/bin/bash
# Basic Xdebug trace for this project
TARGET_FILE="${1:-index.php}"

if [ ! -f "$TARGET_FILE" ]; then
    echo "❌ File not found: $TARGET_FILE"
    echo "Usage: $0 <php_file>"
    exit 1
fi

echo "🔍 Tracing: $TARGET_FILE"
XDEBUG_TRIGGER=TRACE php -dzend_extension=xdebug \
    -dxdebug.mode=trace \
    -dxdebug.start_with_request=trigger \
    -dxdebug.trigger_value=TRACE \
    -dxdebug.trace_format=1 \
    -dxdebug.use_compression=0 \
    -dxdebug.output_dir=/tmp \
    "$TARGET_FILE"

# Find and display trace file
LATEST_TRACE=$(ls -t /tmp/*trace*.xt 2>/dev/null | head -1)
if [ -f "$LATEST_TRACE" ]; then
    echo "✅ Trace complete: $LATEST_TRACE"
    echo "📊 $(wc -l < "$LATEST_TRACE") lines generated"
else
    echo "❌ No trace file generated. Check Xdebug configuration."
fi
EOF
    
    chmod +x scripts/debug-trace.sh
    echo "✅ Created: scripts/debug-trace.sh"
fi

echo ""
echo "🎯 Setup complete!"
echo ""
echo "📋 What was added:"
echo "  • CLAUDE.md with AI debugging principles"
if [ -f "CLAUDE.md.backup" ]; then
    echo "  • Backup of original CLAUDE.md"
fi
if [ -f "scripts/debug-trace.sh" ]; then
    echo "  • Basic trace command: scripts/debug-trace.sh"
fi
echo ""
echo "💡 Usage example:"
echo "  scripts/debug-trace.sh your_file.php"
echo ""
echo "🔍 AI will now use trace-based debugging instead of var_dump!"
echo "   See CLAUDE.md for full guidelines."