# Logging Pattern

Use the Laravel `Log` facade for operational and diagnostic events (not user actions; use AuditService for those). Every log record gets `correlation_id`, `user_id`, `ip_address`, and `request_uri` from the context processor when in HTTP context.

## Usage

```php
use Illuminate\Support\Facades\Log;

// Success / operational events
Log::info('Backup created', ['filename' => $filename, 'size' => $size]);
Log::info('Email sent', ['user_id' => $user->id, 'to' => $user->email]);

// Recoverable issues
Log::warning('LLM provider failed', ['provider' => $name, 'error' => $e->getMessage()]);

// Failures
Log::error('Backup restore failed', ['error' => $e->getMessage()]);
```

- **Levels**: Use `info` for normal operations, `warning` for recoverable issues, `error` for failures. Use `debug` only for development.
- **Context**: Always pass a structured array (ids, names, duration_ms). Do not log secrets or full request bodies.
- **Frontend**: Use `errorLogger` from `frontend/lib/error-logger.ts` instead of `console.error`/`console.warn` so client errors are sent to `POST /api/client-errors` and appear in backend logs with correlation ID.

**Key files:** `backend/config/logging.php`, `backend/app/Logging/AddContextProcessorTap.php` (tap class), `backend/app/Logging/ContextProcessor.php`, `backend/app/Http/Middleware/AddCorrelationId.php`, `frontend/lib/error-logger.ts`, `docs/logging.md`

**Related:** [Recipe: Extend logging](../recipes/extend-logging.md), [Audit Service](audit-service.md), [Access Log Service](access-log-service.md)

## Implementation Journal

- [Console App Logging (2026-01-29)](../../journal/2026-01-29-console-app-logging.md)
- [Live Logs HIPAA Logging (2026-01-29)](../../journal/2026-01-29-live-logs-hipaa-logging.md)
