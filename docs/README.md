# PHP Xdebug MCP Server - Documentation

## 📁 Files in this directory

This folder contains documentation, guides, and presentation materials for the PHP Xdebug MCP Server.

## 📄 Documentation Files

### Debugging Guides
- **`debug_guideline_for_ai.md`** - AI assistant debugging methodology and Forward Trace™ concepts
- **`debug_guideline_for_humans.md`** - Human developer workflow and practical usage patterns
- **`TROUBLESHOOTING.md`** - Common issues and comprehensive solutions guide

### Architecture & Decisions
- **`ADR-001-forward-trace-only-approach.md`** - Architecture Decision Record for Forward Trace-only approach

### Technical Specifications
- **`schemas/`** - JSON schema definitions for tool outputs
  - `xstep.json` - Debug session output schema
  - `xprofile.json` - Performance profiling schema
  - `xtrace.json` - Execution trace schema
  - `alps.json` - ALPS API documentation

### Presentation Materials
- **`slide/index.html`** - Forward Trace Revolution presentation (reveal.js)

### Assets
- **`images/`** - Presentation and documentation images
  - `title.jpg` - Main presentation title image
  - `logo.jpeg` - Project logo
  - `interactive-debugger.png` - Interactive debugger screenshot
- **`css/`** - Stylesheets for documentation
  - `semantic-portal.css` - Semantic styling for web documentation

## 📋 File Usage

### For AI Setup (After Composer Installation)
Choose the integration style that matches your assistant:
```bash
# Install via Composer (if not already done)
composer require koriym/xdebug-mcp

# Claude Code: include the project guide
echo "@vendor/koriym/xdebug-mcp/docs/debug_guideline_for_ai.md" >> CLAUDE.md

# Codex: install the bundled local skill
# (run from the consumer project root that contains vendor/)
# mkdir -p ~/.codex/skills
# ln -s "$(pwd)/vendor/koriym/xdebug-mcp/skills/xdebug" ~/.codex/skills/xdebug

# Generic MCP client: point your MCP config at the server entrypoint
# php vendor/bin/xdebug-mcp

# Alternative Claude global setup (affects ALL projects - use with caution)
# mkdir -p ~/.claude
# cp vendor/koriym/xdebug-mcp/docs/debug_guideline_for_ai.md ~/.claude/
# echo "@debug_guideline_for_ai.md" >> ~/.claude/CLAUDE.md
```

### For Presentations
View the Forward Trace Revolution presentation:
```bash
cd slide && python3 -m http.server 8000
open http://localhost:8000
```

### For Development Reference
```bash
# View human developer guide
open vendor/koriym/xdebug-mcp/docs/debug_guideline_for_humans.md

# Or use your preferred editor
code vendor/koriym/xdebug-mcp/docs/debug_guideline_for_humans.md
```
