# Per-User Timezone Support

**Date:** 2026-02-15

## Summary

Added per-user timezone settings with automatic browser detection and timezone-aware date formatting throughout the application.

## Changes

### Backend
- **`backend/config/settings-schema.php`**: Added `general.default_timezone` with `APP_TIMEZONE` env fallback, giving the admin system default proper SettingService integration.
- **`backend/app/Models/User.php`**: Added `getTimezone()` method implementing the fallback chain: user setting -> admin system default -> `APP_TIMEZONE` -> UTC.
- **`backend/app/Http/Controllers/Api/UserSettingController.php`**: Extended `show()` and `update()` to include `timezone` and `effective_timezone`. Added `detectTimezone()` endpoint that sets timezone only if the user hasn't explicitly chosen one (idempotent on repeated logins).
- **`backend/routes/api.php`**: Added `POST /user/settings/detect-timezone` route.
- **`backend/app/Http/Controllers/Api/AuthController.php`**: `GET /auth/user` now includes `timezone` in the response.

### Frontend
- **`frontend/lib/timezones.ts`** (new): Shared timezone constants (70+ entries), `detectBrowserTimezone()`, and `getTimezoneLabel()` utilities.
- **`frontend/lib/auth.ts`**: After login, register, and 2FA verify, fires a background `POST /user/settings/detect-timezone` call. `fetchUser()` now sets the user timezone from the `/auth/user` response.
- **`frontend/lib/utils.ts`**: Added `setUserTimezone()` / `getUserTimezone()` module-level state. `formatDate()`, `formatDateTime()`, and `formatTimestamp()` now apply the user's timezone via the `Intl.DateTimeFormat` `timeZone` option.
- **`frontend/app/(dashboard)/user/preferences/page.tsx`**: Added "Regional" card with timezone `Select` dropdown between Defaults and Notification Preferences. Shows auto-detected vs. manually set status.
- **`frontend/app/(dashboard)/configuration/system/page.tsx`**: Replaced inline `TIMEZONES` constant with import from shared `@/lib/timezones`.

### Documentation
- **`frontend/lib/help/help-content.ts`**: Added "Timezone Settings" help article.
- **`docs/features.md`**: Added "User Preferences & Regional Settings" section.

## Architecture Decisions

- **Storage**: Per-user timezone stored in the existing `settings` table (`group: 'general'`, `key: 'timezone'`) rather than a column on the users table. This follows the established pattern for user preferences.
- **Auto-detection is write-once**: The detect endpoint only sets the timezone if the user has no existing setting, preventing overwrite on every login.
- **Fallback chain**: User setting -> System default -> APP_TIMEZONE -> UTC. This allows admins to set a default for their org while users can override individually.
- **Frontend formatting**: Timezone is applied at the `Intl.DateTimeFormat` level, so all existing `formatDate`/`formatDateTime`/`formatTimestamp` calls automatically use the user's timezone without caller changes.

## Key Files

- `backend/app/Models/User.php` - `getTimezone()` method
- `backend/app/Http/Controllers/Api/UserSettingController.php` - timezone CRUD + detect
- `frontend/lib/timezones.ts` - shared constants and detection
- `frontend/lib/utils.ts` - timezone-aware formatting
- `frontend/lib/auth.ts` - auto-detect on auth flows
- `frontend/app/(dashboard)/user/preferences/page.tsx` - timezone picker UI
