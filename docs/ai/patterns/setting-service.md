# SettingService Pattern

Use `SettingService` for system-wide settings that can be stored in the database with environment fallback. Do not use `SystemSetting` directly for migratable settings; use the service so env fallback and caching apply.

## Usage

```php
<?php
// Inject SettingService in controller or service
use App\Services\SettingService;

public function __construct(private SettingService $settingService) {}

// Get a single setting (env fallback)
$value = $this->settingService->get('mail', 'smtp_host', '127.0.0.1');

// Get all settings for a group (env fallback)
$mailSettings = $this->settingService->getGroup('mail');

// Set a setting (encryption applied per settings-schema; cache cleared)
$this->settingService->set('mail', 'smtp_password', $password, $request->user()->id);

// Check if value is overridden in DB (vs env)
if ($this->settingService->isOverridden('mail', 'smtp_host')) { ... }

// Reset to env default (delete from DB, clear cache)
$this->settingService->reset('mail', 'smtp_password');

// Get all settings (for boot-time config injection; cached)
$all = $this->settingService->all();
```

- Define new migratable settings in `backend/config/settings-schema.php` with `env`, `default`, `encrypted`, and optionally `public` keys. Use `'public' => true` for settings that must be exposed via `GET /system-settings/public` (e.g. `general.app_name` for page titles and branding).
- Add boot-time injection in `ConfigServiceProvider::boot()` for new groups.
- Use file cache only for settings (not DB) to avoid circular dependency.

**Key files:** `backend/app/Services/SettingService.php`, `backend/app/Providers/ConfigServiceProvider.php`, `backend/config/settings-schema.php`, `backend/app/Models/SystemSetting.php`.

**Related:** [Recipe: Add config page](../recipes/add-config-page.md), [ADR-014: Database Settings with Env Fallback](../../adr/014-database-settings-env-fallback.md).

## Implementation Journal

- [Setting Service Implementation (2026-01-28)](../../journal/2026-01-28-setting-service-implementation.md)
- [Section 2 Settings Implementation (2026-01-26)](../../journal/2026-01-26-section-2-settings-implementation.md)
- [LLM Settings Consolidation (2026-01-29)](../../journal/2026-01-29-llm-settings-consolidation.md)
- [SSO Settings Migration (2026-01-28)](../../journal/2026-01-28-sso-settings-migration.md)
- [Backup Settings Migration (2026-01-29)](../../journal/2026-01-29-backup-settings-migration.md)
- [Meilisearch Configuration (2026-01-30)](../../journal/2026-01-30-meilisearch-configuration.md)
