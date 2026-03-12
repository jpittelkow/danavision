# Recipe: Work with the Changelog System

The changelog system parses `CHANGELOG.md` (Keep a Changelog format) and serves it via API.

## Key Files

| File | Purpose |
|------|---------|
| `CHANGELOG.md` | Source of truth — Keep a Changelog format |
| `backend/app/Services/ChangelogService.php` | Parses markdown into structured entries |
| `backend/app/Http/Controllers/Api/ChangelogController.php` | Paginated API endpoint |

## CHANGELOG.md Format

```markdown
# Changelog

## [1.2.0] - 2026-03-04

### Added
- New feature description

### Fixed
- Bug fix description

## [1.1.0] - 2026-02-28

### Changed
- Change description
```

## API

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/changelog?page=1&per_page=10` | Paginated changelog entries |

### Response Format

```json
{
  "data": [
    {
      "version": "1.2.0",
      "date": "2026-03-04",
      "categories": {
        "added": ["New feature description"],
        "fixed": ["Bug fix description"]
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25
  }
}
```

## Adding a Changelog Entry

Simply edit `CHANGELOG.md` at the project root following Keep a Changelog format. The API parses it on each request — no migration or cache needed.

Standard categories: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.

**Writing quality entries:** See [Pattern: Changelog Entries](../patterns/changelog-entries.md) for rules on writing detailed, user-facing changelog entries. The release script (`push.ps1`) will skip auto-generation when a manual entry exists.

**Related:** [Recipe: Commit and Release](commit-and-release.md)

## Implementation Journal

- [Changelog and Theme Fixes (2026-02-14)](../../journal/2026-02-14-changelog-and-theme-fixes.md)
- [Changelog Docker Fix and Branding Null Bug (2026-02-15)](../../journal/2026-02-15-changelog-docker-fix-and-branding-null-bug.md)
