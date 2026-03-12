# Homepage 401 Redirect Fix - 2026-02-06

## Overview

Fixed a bug where the homepage would flash briefly before redirecting to `/login` for unauthenticated users. The homepage is a public page and should not redirect.

## Root Cause

The global axios 401 response interceptor in `frontend/lib/api.ts` was treating **all** 401 responses as "session expired, redirect to login" — including the auth-check call (`GET /api/auth/user`) made by `AuthInitializer` on every page load. On public pages like the homepage, a 401 from `/auth/user` simply means "not logged in" and is expected behavior.

The sequence was:
1. User visits `/` → homepage renders (flash of content)
2. `AuthInitializer` calls `fetchUser()` → `GET /api/auth/user`
3. Backend returns 401 (not logged in)
4. Axios interceptor fires `window.location.replace("/login")` → hard redirect

## Fix

Added an exclusion for the `/auth/user` endpoint in the 401 interceptor. The `fetchUser()` catch block in `frontend/lib/auth.ts` already handles 401 gracefully by setting `user: null`, so the interceptor redirect was redundant and harmful for this specific endpoint.

```typescript
const requestUrl = error.config?.url || "";
const isAuthCheck = requestUrl === "/auth/user" || requestUrl.endsWith("/auth/user");
if (error.response.status === 401 && !isAuthCheck) { ... }
```

## Key Files

- `frontend/lib/api.ts` — axios interceptor (the fix)
- `frontend/lib/auth.ts` — `fetchUser()` which calls `/auth/user`
- `frontend/components/providers.tsx` — `AuthInitializer` that triggers `fetchUser()` globally
- `frontend/app/page.tsx` — homepage (public page affected by the bug)

## Observations

- The dashboard layout (`frontend/app/(dashboard)/layout.tsx`) has its own independent redirect via `router.push("/login")` in a `useEffect` — this continues to work correctly for protected pages.
- The 401 interceptor still fires for all other API calls (e.g., session expiry while on a dashboard page), preserving the existing UX for authenticated sessions.
