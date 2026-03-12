# AI Documentation Optimization - January 26, 2026

## Overview

Optimized the documentation system for AI-assisted development (vibe coding). The goal was to make it faster for AI assistants to find context, understand patterns, and avoid common mistakes.

## Implementation Approach

Focused on three areas:
1. **Surface critical info faster** - Context loading table at top of AI guide
2. **Add missing guidance** - Anti-patterns and testing recipes
3. **Remove redundancy** - Deleted deprecated/duplicate files

## Changes Made

### Files Deleted
- `docs/DOCUMENTATION.md` - Deprecated file with only a redirect notice
- `docs/rules.md` - Redundant with `.cursor/rules/` which auto-loads in Cursor

### Files Created
- `docs/ai/anti-patterns.md` - Common mistakes to avoid with code examples
- `docs/ai/recipes/add-tests.md` - Step-by-step guide for adding backend, frontend, and E2E tests
- `.cursor/rules/tool-selection.mdc` - Rule to check OpenAlternative.co when adding dependencies

### Files Modified

**`docs/ai/README.md`**
- Added "Quick Context Loading" table at the very top
- Added visual workflow diagram (ASCII)
- Added "Common Gotchas" section
- Added link to new anti-patterns guide
- Added testing recipe to recipe table
- Reorganized "Detailed Guides" as a table for quick scanning

**`docs/ai/patterns.md`**
- Added comprehensive "Error Handling Patterns" section
- Covers backend error responses, service exception handling, frontend error handling, and error boundary pattern
- Added HTTP status code reference table

**`docs/overview.md`**
- Reorganized documentation index into logical groups:
  - AI Development (Start Here)
  - Architecture & Features
  - Technical Reference
  - Other
- Moved Quick Reference higher (second item)

**`.cursor/rules/documentation-first.mdc`**
- Updated documentation structure list (removed rules.md)
- Added anti-patterns.md to AI guide consultation list
- Added new rule requiring AI documentation updates (patterns, context-loading, anti-patterns, recipes)

## Challenges Encountered

Deciding what to delete vs keep:
- `docs/rules.md` duplicated content from `.cursor/rules/` - deleted since Cursor auto-loads the rules
- `docs/dev/README.md` vs `docs/development.md` - kept both since dev/README.md is comprehensive human-developer guide while development.md is a brief overview

## Observations

- The existing documentation structure was well-thought-out but needed surfacing of critical info
- Context loading was the most valuable file but was buried 2 clicks deep
- Anti-patterns are just as important as patterns for AI development
- Testing was documented in ADR but lacked a practical recipe

## Trade-offs

- **Deleted rules.md**: Human developers who don't use Cursor won't see the coding standards. However, the patterns.md file covers most of this, and the project is designed for AI-first development in Cursor.

- **Comprehensive anti-patterns**: The anti-patterns file is long but comprehensive. Considered splitting by category but kept as one file for easier searching.

## Next Steps (Future Considerations)

- Add more recipes as common tasks are identified (e.g., "Add Frontend Page", "Debug Common Issues")
- Consider adding a troubleshooting guide for common error scenarios
- Add state management patterns to patterns.md if Zustand usage grows

## Testing Notes

- Verified all internal documentation links still work
- Confirmed `.cursor/rules/` files still load correctly
- Checked that new files are properly linked from README and overview
