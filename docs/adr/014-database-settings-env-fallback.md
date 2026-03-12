# ADR-014: Database Settings with Environment Fallback

## Status

Accepted

## Date

2026-01-28

## Context

All application configuration is currently read from `.env` via Laravel config files. Changing settings requires editing the environment file and restarting the application. This creates several problems:

- Non-technical administrators cannot change settings without server access
- No audit trail for configuration changes
- Restart required for every configuration change
- Deployment or redeploy needed to change production settings

## Decision

We will store configurable settings in the `system_settings` table with environment fallback:

1. **SettingService**: A dedicated service (`backend/app/Services/SettingService.php`) provides `get()`, `getGroup()`, `set()`, `reset()`, and `all()` with env fallback. Settings are cached (file store) with configurable TTL; cache is cleared on any update.

2. **Settings schema**: `backend/config/settings-schema.php` defines all migratable settings per group with `env` (env var name), `default`, and `encrypted` keys. Only settings in the schema are read from DB with env fallback.

3. **ConfigServiceProvider**: Registered early in `bootstrap/providers.php`. After DB is available, loads settings via SettingService and injects them into Laravel config with `config()->set()`. Skips injection when DB is not ready (e.g. during migrations).

4. **Encryption**: Sensitive values (API keys, passwords) are stored encrypted using Laravel's `encrypt()`; the `SystemSetting` model decrypts on read when `is_encrypted` is true.

5. **Bootstrap-only in env**: Settings required before DB is available (e.g. `APP_KEY`, `DB_*`, `CACHE_STORE`, `LOG_*`) remain in `.env` only.

## Consequences

### Positive

- Settings changeable via admin UI without restart
- Backward compatible: existing deployments continue to work; env values are used when no DB value exists
- Audit trail via `updated_by` on `system_settings`
- Sensitive values encrypted at rest
- Single source of truth for “what can be stored in DB” via settings-schema

### Negative

- Boot-time config depends on DB; fresh installs or failed migrations must not assume settings table exists (ConfigServiceProvider guards with `databaseReady()`)
- File cache for settings must not use DB driver to avoid circular dependency

### Neutral

- Future groups (SSO, LLM, notifications, etc.) are added by extending the schema and ConfigServiceProvider injection methods
- Artisan command `settings:import-env` copies current env values into DB for migration

## Degraded Mode (When DB Is Unavailable)

When `ConfigServiceProvider::databaseReady()` returns false (e.g. during migrations, fresh installs, or database failures), the provider **silently skips** config injection. The application runs in degraded mode:

- **All schema-backed settings revert to env defaults** — any values stored in `system_settings` are ignored
- **Features behave as if no admin UI changes were ever made** — mail provider, SSO credentials, LLM keys, backup destinations all use `.env` values
- **The app is functional** — it just uses environment config instead of database overrides
- **No errors are thrown** — the degradation is silent; check logs for `ConfigServiceProvider: database not ready, skipping` if investigating

**Cache corruption:** If the SettingService file cache becomes corrupted or stale, clear it with `php artisan cache:clear`. The cache uses the file store (not DB) to avoid circular dependency.

**Production safety:** `config:cache` should only be run when the DB is available, otherwise cached config will contain only env defaults. The entrypoint script guards this with `if [ "$APP_ENV" = "production" ]`.

## Related Decisions

- [ADR-012: Admin-Only Settings Access](./012-admin-only-settings.md) – Settings UI and API remain admin-only
- [ADR-015: Environment-Only Settings](./015-env-only-settings.md) – Bootstrap settings that must stay in `.env`

## Key Files

- `backend/app/Services/SettingService.php` – Core settings service with caching and env fallback
- `backend/app/Providers/ConfigServiceProvider.php` – Boot-time config injection
- `backend/config/settings-schema.php` – Definition of migratable settings
- `backend/app/Models/SystemSetting.php` – Model with `is_encrypted` and value decryption

## Implementation Journal

- [Section 2 Settings Implementation (2026-01-26)](../journal/2026-01-26-section-2-settings-implementation.md)
- [Setting Service Implementation (2026-01-28)](../journal/2026-01-28-setting-service-implementation.md)
