# Changelog Page & Theme Adherence Fixes

**Date**: 2026-02-14  
**Type**: Feature + Bug Fix

## Summary

Implemented the Changelog page (Configuration > Changelog) and fixed dark mode theme adherence issues across multiple configuration pages.

## Changelog Page

### Backend
- Created `CHANGELOG.md` in project root using Keep a Changelog format, seeded with retroactive entries from v0.1.16 through v0.1.26
- Created `ChangelogService` to parse CHANGELOG.md into structured entries with pagination
- Created `ChangelogController` with `GET /api/changelog` endpoint (authenticated, paginated)
- Added route to `backend/routes/api.php`

### Frontend
- Created changelog page at `frontend/app/(dashboard)/configuration/changelog/page.tsx`
- Version entries displayed as collapsible cards with version badge, date, and change count
- Categories color-coded: Added (green), Changed (blue), Fixed (amber), Removed (red), Security (purple)
- Latest version expanded by default; older versions collapsed
- Loading skeleton, error state with retry, and empty state
- Pagination controls for large changelogs

### Navigation
- Added "Changelog" to General group in Configuration navigation (`layout.tsx`)
- Made sidebar version footer (`SidebarVersionFooter`) a clickable link to `/configuration/changelog`
- Registered in both `search-pages.php` and `search-pages.ts` for Cmd+K search

## Theme Adherence Fixes

Fixed hardcoded color classes missing dark mode variants:

| File | Fix |
|------|-----|
| `configuration/ai/page.tsx` | 6x `text-green-500` → `text-green-600 dark:text-green-400` on CheckCircle icons |
| `configuration/log-retention/page.tsx` | 1x `text-green-600` → added `dark:text-green-400` |
| `configuration/jobs/page.tsx` | 2x `text-green-500`/`text-green-600` → `text-green-600 dark:text-green-400` |
| `configuration/logs/page.tsx` | Log level colors: added light/dark paired classes matching audit page pattern |
| `storage/file-browser.tsx` | 1x `text-blue-500` → `text-blue-600 dark:text-blue-400` |
| `(auth)/login/page.tsx` | 1x `border-gray-300` → `border-border` (theme-aware token) |
| `user/security/page.tsx` | 1x `text-green-500` → `text-green-600 dark:text-green-400` |

### Toggle Consistency

Investigation found no functional toggle bugs:
- All `setValue` + `onCheckedChange` calls include `{ shouldDirty: true }`
- The Switch component uses theme-aware CSS variables
- Both `SettingsSwitchRow` and raw `Switch` patterns are appropriate for their contexts

## Files Created
- `CHANGELOG.md`
- `backend/app/Services/ChangelogService.php`
- `backend/app/Http/Controllers/Api/ChangelogController.php`
- `frontend/app/(dashboard)/configuration/changelog/page.tsx`

## Files Modified
- `backend/routes/api.php` -- Added changelog route
- `backend/config/search-pages.php` -- Added changelog search entry
- `frontend/app/(dashboard)/configuration/layout.tsx` -- Added Changelog to General group nav
- `frontend/components/sidebar.tsx` -- Made version footer clickable
- `frontend/lib/search-pages.ts` -- Added changelog search entry
- `frontend/app/(dashboard)/configuration/ai/page.tsx` -- 6 dark mode fixes
- `frontend/app/(dashboard)/configuration/log-retention/page.tsx` -- 1 dark mode fix
- `frontend/app/(dashboard)/configuration/jobs/page.tsx` -- 2 dark mode fixes
- `frontend/app/(dashboard)/configuration/logs/page.tsx` -- Log level dark mode fixes
- `frontend/components/storage/file-browser.tsx` -- 1 dark mode fix
- `frontend/app/(auth)/login/page.tsx` -- Theme-aware border fix
- `frontend/app/(dashboard)/user/security/page.tsx` -- 1 dark mode fix
- `docs/features.md` -- Added Changelog feature description
- `docs/roadmaps.md` -- Moved both items to Completed
- `docs/plans/changelog-roadmap.md` -- Marked Phases 1-3 complete
