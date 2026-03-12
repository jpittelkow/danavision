# Documentation Guide

How to write and maintain Sourdough documentation.

## Principles

1. **Small files** -- Each file covers one concept. Target 50-150 lines for patterns/anti-patterns, 100-350 for recipes.
2. **Single source of truth** -- Every concept lives in one place. Other files link to it, never duplicate it.
3. **Link, don't duplicate** -- If you need to reference content from another file, link to it. Never copy paragraphs between files.
4. **Key files section** -- Every pattern and recipe must list the implementation files it references.
5. **Related section** -- Every pattern, recipe, and ADR should link to related docs (other patterns, recipes, ADRs).
6. **Index files** -- Directories use `README.md` as a table index (Pattern, File, When to Use).

## File Naming

| Type | Location | Format | Example |
|------|----------|--------|---------|
| Pattern | `docs/ai/patterns/` | `kebab-case.md` | `setting-service.md` |
| Anti-pattern | `docs/ai/anti-patterns/` | `kebab-case.md` | `backend.md` |
| Recipe | `docs/ai/recipes/` | `verb-noun.md` | `add-api-endpoint.md` |
| ADR | `docs/adr/` | `NNN-kebab-case.md` | `024-security-hardening.md` |
| Journal | `docs/journal/` | `YYYY-MM-DD-brief-description.md` | `2026-02-05-docker-audit.md` |
| Roadmap | `docs/plans/` | `kebab-case-roadmap.md` | `pwa-roadmap.md` |

## Templates

### Pattern File

```markdown
# Pattern Name

Brief 1-2 sentence description of when to use this pattern.

## Usage

\```php  (or tsx)
// Code example showing the pattern
\```

- Bullet points explaining key details
- When to use vs. when not to use

**Key files:** `path/to/file.php`, `path/to/other.ts`

**Related:** [Recipe: Add X](../recipes/add-x.md), [Anti-pattern: Don't do Y](../anti-patterns/backend.md#dont-y)
```

### Anti-Pattern Entry

```markdown
### Don't: Brief Description

\```php  (or tsx)
// BAD - why this is wrong
bad code example

// GOOD - the correct approach
good code example
\```

Brief explanation of why the bad approach causes problems.
```

### Recipe

```markdown
# Recipe: Task Name

Step-by-step guide to [what this recipe accomplishes].

## Files to Create/Modify

| File | Action | Purpose |
|------|--------|---------|
| `path/to/file` | Create/Modify | What it does |

## Step 1: First Step
## Step 2: Second Step
...

## Checklist

- [ ] Verification item 1
- [ ] Verification item 2
```

### Journal Entry

```markdown
# [Change Name] - [Date]

## Overview
Brief summary of what was done and why.

## Implementation Approach
Key decisions and architecture choices.

## Challenges Encountered
Problems faced and solutions.

## Observations
Insights, quality notes, patterns worth noting.

## Trade-offs
Decisions and their implications.

## Next Steps (Future Considerations)
Potential future work.

## Testing Notes
Key areas to verify.
```

## What to Create/Update (Decision Tree)

| You did this... | Create/Update these |
|-----------------|---------------------|
| Added a new feature | `features.md`, recipe if repeatable, pattern if new code pattern, ADR if architectural |
| Fixed a bug | Journal if non-obvious solution |
| Added an API endpoint | `api-reference.md`, recipe if new pattern |
| Changed architecture | ADR in `docs/adr/`, update `architecture.md` with key files |
| Added a code pattern | New file in `docs/ai/patterns/`, update `patterns/README.md` index |
| Found a common mistake | New entry in relevant `docs/ai/anti-patterns/*.md` file |
| Completed roadmap work | Update `docs/roadmaps.md` (move to Completed, add date) |
| Significant refactor | Journal entry in `docs/journal/` |
| Changed Docker setup | Update `docs/docker.md` |
| Changed config/settings | Update `docs/development.md` |

## Cross-Referencing Rules

| This file type... | Should link to... |
|--------------------|-------------------|
| Pattern | Related recipes, anti-patterns, ADRs |
| Recipe | Patterns it follows, ADRs it implements |
| ADR | Key implementation files, related patterns |
| Context-loading.md | Specific pattern files (not the whole directory) |
| README.md (AI guide) | Pattern index, anti-pattern index, recipe table |
| features.md | Recipes, ADRs, patterns by section anchor |
