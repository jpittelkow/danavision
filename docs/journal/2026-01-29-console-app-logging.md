# Console and Application Logging - 2026-01-29

## Overview

Completed the remaining Console/Application Logging items from the Audit Logs roadmap: backend logging audit and enhancements, per-environment log levels, log rotation, frontend error reporting, and documentation (standards guide, patterns, recipe, global components).

## Implementation Approach

### Backend Logging

- **LLMOrchestrator**: Log::info on successful query and provider test; Log::warning on query failure, provider test failure, and aggregation/council provider failures.
- **EmailChannel**: try/catch around send; Log::info on success, Log::error on failure.
- **BackupService**: Log::info for backup start/created; Log::warning for restore start/complete; Log::error for restore failures.
- **ScheduledBackup**: Log::info at job start and on completion (with duration_ms).
- **AuthController**: Log::info for password reset requested; Log::warning for password reset failure.

### Configuration

- **LOG_LEVEL**: Documented in .env.example (debug/info/warning by environment).
- **LOG_DAILY_DAYS**: Added (default 14); daily channel uses AddContextProcessorTap for correlation_id, user_id, etc.

### Frontend Error Reporting

- **errorLogger** (`frontend/lib/error-logger.ts`): report(error, context?), captureMessage(message, level?, context?); sends to POST /api/client-errors via fetch (no api client to avoid circular dependency). Correlation ID stored from API response headers and sent with reports.
- **ClientErrorController**: Validates payload (message, stack, component_stack, url, user_agent, level, context); logs via Log::info/warning/error with client_error context; rate limit throttle:10,1.
- **ErrorBoundary**: Reports via errorLogger.report() with componentStack in context (sent as component_stack in payload).
- **ErrorHandlerSetup**: window.onerror and unhandledrejection handlers report via errorLogger.
- **Providers**: App wrapped in ErrorBoundary; ErrorHandlerSetup mounted inside.
- Replaced ad-hoc console.error/console.warn with errorLogger in backup, notifications, ai, user/preferences, use-page-title, sso-buttons, user/security, configuration/security pages.

### Documentation

- **docs/logging.md**: When to log, levels, backend config, frontend errorLogger, key files.
- **docs/ai/patterns.md**: Logging Pattern section with examples.
- **docs/ai/context-loading.md**: Application Logging Work section.
- **docs/ai/recipes/extend-logging.md**: Add logging to backend/frontend, add log channel, verification.
- **.cursor/rules/global-components.mdc**: errorLogger in utilities table and enforcement checklist.

## Challenges Encountered

- **Circular dependency**: error-logger.ts must not import api.ts (api imports setCorrelationId from error-logger). Client errors are sent via fetch() directly.
- **component_stack**: Error boundary passes componentStack in context; report() now extracts it and sends as payload.component_stack so the backend receives it in the validated field.

## Observations

- Correlation ID from API responses is stored and sent with client error reports so backend logs can correlate request and client error.
- Rate limit (10/minute) on client-errors prevents abuse while allowing legitimate reporting.

## Trade-offs

- Client error context is not size-limited in validation; rate limit and typical usage keep risk low.
- Logging standards are documented in docs/logging.md and a recipe; no separate ADR (extends existing audit/logging design).

## Next Steps (Future Considerations)

- Optional: add size/depth limits on client error context in ClientErrorController.
- Alerting for suspicious activity patterns remains on the roadmap.

## Testing Notes

- Trigger a client error (e.g. force a failed API call); confirm POST /api/client-errors and backend log entry with correlation_id.
- Trigger React error boundary; confirm component_stack in log.
- Set LOG_STACK=daily; confirm log rotation and LOG_DAILY_DAYS retention.
