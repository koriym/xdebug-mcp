# AI Debugging Templates

This directory contains template files for applying AI debugging principles to other PHP projects.

## 📁 Template Files

### `CLAUDE_DEBUG_PRINCIPLES.md`
AI debugging principles template for PHP projects. Promotes the transition from var_dump culture to Xdebug trace-based debugging.

### `.clauderc-template`
Project configuration template for Claude AI with debugging principles and project-specific settings.

### `setup-ai-debug.sh`
Automated setup script to add AI debugging principles to any PHP project.

## 🚀 Usage

### 1. New Project Setup
```bash
# Copy template to project root
cp templates/CLAUDE_DEBUG_PRINCIPLES.md /path/to/new-project/CLAUDE.md

# Copy configuration template
cp templates/.clauderc-template /path/to/new-project/.clauderc
```

### 2. Adding to Existing Project
```bash
# Append to existing CLAUDE.md
cat templates/CLAUDE_DEBUG_PRINCIPLES.md >> existing-project/CLAUDE.md

# Or create new file
cp templates/CLAUDE_DEBUG_PRINCIPLES.md existing-project/CLAUDE.md

# Automated setup (recommended)
templates/setup-ai-debug.sh /path/to/existing-project
```

### 3. Project-Specific Customization

After copying templates, customize the following:

- **Project-specific paths**: `./debug-script.php`, etc.
- **Custom trace commands**: Project-specific debugging scripts
- **Additional debugging tools**: Profiling, coverage configurations
- **Known issues**: Document project-specific problems and solutions

## 💡 Benefits

These templates enable any PHP project to have:

✅ **AI avoids adding var_dump statements**  
✅ **Xdebug trace analysis recommendations**  
✅ **Runtime data-based debugging**  
✅ **Professional debugging practices**  
✅ **Consistent AI behavior across sessions**

## 🔧 Customization Examples

### Adding Project-Specific Commands
```bash
# Add custom debug commands to CLAUDE.md
echo "## Project-Specific Debug Commands" >> CLAUDE.md
echo "./scripts/debug-trace.sh \$target_file" >> CLAUDE.md
```

### Customizing .clauderc
```yaml
# Edit .clauderc for your project
project_name: my_awesome_project
known_issues:
  custom_framework:
    problem: "Framework XYZ requires special trace handling"
    solution: "Use --framework-mode flag with trace commands"
```

### Creating Debug Scripts
```bash
# Create project-specific trace wrapper
mkdir -p scripts
cat > scripts/debug-trace.sh << 'EOF'
#!/bin/bash
# Custom trace script for this project
XDEBUG_TRIGGER=TRACE php -dxdebug.mode=trace "$1"
EOF
chmod +x scripts/debug-trace.sh
```

## 📋 Template Contents

### CLAUDE_DEBUG_PRINCIPLES.md
- Complete AI debugging guidelines
- var_dump prohibition and Xdebug trace promotion
- Specific command examples
- Trace file analysis guide
- Response examples (correct vs incorrect)

### .clauderc-template
- Project configuration structure
- Debugging rules and workflows
- Known issues documentation
- AI behavior preferences

### setup-ai-debug.sh
- Automated installation
- Backup existing files
- Create basic debug infrastructure
- Intelligent project detection

## 🎯 Implementation Flow

1. **Choose Setup Method**: Manual copy or automated script
2. **Run Setup**: Apply templates to your project
3. **Customize**: Adapt templates to project needs
4. **Test**: Verify AI follows debugging principles
5. **Iterate**: Update .clauderc with new discoveries

This enables the AI debugging revolution established in xdebug-mcp to be deployed across all your PHP projects.