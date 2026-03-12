# Settings Overview: How to Read/Write Settings

Sourdough has multiple settings systems. Use this guide to pick the right one.

## Decision Flowchart

```
Is it a per-user preference (theme, LLM mode, notification channels)?
  YES → Use `Setting` model directly ($user->getSetting(), Setting::where('user_id', ...))
  NO  ↓

Is the setting defined in backend/config/settings-schema.php?
  YES → Are you in a Controller or route handler?
          YES → Use SettingService (get/set/reset with env fallback, caching, encryption)
          NO  → Are you in a Service class (BackupService, StorageService, etc.)?
                  YES → Use config() — ConfigServiceProvider already injected DB values at boot
                  NO  → Use SettingService
  NO  ↓

Is it a system-wide toggle not in the schema (e.g. branding, notification channel toggles)?
  YES → Use SystemSetting model directly (SystemSetting::get/set)
  NO  ↓

Is it a bootstrap/infrastructure setting (APP_KEY, DB_*, LOG_*, CACHE_STORE)?
  YES → Must stay in .env only — see ADR-015
```

## Quick Reference

| What you want | Where it lives | How to access |
|---------------|---------------|---------------|
| User preference (theme, LLM mode) | `settings` table (has `user_id`) | `Setting` model, `$user->getSetting()` |
| System setting in schema (mail, SSO, LLM, backup, storage, auth) | `system_settings` table | **SettingService** in controllers; `config()` in services |
| System setting NOT in schema (branding toggles) | `system_settings` table | `SystemSetting` model directly |
| Backup config in BackupService/Destinations | `config('backup.*')` | `config()` — injected by ConfigServiceProvider |
| Storage config in StorageService | `config('filesystems.*')` | `config()` — injected by ConfigServiceProvider |
| Bootstrap settings (APP_KEY, DB_*) | `.env` only | `env()` or `config()` — never in DB |

## Why Services Use config() Instead of SettingService

`ConfigServiceProvider` loads all schema-backed settings from the database at boot time and injects them into Laravel's config via `config()->set()`. This means service classes like `BackupService` and its destination classes can simply read `config('backup.destinations.s3.bucket')` — the value already reflects the DB override (or env fallback).

Reading SettingService inside these classes would bypass the injected config and could produce inconsistent values. See [Anti-pattern: Don't read SettingService inside BackupService](../anti-patterns/architecture.md#dont-read-settingservice-inside-backupservice-or-destinations).

**Key files:** `backend/app/Services/SettingService.php`, `backend/app/Providers/ConfigServiceProvider.php`, `backend/config/settings-schema.php`, `backend/app/Models/SystemSetting.php`, `backend/app/Models/Setting.php`

**Related:** [SettingService Pattern](setting-service.md), [ADR-014](../../adr/014-database-settings-env-fallback.md), [ADR-015](../../adr/015-env-only-settings.md), [Anti-patterns: Architecture](../anti-patterns/architecture.md)

## Implementation Journal

- [Section 2 Settings Implementation (2026-01-26)](../../journal/2026-01-26-section-2-settings-implementation.md)
- [Setting Service Implementation (2026-01-28)](../../journal/2026-01-28-setting-service-implementation.md)
- [LLM Settings Consolidation (2026-01-29)](../../journal/2026-01-29-llm-settings-consolidation.md)
- [Backup Settings Migration (2026-01-29)](../../journal/2026-01-29-backup-settings-migration.md)
- [SSO Settings Enhancement (2026-01-30)](../../journal/2026-01-30-sso-settings-enhancement.md)
- [Storage Settings Phase 1 (2026-01-30)](../../journal/2026-01-30-storage-settings-phase-1.md)
- [Configurable Auth Features (2026-01-30)](../../journal/2026-01-30-configurable-auth-features.md)
