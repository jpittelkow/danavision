# Pattern: Writing Changelog Entries

## Problem

Auto-generated changelog entries from commit message subjects produce vague, unhelpful entries like:
```
- ADR API audit, frontend component refactoring, design review improvements, and documentation updates
```

Users need detailed, specific entries that describe what actually changed.

## Solution

Write changelog entries **manually before releasing**, based on the actual diff — not commit messages. The release script (`push.ps1`) will skip auto-generation when it detects a pre-written entry.

## When to Write

Before running `./scripts/push.ps1`, add a changelog entry to `CHANGELOG.md` for the version about to be released.

## Format

Follow [Keep a Changelog](https://keepachangelog.com/) with specific, user-facing descriptions:

```markdown
## [0.11.0] - 2026-03-09

### Added
- File manager with drag-and-drop upload and folder navigation
- Webhook delivery retry with exponential backoff
- User group permissions for API key management

### Changed
- Dashboard widgets now lazy-load for faster initial page render
- Email template editor uses Monaco instead of plain textarea

### Fixed
- Avatar upload failing for JPEG files over 2MB
- Notification bell count not updating after marking all as read
- Search results missing accent characters in non-English content
```

## Rules

1. **One bullet per distinct change** — never combine unrelated changes into one bullet
2. **Start with a verb** — Added/Changed/Fixed/Removed language
3. **Be specific** — name the feature, component, or behavior that changed
4. **User-facing language** — describe what users see, not internal refactoring details
5. **Skip trivial changes** — don't list dependency bumps, typo fixes, or pure refactors unless they affect behavior
6. **Use the correct category**:
   - **Added** — new features or capabilities
   - **Changed** — modifications to existing behavior
   - **Fixed** — bug fixes
   - **Removed** — removed features
   - **Security** — vulnerability fixes
   - **Deprecated** — features marked for future removal

## How to Generate Good Entries

1. Run `git diff <last-tag>..HEAD --stat` to see which files changed
2. Run `git log <last-tag>..HEAD --oneline` to see commit history
3. Group changes by user impact, not by file or commit
4. Write entries from the user's perspective

## Anti-Patterns

- `Update 15 files` — says nothing useful
- `Frontend and backend improvements` — too vague
- `Bug fixes and performance improvements` — the App Store special
- Listing every file that changed as a separate bullet
- Duplicating the commit message verbatim when it's already vague

## Integration with Release Script

`push.ps1` checks if `CHANGELOG.md` already contains `## [X.Y.Z]` for the target version. If found, it skips auto-generation and uses the manually-written entry. This means:

1. Calculate the next version (read `VERSION`, bump accordingly)
2. Write the changelog entry with that version number
3. Run `./scripts/push.ps1` — it will detect and preserve your entry

**Related:** [Recipe: Commit and Release](../recipes/commit-and-release.md), [Recipe: Work with the Changelog System](../recipes/add-changelog-entry.md)
