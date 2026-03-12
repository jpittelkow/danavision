# Storage Settings Pattern

**Documentation:** [Storage Settings Enhancement Roadmap](../../plans/storage-settings-roadmap.md), [Features: Storage Settings](../../features.md#storage-settings).

## Storage Settings (Database)

Storage configuration is stored in the `storage` group via `SystemSetting::get`/`set` (no settings-schema; keys are driver plus provider-prefixed, e.g. `driver`, `s3_bucket`, `gcs_credentials_json`). All provider credentials and options use this single group so one active driver is configured at a time.

## StorageService

`App\Services\StorageService` defines `PROVIDERS` (id → label, driver), `getProviderConfig(provider)`, `getAvailableProviders()`, `testConnection(provider, config)`, and `buildDiskConfig(provider, config)`. Connection test: build disk config from request/DB keys, set a temporary disk name in config, `Storage::disk(name)->put()` then `delete()` a test path, clear the temp disk. For S3-compatible providers the driver is `s3` with custom `endpoint` and `use_path_style_endpoint`; for GCS/Azure the driver is `gcs`/`azure` and drivers are registered in AppServiceProvider via `Storage::extend()` when the Flysystem adapter package is installed.

## Settings API and Test Connection

StorageSettingController exposes `GET/PUT /storage-settings`, `POST /storage-settings/test`, `GET /storage-settings/stats`, `GET /storage-settings/paths`, `GET /storage-settings/health`. Test accepts `driver` and provider-prefixed keys in the body; controller calls `StorageService::testConnection($validated['driver'], $request->except(['driver']))`. Validation in `update()` uses `required_if:driver,{provider}` for each provider's keys (e.g. `s3_bucket`, `gcs_credentials_json`, `minio_endpoint`).

## Storage UI

Configuration > Storage: driver dropdown (local, s3, gcs, azure, do_spaces, minio, b2) with ProviderIcon, dynamic form sections per driver, max upload size and allowed file types (shared), Test Connection button (non-local) with loading/success/error state, Save. Test payload must include `driver` and all visible provider fields so the backend can build disk config.

## Adding a New Storage Provider

See [Add storage provider](../recipes/add-storage-provider.md). Summary: add to `StorageService::PROVIDERS` and `getSettingPrefix`, implement `buildDiskConfig()` branch; add disk in filesystems.php and optionally `Storage::extend()` for new driver types; add validation rules and frontend form section + test payload; add provider icon if needed.

**Key files:**
- `backend/app/Services/StorageService.php`
- `backend/app/Http/Controllers/Api/StorageSettingController.php`
- `backend/config/filesystems.php`
- `backend/app/Providers/AppServiceProvider.php` (GCS/Azure Storage::extend)
- `backend/app/Console/Commands/StorageAlertCommand.php`
- `frontend/app/(dashboard)/configuration/storage/page.tsx`
- `frontend/components/provider-icons.tsx`

**Related:**
- [Recipe: Add Storage Provider](../recipes/add-storage-provider.md)
- [Storage Settings Enhancement Roadmap](../../plans/storage-settings-roadmap.md)
- [Features: Storage Settings](../../features.md#storage-settings)

## Implementation Journal

- [Storage Settings Phase 1 (2026-01-30)](../../journal/2026-01-30-storage-settings-phase-1.md)
- [Storage Providers Phase 2 (2026-01-30)](../../journal/2026-01-30-storage-providers-phase-2.md)
- [Storage Phase 4 Analytics (2026-01-30)](../../journal/2026-01-30-storage-phase-4-analytics.md)
