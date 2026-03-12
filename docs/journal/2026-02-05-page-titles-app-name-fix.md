# Page Titles App Name Fix - 2026-02-05

## Overview

Fixed a bug where page titles did not display the application name from the database. The `app_name` setting was saved correctly but never returned by the public settings API because the `is_public` flag was never persisted.

## Root Cause

- The public API (`GET /api/system-settings/public`) returns only settings where `is_public = true`
- When saving via the System Settings page, the frontend correctly sent `is_public: true` for `app_name`
- The backend `SettingService::set()` did not accept or persist `is_public`; it was ignored
- As a result, `app_name` was stored with `is_public = false` (default) and never returned to the frontend
- Page titles, branding, and other components that rely on `useAppConfig().appName` received an empty string

## Implementation Approach

1. **Schema-based `is_public`**: Added `general` group to `backend/config/settings-schema.php` with `app_name` and `app_url` marked as `public: true`
2. **SettingService update**: Modified `SettingService::set()` to read the `public` flag from the schema and set `is_public` on the database record
3. **Migration**: Created `2026_02_05_000024_set_app_name_app_url_public_in_system_settings.php` to fix existing records

## Key Files

- `backend/config/settings-schema.php` – Added `general` group with `app_name`, `app_url`
- `backend/app/Services/SettingService.php` – Sets `is_public` from schema when saving
- `backend/database/migrations/2026_02_05_000024_set_app_name_app_url_public_in_system_settings.php` – Backfill existing records

## Verification

After the fix, `GET /api/system-settings/public` returns `settings.general.app_name`, and page titles display correctly (e.g., "Dashboard | My App Name").
