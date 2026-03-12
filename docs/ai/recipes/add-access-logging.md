# Recipe: Add HIPAA Access Logging to a Feature

How to add access logging when creating features that access protected data (PHI).

## When to Use

Add access logging when your feature:

- Reads user profile data
- Modifies user data
- Exports user data
- Lists users (bulk view)
- Accesses user settings

## Steps

### Option 1: Use Middleware (Recommended for Routes)

1. Apply `log.access` middleware to routes in `api.php`. The parameter is the resource type (e.g. `User`, `Setting`):

```php
Route::middleware(['auth:sanctum', 'log.access:User'])->get('/users/{user}', [UserController::class, 'show']);
```

2. The middleware automatically:

   - Derives action from HTTP method (GET=view, POST=create, PUT/PATCH=update, DELETE=delete)
   - Resolves resource ID from route parameters (e.g. `{user}`) or path (profile → current user)
   - Extracts **fields accessed**: from request body (create/update) or JSON response (view). Sensitive keys (password, token, secret, etc.) are excluded. Uses `null` when no keys remain (e.g. all sensitive), empty body, or non-JSON response.
   - Logs via `AccessLogService` with IP, user agent, correlation ID, and `fields_accessed` when available.

### Option 2: Use AccessLogService (For Custom Logging)

1. Inject `AccessLogService` in your controller or service.

2. Call `log()` with action, resource type, resource ID, and optional fields accessed:

```php
use App\Services\AccessLogService;

public function __construct(private AccessLogService $accessLog) {}

public function show(User $user): JsonResponse
{
    $this->accessLog->log('view', 'User', $user->id, ['name', 'email']);
    return response()->json($user);
}

public function exportUsers(Request $request): JsonResponse
{
    $this->accessLog->log('export', 'User', null, ['all']);
    // ...
}
```

**Actions:** `view`, `create`, `update`, `delete`, `export`

**Resource types:** `User`, `Setting`, or any model containing PHI.

**Toggle:** HIPAA access logging can be disabled in Configuration > Log retention. When disabled, `AccessLogService::log()` does nothing and “Delete all access logs” is available (with HIPAA violation warning).

## Verification

- [ ] Access logs created when feature is used
- [ ] Resource type and ID are correct
- [ ] Fields accessed array populated (middleware does this automatically for JSON responses and request bodies)
- [ ] User ID and IP captured

## Key Files

- `backend/app/Services/AccessLogService.php`
- `backend/app/Http/Middleware/LogResourceAccess.php`
- `backend/app/Http/Controllers/Api/AccessLogController.php` (index, export, stats; filter by correlation_id)
- `backend/app/Models/AccessLog.php`
- `backend/routes/api.php` (middleware registration, access-logs routes)

## Related

- [Patterns: AccessLogService](../patterns/access-log-service.md)
- [Logging Guide](../../logging.md)

### Implementation Journal

- [Access Logs Field Tracking (2026-01-29)](../../journal/2026-01-29-access-logs-field-tracking.md)
- [HIPAA Logging Toggle (2026-01-29)](../../journal/2026-01-29-hipaa-logging-toggle.md)
- [Live Logs HIPAA Logging (2026-01-29)](../../journal/2026-01-29-live-logs-hipaa-logging.md)
