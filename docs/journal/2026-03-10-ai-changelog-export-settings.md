# AI-Readable Changelog Export — Configurable Settings

**Date**: 2026-03-10
**Type**: Feature Enhancement

## Summary

Added admin-configurable settings for the AI-readable changelog export feature and completed all housekeeping tasks (help content, search registration, journal, roadmap archival).

## What Was Already Built

The core AI export feature was implemented in a prior session:
- `ChangelogExportService` — generates structured markdown upgrade guides between two versions
- `ChangelogController` — `GET /api/changelog/versions` and `GET /api/changelog/export` endpoints
- Frontend `AIExportDialog` — version picker UI with blob download
- Tests — auth, validation, version ordering, and successful export

## What This Session Added

### Configuration Settings

Added three new settings under the `changelog` group in `backend/config/settings-schema.php`:

| Setting | Options | Default | Effect |
|---------|---------|---------|--------|
| `export_format` | `detailed`, `summary` | `detailed` | `summary` skips the version-by-version section, showing only consolidated changes |
| `export_detail_level` | `full`, `changes-only` | `full` | `changes-only` omits migration detection |
| `export_instruction_style` | `step-by-step`, `checklist`, `minimal` | `step-by-step` | Controls the AI agent instructions section format |

### Service Changes

- Injected `SettingService` into `ChangelogExportService`
- `generateExport()` reads all three settings and passes them to `buildMarkdown()`
- `buildMarkdown()` conditionally skips version-by-version section for `summary` format
- Migration detection skipped when `detail_level` is `changes-only`
- Extracted `buildInstructions()` method with three output styles
- Settings included in YAML frontmatter for downstream tool consumption

### Help Content

Updated the changelog help article in `frontend/lib/help/help-content.ts` with an "Export Settings (Admin)" section documenting the three configurable options.

### Housekeeping

- Search registration was already in place (both `search-pages.php` and `search-pages.ts`)
- Archived the roadmap item from Planned Features to Completed
- Created this journal entry

## Files Modified

- `backend/config/settings-schema.php` — Added `changelog` settings group
- `backend/app/Services/ChangelogExportService.php` — Injected SettingService, added format/detail/instruction logic
- `frontend/lib/help/help-content.ts` — Added export settings documentation
- `docs/roadmaps.md` — Moved AI changelog item to completed
- `docs/roadmap-archive.md` — Added archive entry

## Files Created

- `docs/journal/2026-03-10-ai-changelog-export-settings.md` — This file
