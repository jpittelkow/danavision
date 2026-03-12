# AccessLogService Pattern (HIPAA Access Logging)

Use `AccessLogService` to log access to protected health information (PHI) for HIPAA compliance. This is separate from AuditService (which tracks user actions); AccessLogService tracks data access.

## Usage

```php
use App\Services\AccessLogService;

public function __construct(private AccessLogService $accessLog) {}

// View action
$this->accessLog->log('view', 'User', $user->id, ['name', 'email', 'phone']);

// Update action
$this->accessLog->log('update', 'User', $user->id, ['email']);

// Bulk view (list)
$this->accessLog->log('view', 'User', null, null);

// Export
$this->accessLog->log('export', 'User', null, ['all']);
```

- **When to use**: Any endpoint that reads, creates, updates, deletes, or exports user data (PHI).
- **Actions**: `view`, `create`, `update`, `delete`, `export`.
- **Resource types**: `User`, `Setting`, or any model containing PHI.
- **Middleware**: Prefer applying `log.access:User` (or `log.access:Setting`) to routes; the middleware resolves action and resource ID automatically and extracts **fields accessed** from request body (create/update) or JSON response (view), excluding sensitive keys.
- **Toggle**: HIPAA access logging can be disabled in Configuration > Log retention. When disabled, no logs are created; "Delete all access logs" is available (with HIPAA violation warning).

**Key files:** `backend/app/Services/AccessLogService.php`, `backend/app/Http/Middleware/LogResourceAccess.php`, `backend/app/Models/AccessLog.php`.

**Related:** [Recipe: Add access logging](../recipes/add-access-logging.md).

## Implementation Journal

- [Access Logs Field Tracking (2026-01-29)](../../journal/2026-01-29-access-logs-field-tracking.md)
- [HIPAA Logging Toggle (2026-01-29)](../../journal/2026-01-29-hipaa-logging-toggle.md)
- [Live Logs HIPAA Logging (2026-01-29)](../../journal/2026-01-29-live-logs-hipaa-logging.md)
