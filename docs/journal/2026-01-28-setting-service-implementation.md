# SettingService Implementation (Env to Database Phase 1–2) - 2026-01-28

## Overview

Implemented the foundation for database-stored settings with environment fallback (Phase 1) and migrated mail settings to use it (Phase 2). Admins can change mail configuration via the UI without restart; values fall back to `.env` when not set in the database.

## Implementation Approach

- **SettingService** (`backend/app/Services/SettingService.php`): `get()`, `getGroup()`, `set()`, `reset()`, `all()` with file-based caching and env fallback. Schema-driven encryption for sensitive keys. Cache cleared on any update.
- **settings-schema.php**: Defines migratable settings per group with `env`, `default`, and `encrypted`. Mail group includes SMTP and provider-specific keys (Mailgun, SendGrid, SES, Postmark).
- **ConfigServiceProvider**: Registered first in `bootstrap/providers.php`. When DB is available, loads settings via SettingService and injects into Laravel config (`injectMailConfig()`). Skips when DB not ready (e.g. during migrations).
- **SystemSetting model**: Added `is_encrypted`; value attribute cast decrypts when `is_encrypted` is true. Removed global `value` json cast in favor of custom Attribute.
- **MailSettingController**: Uses SettingService; maps schema keys (mailer, smtp_host, …) to/from frontend keys (provider, host, …). Reset endpoint `DELETE /mail-settings/keys/{key}`. Test email applies current settings to config for the request.
- **Import command**: `php artisan settings:import-env [--group=mail]` copies set env vars into `system_settings` per schema.
- **Migration**: `2024_01_01_000011_add_is_encrypted_to_system_settings_table.php` adds `is_encrypted` column.

## Key Files

- `backend/app/Services/SettingService.php`
- `backend/app/Providers/ConfigServiceProvider.php`
- `backend/config/settings-schema.php`
- `backend/app/Models/SystemSetting.php` (value Attribute, is_encrypted)
- `backend/app/Http/Controllers/Api/MailSettingController.php`
- `backend/app/Console/Commands/ImportEnvSettings.php`
- `backend/database/migrations/2024_01_01_000011_add_is_encrypted_to_system_settings_table.php`

## Challenges Encountered

- **Value storage**: SystemSetting previously used a global `value` => 'json' cast. Encrypted values are stored as encrypted strings; custom Attribute handles JSON decode and decrypt on read, and stores raw/encoded on write so encrypted strings are not double-encoded.
- **Provider registration**: Laravel 11 uses `bootstrap/providers.php` (not `config/app.php`) for provider list; ConfigServiceProvider added there before AppServiceProvider so mail config is injected before other boot logic.
- **Mail config injection**: ConfigServiceProvider only injects when DB is ready; MailSettingController applies current settings to config for the “Send Test Email” request so the test uses the latest values without restart.

## Observations

- Keeping frontend key names (provider, host, port, …) and mapping to schema keys (mailer, smtp_host, …) in the controller avoids breaking the existing email configuration UI.
- File cache for settings avoids using the database as the cache store and prevents circular dependency at boot.

## Trade-offs

- Boot-time config is loaded once per request; changing a setting in the UI takes effect on the next request (no in-request reload). Test email explicitly applies current settings for that request.
- Only settings listed in settings-schema get env fallback and encryption; other SystemSetting usage (e.g. notifications toggles) remains direct for now.

## Next Steps (Future Considerations)

- Migrate notification, LLM, SSO, and backup settings to schema and ConfigServiceProvider.
- Add per-setting “reset to default” in the email configuration UI using `DELETE /mail-settings/keys/{key}`.
- Document which settings must remain env-only (APP_KEY, DB_*, CACHE_STORE, etc.) in a dedicated doc section.

## Testing Notes

- Run migration: `php artisan migrate`
- Verify mail settings load/save from Configuration > Email; send test email.
- Run `php artisan settings:import-env --group=mail` with mail env vars set; confirm values appear in DB and config reflects them after next request.
