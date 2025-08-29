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
  - `xdebug-debug.json` - Debug session output schema
  - `xdebug-profile.json` - Performance profiling schema
  - `xdebug-trace.json` - Execution trace schema
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
Enable AI debugging guide for your PHP project:
```bash
# Install via Composer (if not already done)
composer require koriym/xdebug-mcp

# Enable AI debugging guide for this project
echo "@vendor/koriym/xdebug-mcp/docs/debug_guideline_for_ai.md" >> CLAUDE.md

# Alternative: Global setup (affects ALL projects - use with caution)
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