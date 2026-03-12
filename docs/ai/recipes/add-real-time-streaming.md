# Recipe: Add Real-Time Streaming to a Page

Add live-updating data to a page using Laravel Reverb (WebSocket) and Laravel Echo.

## Prerequisites

- Reverb configured (`BROADCAST_CONNECTION=reverb` + `REVERB_*` env vars)
- `NEXT_PUBLIC_REVERB_APP_KEY` set for frontend

## Files to Create/Modify

| File | Action | Purpose |
|------|--------|---------|
| `backend/app/Events/{Name}Event.php` | Create | Broadcastable event |
| `backend/routes/channels.php` | Modify | Channel authorization |
| `frontend/lib/use-{name}-stream.ts` | Create | React hook for consuming the stream |
| Page component | Modify | Use the stream hook |

## Steps

### 1. Create Backend Event

```php
<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MyDataCreated implements ShouldBroadcast
{
    public function __construct(
        public readonly array $data
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('my-data');
    }

    public function broadcastAs(): string
    {
        return 'MyDataCreated';
    }
}
```

### 2. Authorize the Channel

```php
// backend/routes/channels.php
Broadcast::channel('my-data', function (User $user) {
    return $user->isAdmin(); // or any authorization logic
});
```

### 3. Broadcast the Event

```php
broadcast(new MyDataCreated($payload));
```

### 4. Create Frontend Hook

Follow the pattern from `frontend/lib/use-app-log-stream.ts`:

```typescript
"use client";
import { useEffect, useState } from "react";
import { getEcho } from "@/lib/echo";

export type StreamStatus = "disconnected" | "connecting" | "connected" | "unavailable";

export function useMyDataStream(enabled: boolean, onNewItem: (item: MyData) => void) {
  const [status, setStatus] = useState<StreamStatus>("disconnected");

  useEffect(() => {
    if (!enabled || typeof window === "undefined") return;
    let cancelled = false;
    let cleanup: (() => void) | undefined;

    setStatus("connecting");
    getEcho().then((echo) => {
      if (cancelled) { setStatus("disconnected"); return; }
      if (!echo) { setStatus("unavailable"); return; }

      const channel = echo.private("my-data");
      channel.listen(".MyDataCreated", (payload: unknown) => {
        onNewItem(payload as MyData);
      });
      setStatus("connected");

      cleanup = () => {
        channel.stopListening(".MyDataCreated");
        echo.leave("my-data");
        setStatus("disconnected");
      };
    });

    return () => { cancelled = true; cleanup?.(); };
  }, [enabled]);

  return { status };
}
```

### 5. Use in Page Component

```tsx
const { status } = useMyDataStream(isAdmin, (item) => {
  setItems(prev => [item, ...prev]);
});

return (
  <>
    {status === "connected" && <Badge variant="outline">Live</Badge>}
    {status === "unavailable" && <p className="text-sm text-muted-foreground">Real-time not available</p>}
    {/* render items */}
  </>
);
```

## Checklist

- [ ] Backend event implements `ShouldBroadcast`
- [ ] Channel authorized in `routes/channels.php`
- [ ] Frontend hook handles all 4 statuses
- [ ] Cleanup stops listener and leaves channel
- [ ] Graceful degradation when Reverb not configured
- [ ] `useRef` for callback to avoid re-subscribing on callback changes

## Reference Implementations

- **App log streaming**: `frontend/lib/use-app-log-stream.ts` (buffers entries in state)
- **Audit log streaming**: `frontend/lib/use-audit-stream.ts` (callback pattern)
- **Echo singleton**: `frontend/lib/echo.ts`

**Related:** [ADR-027](../../adr/027-real-time-streaming.md), [Pattern: Real-Time Streaming](../patterns/real-time-streaming.md)
