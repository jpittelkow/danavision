# API Calls Pattern

The `api` client, error message extraction, and online/offline state hooks.

## API Utility

The `api` client uses axios with built-in interceptors for error handling, auth redirects, correlation ID tracking, and offline request queuing.

```typescript
import { api } from "@/lib/api";

// GET request
const response = await api.get<{ data: Example[] }>("/examples");

// POST request
await api.post("/examples", { name: "New Example" });

// PUT request
await api.put(`/examples/${id}`, { name: "Updated" });

// DELETE request
await api.delete(`/examples/${id}`);

// With query params
await api.get("/examples", { params: { page: 2, per_page: 20 } });

// File download (blob)
const response = await api.get(`/backup/download/${filename}`, { responseType: "blob" });
```

Key features:
- `withCredentials: true` ensures Sanctum session cookies are sent
- Interceptors automatically redirect to `/login` on 401
- Network errors on mutations (POST/PUT/PATCH/DELETE) are queued for retry when back online
- Correlation ID from response headers is captured for error logging

## Error Message Extraction

```tsx
// In catch blocks
catch (err: unknown) {
  const msg = err instanceof Error ? err.message : null;
  toast.error(msg ?? "Failed to save settings");
}

// For axios errors with response data
catch (error: unknown) {
  const err = error as Error & { response?: { data?: { message?: string } } };
  toast.error(err.message || err.response?.data?.message || "Operation failed");
}
```

The `api` interceptor already extracts `response.data.message` and throws it as an `Error`, so `err.message` is usually sufficient.

## useOnline Hook

Reactive online/offline state:

```tsx
import { useOnline } from "@/lib/use-online";

const { isOnline, isOffline } = useOnline();

<Button disabled={isOffline}>Submit</Button>
{isOffline && <Badge variant="secondary">Offline</Badge>}
```

**Key files:** `frontend/lib/api.ts`, `frontend/lib/use-online.ts`

**Related:** [Error Handling](error-handling.md)
