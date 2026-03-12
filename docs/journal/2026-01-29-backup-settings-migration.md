# Backup Settings Migration (Env to DB Phase 6) - 2026-01-29

## Overview

Completed Phase 6 of the Env to Database Migration: backup configuration is now stored in the database with env fallback, and the Backup page has a Settings tab for retention, schedule, S3/SFTP/Google Drive, encryption, and notifications. ADR-015 documents which settings must remain env-only.

## Implementation Approach

- **Settings schema**: Added `backup` group to `backend/config/settings-schema.php` with 40+ keys (disk, retention, schedule, s3, sftp, gdrive, encryption, notifications). Sensitive fields (passwords, keys, tokens) marked `encrypted`.
- **BackupSettingController**: `show()`, `update()`, `reset()`, `testDestination()`. Validation for all keys; test destination injects current backup settings into config and calls destination `isAvailable()`.
- **ConfigServiceProvider**: Added `injectBackupConfig()` so backup config is populated from DB at boot; BackupService and destination classes continue to read `config('backup.*')`.
- **Frontend**: Backup page (`frontend/app/(dashboard)/configuration/backup/page.tsx`) has two tabs: **Backups** (existing create/download/restore/delete) and **Settings** (form with sections: Retention & storage, Schedule, S3, SFTP, Google Drive, Encryption, Notifications). Save button and Test Connection for S3, SFTP, Google Drive. Settings fetched when user opens Settings tab.
- **ADR-015**: Created `docs/adr/015-env-only-settings.md` listing APP_KEY, APP_ENV, APP_DEBUG, DB_*, LOG_*, CACHE_STORE as env-only.

## Key Files

- `backend/config/settings-schema.php` – backup group
- `backend/app/Http/Controllers/Api/BackupSettingController.php`
- `backend/app/Providers/ConfigServiceProvider.php` – injectBackupConfig
- `backend/routes/api.php` – backup-settings routes
- `frontend/app/(dashboard)/configuration/backup/page.tsx` – tabs and settings form
- `docs/adr/015-env-only-settings.md`
- `docs/roadmaps.md` – Env to Database moved to Completed
- `docs/plans/env-to-database-roadmap.md` – checklist updated

## Challenges Encountered

- **Test Connection**: Test endpoint must use current DB settings; controller builds nested backup config from SettingService and injects it before instantiating destination so `isAvailable()` runs with saved credentials.
- **Nested config**: Backup config is nested (retention.enabled, destinations.s3.bucket); schema uses flat keys (retention_enabled, s3_bucket). ConfigServiceProvider and BackupSettingController map flat → nested for injection.

## Observations

- Reusing ConfigServiceProvider injection (like mail, LLM, SSO) keeps BackupService and destination classes unchanged; they continue to read config().
- Settings tab loads data on first open to avoid fetching when user only manages backups.

## Trade-offs

- Test Connection uses saved settings; user must save before testing if they just edited the form.
- Backup UI does not implement reset-per-key; API supports it (`POST /backup-settings/reset/{key}`) for future use.

## Next Steps (Future Considerations)

- Add per-setting reset in Backup Settings UI.
- Optional: use unsaved form values for Test Connection (submit current form to a test endpoint that accepts body).

## Testing Notes

- Load Configuration > Backup > Settings; verify retention, schedule, S3/SFTP/GDrive, encryption, notifications load and save.
- With S3/SFTP/GDrive configured, click Test Connection; expect success or clear error.
- Run `php artisan settings:import-env --group=backup` with backup env vars set; confirm config reflects DB after next request.
