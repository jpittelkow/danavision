# Real-Time Streaming Pattern

Real-time data streaming from backend to frontend using Laravel Reverb (WebSocket) and Laravel Echo.

## Frontend: Subscribing to a Channel

### Using `useAppLogStream`

```tsx
const { status, logs, clearLogs } = useAppLogStream(enabled);
// status: "disconnected" | "connecting" | "connected" | "unavailable"
// logs: AppLogEntry[] (max 500, newest first)
```

### Using `useAuditStream`

```tsx
const { status } = useAuditStream(enabled, (log) => {
  // Called for each new audit log entry
  setLogs(prev => [log, ...prev]);
});
```

### Status Handling

```tsx
{status === "unavailable" && <p>Real-time streaming not configured</p>}
{status === "connecting" && <Spinner />}
{status === "connected" && <Badge>Live</Badge>}
```

## Getting the Echo Instance

```typescript
import { getEcho, disconnectEcho } from "@/lib/echo";

// In useEffect (browser only):
const echo = await getEcho(); // null if Reverb not configured
if (!echo) return;

const channel = echo.private("my-channel");
channel.listen(".MyEvent", (data) => { /* handle */ });

// Cleanup:
channel.stopListening(".MyEvent");
echo.leave("my-channel");

// On logout:
disconnectEcho();
```

## Backend: Broadcasting an Event

```php
// 1. Create event class
class AppLogCreated implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('app-logs');
    }

    public function broadcastAs(): string
    {
        return 'AppLogCreated';
    }
}

// 2. Broadcast
broadcast(new AppLogCreated($payload));
```

## Channel Authorization

```php
// routes/channels.php
Broadcast::channel('app-logs', function (User $user) {
    return $user->isAdmin();
});
```

Auth uses Sanctum cookies — the Echo authorizer in `echo.ts` sends `credentials: 'include'` to `/broadcasting/auth`.

## Graceful Degradation

- `getEcho()` returns `null` when `NEXT_PUBLIC_REVERB_APP_KEY` is not set
- Streaming hooks report `status: "unavailable"` — UI works without real-time
- All streaming is opt-in via the `enabled` parameter

**Key files:** `frontend/lib/echo.ts`, `frontend/lib/use-app-log-stream.ts`, `frontend/lib/use-audit-stream.ts`, `backend/config/broadcasting.php`, `backend/config/reverb.php`

**Related:** [ADR-027](../../adr/027-real-time-streaming.md)
