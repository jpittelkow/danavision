# ADR-027: Real-Time Streaming (Laravel Reverb)

## Status

Accepted

## Date

2026-03-04

## Context

Several features require real-time updates pushed from the server to the browser: application log streaming for admins, audit log live view, and future notification delivery. Polling is inefficient and introduces latency. We need a WebSocket solution that integrates with Laravel's broadcasting system and works within our single-container Docker architecture.

## Decision

Use **Laravel Reverb** as the WebSocket server with **Laravel Echo** (Pusher protocol) on the frontend.

### Architecture

- **Backend**: Reverb runs as a Supervisor process alongside PHP-FPM and Nginx in the single container. Events are broadcast via Laravel's `broadcast()` helper using the `reverb` connection.
- **Frontend**: `frontend/lib/echo.ts` lazy-loads `laravel-echo` and `pusher-js` to avoid module-level side effects. It creates a singleton Echo instance configured from `NEXT_PUBLIC_REVERB_*` environment variables.
- **Auth**: Private channels authenticate via `POST /broadcasting/auth` using Sanctum session cookies (`credentials: 'include'`).
- **Graceful degradation**: When `NEXT_PUBLIC_REVERB_APP_KEY` is not set, `getEcho()` returns `null` and streaming hooks report status `"unavailable"`. All UI continues to work without real-time updates.

### Channels

| Channel | Event | Consumer | Access |
|---------|-------|----------|--------|
| `private-app-logs` | `AppLogCreated` | `useAppLogStream` | Admin only |
| `private-audit-logs` | `AuditLogCreated` | `useAuditStream` | Admin only |

### Frontend Hooks

- `useAppLogStream(enabled)` — returns `{ status, logs, clearLogs }`. Buffers up to 500 entries in memory.
- `useAuditStream(enabled, onNewLog)` — returns `{ status }`. Calls back on each new audit entry.
- `disconnectEcho()` — called on logout to clean up the WebSocket connection.

### Environment Variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `BROADCAST_CONNECTION` | Laravel broadcast driver | `null` (disabled) |
| `REVERB_APP_KEY` | Reverb application key | — |
| `REVERB_APP_SECRET` | Reverb application secret | — |
| `REVERB_APP_ID` | Reverb application ID | — |
| `REVERB_HOST` | Reverb server hostname | — |
| `REVERB_PORT` | Reverb server port | `6001` |
| `REVERB_SCHEME` | `http` or `https` | `http` |
| `NEXT_PUBLIC_REVERB_APP_KEY` | Frontend — enables Echo | — |
| `NEXT_PUBLIC_REVERB_HOST` | Frontend — WS host | `window.location.hostname` |
| `NEXT_PUBLIC_REVERB_PORT` | Frontend — WS port | `window.location.port` |
| `NEXT_PUBLIC_REVERB_SCHEME` | Frontend — `http`/`https` | auto from protocol |

## Consequences

### Positive

- Real-time updates with sub-second latency
- No polling overhead; connections are persistent
- Reverb is first-party Laravel — tight integration, no external service needed
- Graceful degradation when Reverb is not configured

### Negative

- Additional Supervisor process increases container memory usage
- WebSocket connections are stateful — horizontal scaling requires Redis pub/sub (`REVERB_SCALING_ENABLED`)
- Pusher.js adds ~30KB to the frontend bundle (lazy-loaded to mitigate)

### Neutral

- Channel authorization reuses Sanctum session — no separate auth mechanism
- Reverb config (`backend/config/reverb.php`) uses standard Laravel conventions

## API Documentation

- [Laravel Reverb](https://laravel.com/docs/12.x/reverb)
- [Laravel Broadcasting](https://laravel.com/docs/12.x/broadcasting)
- [Laravel Echo](https://github.com/laravel/echo)

## Related Decisions

- [ADR-009](./009-docker-single-container.md) — single-container architecture (Reverb runs as Supervisor process)
- [ADR-023](./023-audit-logging-system.md) — audit logs streamed via `AuditLogCreated` event

## Notes

- Reverb scaling via Redis is configured but disabled by default (`REVERB_SCALING_ENABLED=false`)
- Max 500 log entries buffered client-side to prevent memory issues
- Echo instance is a singleton — `disconnectEcho()` must be called on logout
