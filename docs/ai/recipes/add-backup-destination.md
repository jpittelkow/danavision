# Recipe: Add Backup Destination

Step-by-step guide to add a new backup storage destination. Backup configuration is stored in the database (env fallback) via SettingService; destinations read from `config('backup.destinations.*')` which ConfigServiceProvider injects at boot.

**Backup documentation hub:** [Backup & Restore](../../backup.md) – user, admin, and developer docs; key files; all backup-related ADRs and recipes.

## Prerequisites

- [ADR-007: Backup System Design](../../adr/007-backup-system-design.md)
- [ADR-014: Database Settings with Environment Fallback](../../adr/014-database-settings-env-fallback.md)
- Existing destinations: `S3Destination`, `SFTPDestination`, `GoogleDriveDestination`

## Files to Create/Modify

| File | Action | Purpose |
|------|--------|---------|
| `backend/app/Services/Backup/Destinations/{Name}Destination.php` | Create | Destination implementation |
| `backend/config/settings-schema.php` | Modify | Add backup group keys for the destination |
| `backend/config/backup.php` | Modify | Add destination config structure (env defaults) |
| `backend/app/Providers/ConfigServiceProvider.php` | Modify | Map flat settings to `backup.destinations.{id}` |
| `backend/app/Http/Controllers/Api/BackupSettingController.php` | Modify | Validation, test destination, inject config |
| `frontend/app/(dashboard)/configuration/backup/page.tsx` | Modify | Settings tab: form schema, Card, Test Connection |

## Step 1: Implement DestinationInterface

Use `App\Services\Backup\Destinations\DestinationInterface` (not a different interface). Methods:

- `upload(string $localPath, string $filename): array` – return `['success' => true, 'filename' => $filename, ...]`
- `download(string $filename, string $localPath): array` – return `['success' => true, 'local_path' => $localPath, ...]`
- `list(): array` – list backup files with metadata (filename, size, last_modified)
- `delete(string $filename): bool`
- `isAvailable(): bool` – configured and reachable (used by Test Connection)
- `getName(): string` – display name (e.g. "Amazon S3")

Read config in `__construct()` from `config('backup.destinations.{id}.*')`. Config is injected at boot from DB by ConfigServiceProvider; for secrets use keys marked `encrypted` in the schema.

Example shape (adjust to your destination):

```php
<?php
// backend/app/Services/Backup/Destinations/ExampleDestination.php

namespace App\Services\Backup\Destinations;

use Illuminate\Support\Facades\Storage;

class ExampleDestination implements DestinationInterface
{
    private string $bucket;
    private string $prefix;
    private string $disk = 'backup-example';

    public function __construct()
    {
        $this->bucket = config('backup.destinations.example.bucket', '');
        $this->prefix = rtrim(config('backup.destinations.example.path', 'backups'), '/');
        if (!config("filesystems.disks.{$this->disk}")) {
            config(["filesystems.disks.{$this->disk}" => [
                'driver' => 's3',
                'key' => config('backup.destinations.example.key'),
                'secret' => config('backup.destinations.example.secret'),
                'region' => config('backup.destinations.example.region', 'us-east-1'),
                'bucket' => $this->bucket,
                'endpoint' => config('backup.destinations.example.endpoint'),
            ]]);
        }
    }

    public function upload(string $localPath, string $filename): array
    {
        $remotePath = $this->prefix ? "{$this->prefix}/{$filename}" : $filename;
        Storage::disk($this->disk)->put($remotePath, file_get_contents($localPath));
        return ['success' => true, 'filename' => $filename, 'remote_path' => $remotePath];
    }

    public function download(string $filename, string $localPath): array
    {
        $remotePath = $this->prefix ? "{$this->prefix}/{$filename}" : $filename;
        if (!Storage::disk($this->disk)->exists($remotePath)) {
            throw new \RuntimeException("Backup not found: {$filename}");
        }
        $content = Storage::disk($this->disk)->get($remotePath);
        file_put_contents($localPath, $content);
        return ['success' => true, 'filename' => $filename, 'local_path' => $localPath];
    }

    public function list(): array
    {
        $files = Storage::disk($this->disk)->files($this->prefix);
        return collect($files)
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(fn ($f) => [
                'filename' => basename($f),
                'remote_path' => $f,
                'size' => Storage::disk($this->disk)->size($f),
                'last_modified' => Storage::disk($this->disk)->lastModified($f),
            ])
            ->values()
            ->all();
    }

    public function delete(string $filename): bool
    {
        $remotePath = $this->prefix ? "{$this->prefix}/{$filename}" : $filename;
        return Storage::disk($this->disk)->delete($remotePath);
    }

    public function isAvailable(): bool
    {
        if (empty($this->bucket)) return false;
        try {
            Storage::disk($this->disk)->files($this->prefix);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'Example Storage';
    }
}
```

## Step 2: Add settings to schema and config

**settings-schema.php** – add flat keys under `backup` (use a prefix like `example_`):

```php
// backend/config/settings-schema.php – inside 'backup' group
'example_enabled' => ['env' => 'BACKUP_EXAMPLE_ENABLED', 'default' => false],
'example_bucket' => ['env' => 'BACKUP_EXAMPLE_BUCKET', 'default' => null],
'example_path' => ['env' => 'BACKUP_EXAMPLE_PATH', 'default' => 'backups'],
'example_access_key_id' => ['env' => 'BACKUP_EXAMPLE_ACCESS_KEY', 'default' => null, 'encrypted' => true],
'example_secret_access_key' => ['env' => 'BACKUP_EXAMPLE_SECRET', 'default' => null, 'encrypted' => true],
'example_region' => ['env' => 'BACKUP_EXAMPLE_REGION', 'default' => 'us-east-1'],
'example_endpoint' => ['env' => 'BACKUP_EXAMPLE_ENDPOINT', 'default' => null],
```

**backup.php** – add destination entry (env defaults; DB overrides applied by ConfigServiceProvider):

```php
// backend/config/backup.php – inside 'destinations'
'example' => [
    'enabled' => env('BACKUP_EXAMPLE_ENABLED', false),
    'driver' => 'example',
    'bucket' => env('BACKUP_EXAMPLE_BUCKET'),
    'path' => env('BACKUP_EXAMPLE_PATH', 'backups'),
    'region' => env('BACKUP_EXAMPLE_REGION', 'us-east-1'),
    'endpoint' => env('BACKUP_EXAMPLE_ENDPOINT'),
],
```

## Step 3: ConfigServiceProvider – inject backup config

In `injectBackupConfig()`, map flat keys to nested config so `config('backup.destinations.example.*')` is set:

```php
if (array_key_exists('example_enabled', $settings)) {
    config(['backup.destinations.example.enabled' => $settings['example_enabled'] ?? config('backup.destinations.example.enabled')]);
}
if (array_key_exists('example_bucket', $settings)) {
    config(['backup.destinations.example.bucket' => $settings['example_bucket'] ?? config('backup.destinations.example.bucket')]);
}
if (array_key_exists('example_path', $settings)) {
    config(['backup.destinations.example.path' => $settings['example_path'] ?? config('backup.destinations.example.path')]);
}
if (array_key_exists('example_access_key_id', $settings)) {
    config(['backup.destinations.example.key' => $settings['example_access_key_id'] ?? config('backup.destinations.example.key')]);
}
if (array_key_exists('example_secret_access_key', $settings)) {
    config(['backup.destinations.example.secret' => $settings['example_secret_access_key'] ?? config('backup.destinations.example.secret')]);
}
if (array_key_exists('example_region', $settings)) {
    config(['backup.destinations.example.region' => $settings['example_region'] ?? config('backup.destinations.example.region')]);
}
if (array_key_exists('example_endpoint', $settings)) {
    config(['backup.destinations.example.endpoint' => $settings['example_endpoint'] ?? config('backup.destinations.example.endpoint')]);
}
```

## Step 4: BackupSettingController

- **Validation** – in `update()`, add rules for the new keys (e.g. `example_enabled`, `example_bucket`, `example_access_key_id`, `example_secret_access_key`, `example_region`, `example_endpoint`, `example_path`).
- **testDestination** – in `DESTINATION_CLASSES`, add `'example' => ExampleDestination::class`.
- **injectBackupConfigFromSettings()** – add the same flat → nested mapping as in ConfigServiceProvider so Test Connection uses current DB values.

## Step 5: Frontend – Settings tab

In `frontend/app/(dashboard)/configuration/backup/page.tsx`:

1. **Schema** – extend `backupSettingsSchema` and `BackupSettingsForm` with the new fields (e.g. `example_enabled`, `example_bucket`, …).
2. **defaultBackupSettings** – add default values.
3. **fetchBackupSettings** – in the mapping from `response.data.settings`, add the new keys.
4. **Settings tab** – add a new Card (e.g. "Example Storage") with:
   - `SettingsSwitchRow` for enabled
   - `FormField` + `Input` for bucket, path, access key, secret key, region, endpoint (use `type="password"` for secrets)
   - Button "Test Connection" that calls `handleTestDestination('example')`

## DestinationInterface reference

```php
// backend/app/Services/Backup/Destinations/DestinationInterface.php

interface DestinationInterface
{
    public function upload(string $localPath, string $filename): array;
    public function download(string $filename, string $localPath): array;
    public function list(): array;
    public function delete(string $filename): bool;
    public function isAvailable(): bool;
    public function getName(): string;
}
```

## Checklist

- [ ] Destination class implements `DestinationInterface` with correct method signatures
- [ ] `upload`/`download`/`list`/`delete` return the expected shapes; `isAvailable()` used by Test Connection
- [ ] Flat keys added to `backend/config/settings-schema.php` (backup group); secrets have `'encrypted' => true`
- [ ] Destination entry added to `backend/config/backup.php` under `destinations`
- [ ] `ConfigServiceProvider::injectBackupConfig()` maps new flat keys to `backup.destinations.example.*`
- [ ] `BackupSettingController`: validation, `DESTINATION_CLASSES`, and `injectBackupConfigFromSettings()` updated
- [ ] Frontend: schema, defaults, fetch mapping, and Settings tab Card with Test Connection
- [ ] Optional: add `example` to `scheduled_destinations` options in UI/docs

## Reference implementations

- `backend/app/Services/Backup/Destinations/S3Destination.php`
- `backend/app/Services/Backup/Destinations/SFTPDestination.php`
- `backend/app/Services/Backup/Destinations/GoogleDriveDestination.php`
- Backup settings schema: `backend/config/settings-schema.php` (backup group)
- Backup UI: `frontend/app/(dashboard)/configuration/backup/page.tsx` (Settings tab)

## Related

- [Extend backup and restore features](extend-backup-restore.md) – adding new backup settings, restore behavior, scheduling, notifications
- [ADR-007: Backup System Design](../../adr/007-backup-system-design.md)
- [SettingService pattern](../patterns/setting-service.md)
