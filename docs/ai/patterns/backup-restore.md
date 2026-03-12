# Backup & Restore Patterns

**Documentation:** Full backup docs (user, admin, developer; key files; recipes): [Backup & Restore](../../backup.md).

## Backup Settings (Database with Env Fallback)

Backup configuration is stored in the `backup` group in `backend/config/settings-schema.php`. ConfigServiceProvider injects flat keys into nested `config('backup.*')` at boot; BackupService and destination classes read only from `config()`. Do not read SettingService inside BackupService or destinations; keep config as the single source at runtime.

## Destination Interface

New storage destinations implement `App\Services\Backup\Destinations\DestinationInterface`: `upload(localPath, filename): array`, `download(filename, localPath): array`, `list(): array`, `delete(filename): bool`, `isAvailable(): bool`, `getName(): string`. Destinations read `config('backup.destinations.{id}.*')` in `__construct()`; config is populated from DB by ConfigServiceProvider.

## Settings API and Test Connection

BackupSettingController exposes `GET/PUT /backup-settings` and `POST /backup-settings/test/{destination}`. For Test Connection, the controller builds nested backup config from SettingService (via `injectBackupConfigFromSettings()`), injects it into config, then instantiates the destination and calls `isAvailable()`. Validation rules in `update()` must cover every backup schema key you expose in the UI.

## Backup UI

The backup page has two tabs: **Backups** (list, create, download, restore, delete) and **Settings** (form for retention, schedule, S3/SFTP/Google Drive, encryption, notifications). Settings are fetched when the user opens the Settings tab; form uses react-hook-form + zod; SaveButton submits to `PUT /backup-settings`; Test Connection calls `POST /backup-settings/test/{destination}`. Add new settings by extending the schema, defaultBackupSettings, fetch mapping, and a Card in the Settings tab.

## Adding a New Backup Setting

1. Add flat key to `backup` group in settings-schema.php (use `encrypted` for secrets).
2. Add env default in backup.php if needed.
3. Map flat → nested in ConfigServiceProvider::injectBackupConfig().
4. Add validation and injectBackupConfigFromSettings() in BackupSettingController.
5. Add field to frontend schema, defaults, fetch mapping, and a FormField/SettingsSwitchRow in the right Card.

## Adding a New Destination

See [Add backup destination](../recipes/add-backup-destination.md). Summary: implement DestinationInterface, add flat keys to backup schema and backup.php destinations, map in ConfigServiceProvider and BackupSettingController, add Card and Test Connection in Settings tab.

## Extending Backup/Restore Behavior

See [Extend backup and restore features](../recipes/extend-backup-restore.md) for new restore logic, scheduling, notifications, new backup content, or UI-only changes.

**Key files:**
- `backend/app/Services/Backup/BackupService.php`
- `backend/app/Services/Backup/Destinations/DestinationInterface.php`
- `backend/app/Services/Backup/Destinations/` (existing destinations)
- `backend/config/settings-schema.php` (backup group)
- `backend/config/backup.php`
- `backend/app/Providers/ConfigServiceProvider.php` (injectBackupConfig)
- `backend/app/Http/Controllers/Api/BackupController.php`
- `backend/app/Http/Controllers/Api/BackupSettingController.php`
- `frontend/app/(dashboard)/configuration/backup/page.tsx`

**Related:**
- [ADR-007: Backup System Design](../../adr/007-backup-system-design.md)
- [Recipe: Add Backup Destination](../recipes/add-backup-destination.md)
- [Recipe: Extend Backup & Restore](../recipes/extend-backup-restore.md)
- [Backup & Restore Documentation](../../backup.md)

## Implementation Journal

- [Backup Settings Migration (2026-01-29)](../../journal/2026-01-29-backup-settings-migration.md)
