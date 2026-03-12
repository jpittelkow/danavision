# Documentation Review Roadmap

Comprehensive review of all project documentation for accuracy, completeness, and consistency. Audit ADRs, recipes, patterns, and guides against the current codebase to ensure documentation reflects reality.

**Priority**: MEDIUM
**Status**: NOT STARTED
**Last Updated**: 2026-03-09

---

## Overview

As the project evolves, documentation can drift from the actual codebase. This review covers all doc categories — ADRs, recipes, patterns, guides, and inline references — to ensure they are accurate, complete, and consistently formatted.

---

## Phase 1: Audit & Inventory

Identify what exists, what's missing, and what's stale.

- [ ] **Inventory all ADRs** — List every ADR in `docs/adr/`, verify each references current file paths and code patterns
- [ ] **Inventory all recipes** — List every recipe in `docs/ai/recipes/`, verify steps still work against current code
- [ ] **Inventory all patterns** — List every pattern in `docs/ai/patterns/`, verify examples match current conventions
- [ ] **Check CLAUDE.md task-type table** — Verify every entry in the task-type lookup table points to files that exist and are current
- [ ] **Check broken links** — Scan all markdown files for broken internal links (missing files, renamed paths)
- [ ] **Identify undocumented features** — Cross-reference controllers, services, and routes against docs to find features without documentation

## Phase 2: Accuracy & Freshness

Fix stale content and update outdated references.

- [ ] **Update stale ADRs** — Fix ADRs that reference old file paths, removed features, or superseded patterns
- [ ] **Update stale recipes** — Fix recipes with outdated steps, missing prerequisites, or changed file locations
- [ ] **Update settings schema docs** — Verify `settings-schema.php` and `user-settings-schema.php` docs match current schema entries
- [ ] **Update search registration docs** — Ensure search dual-registration guidance covers all current searchable pages
- [ ] **Verify code examples** — Spot-check code snippets in docs against actual codebase for correctness
- [ ] **Review CHANGELOG accuracy** — Verify recent CHANGELOG entries match actual shipped changes

## Phase 3: Completeness

Fill documentation gaps.

- [ ] **Add missing ADRs** — Write ADRs for any architectural decisions made without formal documentation
- [ ] **Add missing recipes** — Write recipes for common tasks that developers perform but lack step-by-step guides
- [ ] **Add missing patterns** — Document established code patterns that are used consistently but not formally written up
- [ ] **Document environment variables** — Ensure all `.env` variables are documented with descriptions and defaults
- [ ] **Document API endpoints** — Verify all API routes have corresponding documentation or are covered by existing guides

## Phase 4: Consistency & Formatting

Standardize style across all documentation.

- [ ] **Standardize frontmatter** — Ensure all roadmaps have Priority, Status, Last Updated fields
- [ ] **Standardize heading structure** — Use consistent heading levels and section ordering across similar doc types
- [ ] **Standardize cross-references** — Use consistent link format (relative paths, anchors) across all docs
- [ ] **Review quick-reference.md** — Ensure the quick reference is up to date with current commands and conventions
- [ ] **Review context-loading.md** — Ensure context loading file lists match current project structure

---

## Success Criteria

- Zero broken internal links across all markdown files
- Every controller/service has at least one doc reference (ADR, recipe, or pattern)
- All code examples in docs compile/run against current codebase
- Consistent formatting across all doc categories
