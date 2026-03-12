# Recipe: Trigger Audit Logging

Step-by-step guide to log user actions and system changes using the AuditService.

## When to Use

- **Auth events**: Login, logout, failed login, password reset, 2FA enable/disable
- **User management**: User created, updated, deleted, disabled, admin granted/revoked
- **Settings changes**: Mail, SSO, backup, or system settings updated
- **Sensitive operations**: Backup created, restored, deleted, downloaded
- **Compliance**: Any action that should be traceable (who, when, what)

## Files to Modify

| File | Purpose |
|------|---------|
| Your controller or service | Call AuditService or use AuditLogging trait |
| `backend/app/Services/AuditService.php` | No change unless adding new convenience methods |

## Step 1: Inject AuditService or Use Trait

**Option A: Inject AuditService (recommended for controllers with multiple audit calls)**

```php
use App\Services\AuditService;

class YourController extends Controller
{
    public function __construct(
        private AuditService $auditService
    ) {}
}
```

**Option B: Use AuditLogging trait (for simple one-off logs)**

```php
use App\Http\Traits\AuditLogging;

class YourController extends Controller
{
    use AuditLogging;

    // Then call: $this->audit('action.name', $model, $oldValues, $newValues);
}
```

## Step 2: Choose the Right Method

| Method | Use case | Action format |
|--------|----------|---------------|
| `log()` | Generic action with optional model and old/new values | Any `resource.action` |
| `logAuth()` | Auth-related (login, logout, 2FA, password reset) | `auth.{action}` |
| `logSettings()` | Settings group updated (mail, sso, backup, system) | `settings.updated` |
| `logUserAction()` | User action with no model | Any string |
| `logModelChange()` | Model updated (old/new values) | e.g. `user.updated` |

## Step 3: Call from Controllers

### Auth events

```php
$this->auditService->logAuth('login', $user);
$this->auditService->logAuth('login_failed', null, ['email' => $email], 'warning');
$this->auditService->logAuth('logout', $user);
$this->auditService->logAuth('password_reset', $user);
$this->auditService->logAuth('2fa_enabled', $user);
$this->auditService->logAuth('2fa_disabled', $user);
```

### User management

```php
$this->auditService->log('user.created', $user, [], ['name' => $user->name, 'email' => $user->email]);
$this->auditService->logModelChange($user, 'user.updated', $oldValues, $newValues);
$this->auditService->log('user.deleted', $user, ['name' => $user->name], []);
$this->auditService->log('user.disabled', $user, ['disabled_at' => $old], ['disabled_at' => $new]);
$this->auditService->log('user.admin_granted', $user, [], ['group' => 'admin']);
$this->auditService->log('user.admin_revoked', $user, ['group' => 'admin'], []);
```

### Settings

```php
$oldSettings = $this->settingService->getGroup('mail');
// ... update settings ...
$this->auditService->logSettings('mail', $oldSettings, $newSettings, $request->user()->id);
```

### Backup operations

```php
$this->auditService->log('backup.created', null, [], ['filename' => $backup['filename']]);
$this->auditService->log('backup.restored', null, [], ['filename' => $filename], null, null, 'warning');
$this->auditService->log('backup.deleted', null, [], ['filename' => $filename]);
$this->auditService->log('backup.downloaded', null, [], ['filename' => $filename]);
```

## Step 4: Action Naming Convention

Use `{resource}.{action}`:

- **Resources**: auth, user, settings, backup, email_template, notification
- **Actions**: created, updated, deleted, enabled, disabled, login, logout, etc.

Examples: `auth.login`, `auth.login_failed`, `user.created`, `settings.updated`, `backup.restored`.

## Step 5: Severity

| Severity | When to use |
|----------|-------------|
| `info` | Normal operations (login, create, update) |
| `warning` | Failed login, account disabled, destructive actions (restore) |
| `error` | Security-related failures |
| `critical` | Critical security or system events |

Pass as last argument: `$this->auditService->log(..., 'warning')` or use `AuditLog::SEVERITY_WARNING`.

## Checklist

- [ ] AuditService injected or AuditLogging trait used
- [ ] Action name follows `{resource}.{action}`
- [ ] Sensitive data not passed in old_values/new_values (service masks password, token, secret, api_key)
- [ ] Severity set for destructive or security-relevant actions (e.g. warning for restore)
- [ ] Log call does not block the request (service catches errors and returns null)

## Related Documentation

- [AuditService Pattern](../patterns/audit-service.md)
- [Recipe: Add auditable action](add-auditable-action.md)
- [Audit Logs Implementation Plan](../../plans/audit-logs-implementation-plan.md)

### Implementation Journal

- [Audit Logging Implementation (2026-01-29)](../../journal/2026-01-29-audit-logging-implementation.md)
- [Audit Dashboard Analytics (2026-01-29)](../../journal/2026-01-29-audit-dashboard-analytics.md)
