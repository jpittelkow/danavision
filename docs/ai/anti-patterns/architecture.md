# Architecture Anti-Patterns

### Don't: Add Admin Routes Without Middleware

```php
// BAD - admin route without protection
Route::get('/admin/users', [AdminController::class, 'users']);
Route::get('/users/all', [AdminController::class, 'index']);

// GOOD - protected with auth AND admin middleware
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'users']);
});
```

### Don't: Store Secrets in Code

```php
// BAD - secrets in code
$apiKey = 'sk-1234567890abcdef';
Http::withHeaders(['Authorization' => 'Bearer sk-1234567890abcdef']);

// GOOD - use environment variables
$apiKey = config('services.example.api_key');
// In .env: EXAMPLE_API_KEY=sk-1234567890abcdef
// In config/services.php: 'example' => ['api_key' => env('EXAMPLE_API_KEY')]
```

### Don't: Mix User Settings with System Settings

```php
// BAD - using wrong model
$systemSetting = Setting::where('key', 'app_name')->first(); // User-scoped!

// GOOD - use the correct model
$systemSetting = SystemSetting::where('key', 'app_name')->first();

// User settings (has user_id)
$userSetting = Setting::where('user_id', $userId)
    ->where('key', 'theme')
    ->first();
```

### Don't: Use SystemSetting Directly for Schema-Backed Settings

Settings that are defined in `backend/config/settings-schema.php` (e.g. mail, future groups) should go through **SettingService** so env fallback and encryption apply. Using `SystemSetting::get()` / `SystemSetting::set()` directly for those keys bypasses cache, env fallback, and encryption.

```php
// BAD - no env fallback, no encryption, bypasses cache
$host = SystemSetting::get('smtp_host', null, 'mail');
SystemSetting::set('smtp_password', $password, 'mail', $user->id, false);

// GOOD - use SettingService for schema-backed groups
$host = $this->settingService->get('mail', 'smtp_host', '127.0.0.1');
$this->settingService->set('mail', 'smtp_password', $password, $user->id);
```

For groups **not** in settings-schema (e.g. notifications toggles, branding), `SystemSetting` directly is still correct.

### Don't: Read SettingService Inside BackupService or Destinations

Backup configuration is injected into Laravel config at boot by ConfigServiceProvider (from the `backup` group in settings-schema). BackupService and destination classes should read only from `config('backup.*')`, not from SettingService. Reading SettingService inside those classes bypasses the injected config and can cause inconsistent or uncached values.

```php
// BAD - bypasses config injection, may not reflect DB values at boot
$disk = $this->settingService->get('backup', 'disk', 'backups');

// GOOD - read from config (injected at boot from DB)
$disk = config('backup.disk', 'backups');
```

See [Backup & Restore patterns](../patterns/backup-restore.md) and [Backup documentation](../../backup.md).
