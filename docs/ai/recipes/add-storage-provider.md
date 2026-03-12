# Recipe: Add Storage Provider

Step-by-step guide to add a new storage provider (cloud or S3-compatible) so users can select it in **Configuration > Storage** and use **Test Connection** to verify credentials.

## When to Use

- Adding a new cloud storage option (e.g. another S3-compatible or native Flysystem provider).
- The provider requires its own disk config, validation rules, and UI form.

**Existing providers:** local, s3, gcs, azure, do_spaces, minio, b2. See [Storage Settings pattern](../patterns/storage-settings.md) and [Storage Settings Enhancement Roadmap](../../plans/storage-settings-roadmap.md).

## Architecture Overview

Storage settings are stored in the `storage` group via `SystemSetting`. Keys are prefixed by provider (e.g. `s3_bucket`, `gcs_credentials_json`). `StorageService` builds Laravel disk config from that flat config and runs connection tests by writing/deleting a temp file. The UI shows a driver dropdown and dynamic form fields per provider.

```
Configuration > Storage  →  driver + provider_* fields  →  SystemSetting (storage)
                                    ↓
StorageService::buildDiskConfig()  →  Config::set(disk)  →  Storage::disk()  →  put/delete test
```

## Files to Create/Modify

| File | Action | Purpose |
|------|--------|---------|
| `backend/app/Services/StorageService.php` | Modify | Add provider to `PROVIDERS`, implement branch in `buildDiskConfig()` |
| `backend/config/filesystems.php` | Modify | Add disk definition (if new driver type) |
| `backend/app/Providers/AppServiceProvider.php` | Modify | Register custom Flysystem driver (only if not S3) |
| `backend/app/Http/Controllers/Api/StorageSettingController.php` | Modify | Add driver to `in:` rule, add validation rules for provider_* keys, test endpoint passes config to StorageService |
| `frontend/app/(dashboard)/configuration/storage/page.tsx` | Modify | Add option to driver dropdown, add form section and test payload for new provider |
| `frontend/components/provider-icons.tsx` | Modify | Add icon for new provider (if not reusing existing) |

## Step 1: Add Provider to StorageService

In `backend/app/Services/StorageService.php`:

1. **Add to `PROVIDERS` constant:**

```php
public const PROVIDERS = [
    // ... existing ...
    'my_provider' => ['label' => 'My Cloud', 'driver' => 's3'],  // or 'gcs', 'azure' for native
];
```

- Use `driver => 's3'` for S3-compatible APIs (custom endpoint). Use `'gcs'` or `'azure'` only if you register that driver in AppServiceProvider.

2. **Add `getSettingPrefix` entry** in `getSettingPrefix()`:

```php
'my_provider' => 'my_provider_',
```

3. **Implement `buildDiskConfig()` branch.** For S3-compatible:

```php
if ($provider === 'my_provider') {
    $diskConfig['endpoint'] = $config['my_provider_endpoint'] ?? 'https://...';
    $diskConfig['use_path_style_endpoint'] = true;  // or false
}
```

For GCS/Azure, add a full `if ($driver === 'gcs')`-style block that maps request keys (e.g. `my_provider_bucket`, `my_provider_credentials_json`) into the Laravel disk config shape expected by your driver.

## Step 2: Disk Definition (if new driver type)

If the provider uses a **new** Flysystem driver (not S3):

1. Add a disk in `backend/config/filesystems.php` (structure only; runtime values can come from DB or StorageService test).
2. In `backend/app/Providers/AppServiceProvider.php`, in `registerCustomFilesystemDrivers()`, add a `Storage::extend('my_driver', ...)` that builds the League adapter and returns `new \Illuminate\Filesystem\FilesystemAdapter(...)`. Use `class_exists()` so the app boots when the package is not installed.
3. Install the Flysystem adapter package (e.g. `league/flysystem-my-adapter`) and add it to `backend/composer.json`.

If the provider is **S3-compatible**, no new driver is needed; only the `buildDiskConfig()` branch with endpoint/key/secret/bucket.

## Step 3: Controller Validation and Test

In `backend/app/Http/Controllers/Api/StorageSettingController.php`:

1. **Allow new driver in rules:**  
   Update the `driver` rule to include the new value, e.g. `'in:local,s3,gcs,azure,do_spaces,minio,b2,my_provider'`.

2. **Add validation rules** for the new provider’s keys (required when this driver is selected):

```php
'my_provider_bucket' => ['required_if:driver,my_provider', 'nullable', 'string'],
'my_provider_endpoint' => ['required_if:driver,my_provider', 'nullable', 'string'],
'my_provider_key' => ['required_if:driver,my_provider', 'nullable', 'string'],
'my_provider_secret' => ['required_if:driver,my_provider', 'nullable', 'string'],
```

3. **Test endpoint:** `test()` already forwards request payload to `StorageService::testConnection($validated['driver'], $request->except(['driver']))`. No change needed if the frontend sends the same key names as the validation rules.

## Step 4: Frontend – Dropdown and Form

In `frontend/app/(dashboard)/configuration/storage/page.tsx`:

1. **Driver enum and dropdown:**  
   Add the new driver to the `DRIVERS` array and to `STORAGE_PROVIDERS` (id + label). The existing `Select` will show it.

2. **Provider icon:**  
   Use an existing icon id (e.g. `ProviderIcon provider="s3"`) or add a new id in `provider-icons.tsx` and use it here.

3. **Form section:**  
   Add a block for `driver === "my_provider"` with `Label` + `Input` (and `register("my_provider_bucket")`, etc.). Match the names to backend validation (e.g. `my_provider_bucket`, `my_provider_endpoint`).

4. **Test Connection payload:**  
   In `onTestConnection`, add a branch that spreads the new provider’s fields into the payload, e.g.:

```ts
...(driver === "my_provider" && {
  my_provider_bucket: watch("my_provider_bucket"),
  my_provider_endpoint: watch("my_provider_endpoint"),
  my_provider_key: watch("my_provider_key"),
  my_provider_secret: watch("my_provider_secret"),
}),
```

5. **Zod schema:**  
   Add optional fields for the new keys so the form type and validation stay in sync.

## Step 5: Provider Icon (optional)

If the provider should have its own icon:

1. In `frontend/components/provider-icons.tsx`, add the id to `ProviderIconId` and add an entry in `ALL_ICONS` (e.g. SVG path or existing icon).
2. In the storage page, use `provider={p.id}` for the new provider’s dropdown item (or the new icon id).

## Checklist

- [ ] `StorageService::PROVIDERS` and `getSettingPrefix()` include the new provider.
- [ ] `buildDiskConfig()` has a branch that maps request/DB keys to Laravel disk config (and sets endpoint for S3-compatible).
- [ ] New driver type only: disk in `filesystems.php`, `Storage::extend()` in AppServiceProvider, composer package.
- [ ] Controller: driver in `in:` rule; validation rules for all provider_* keys; test endpoint receives same keys.
- [ ] Frontend: driver in dropdown; form section and test payload; schema updated.
- [ ] Docs: [features.md](../features.md) Storage Settings, [api/README.md](../api/README.md) storage-settings table, [storage-settings-roadmap.md](../plans/storage-settings-roadmap.md) if you extend the list of providers.

## Related

- [Storage Settings pattern](../patterns/storage-settings.md) – StorageService, connection test, provider config.
- [Storage Settings Enhancement Roadmap](../../plans/storage-settings-roadmap.md) – Phases 1–4.
- [Context loading: Storage Settings Work](../context-loading.md#storage-settings-work) – Files to read when working on storage.

### Implementation Journal

- [Storage Settings Phase 1 (2026-01-30)](../../journal/2026-01-30-storage-settings-phase-1.md)
- [Storage Providers Phase 2 (2026-01-30)](../../journal/2026-01-30-storage-providers-phase-2.md)
- [Storage Phase 4 Analytics (2026-01-30)](../../journal/2026-01-30-storage-phase-4-analytics.md)
