# SSO Callback Page Fix - 2026-02-06

## Overview

Fixed a 404 error that occurred when returning from any SSO provider (Google, GitHub, etc.) after authentication. The root cause was a missing frontend page that the backend redirects to after processing the OAuth callback.

## Root Cause

The backend `SSOController::redirectToFrontend()` always redirects to `/auth/callback?success=true` (or `?error=...`) after an SSO provider authenticates a user. However, the Next.js frontend had no page at `(auth)/callback/page.tsx` to handle this redirect, causing a 404.

This affected ALL SSO providers — Google, GitHub, Microsoft, Apple, Discord, GitLab, and OIDC — since they all share the same callback flow through `SSOController`.

## Implementation Approach

1. **Created `frontend/app/auth/callback/page.tsx`** — A shared SSO callback handler that:
   - Reads query parameters (`success`, `error`, `linked`, `registered`) from the backend redirect
   - On success: calls `fetchUser()` to load the authenticated user from the session, shows a toast, and redirects to `/dashboard`
   - On error: displays a user-friendly error message mapped from backend error codes, with a "Back to Login" button
   - Uses `<Suspense>` boundary around `useSearchParams()` (required by Next.js App Router, matching the pattern in `verify-email` and `reset-password` pages)
   - Checks `useAuth.getState().user` after `fetchUser()` resolves (since `fetchUser` catches errors internally and never rejects)
   - **Critical:** placed at `app/auth/callback/` (NOT inside the `(auth)` route group) because the backend redirects to `/auth/callback`. Next.js route groups `(auth)` strip parentheses from URLs — placing the file at `app/(auth)/callback/` would produce URL `/callback`, not `/auth/callback`.

2. **Updated `frontend/lib/api.ts`** — Added `/callback` to the 401 redirect exclusion list so the callback page can show its own error state instead of being silently redirected to login.

3. **Updated documentation** — SSO recipe, features.md, and context-loading.md all now document the callback page, its role in the flow, and a clear warning not to delete it.

## Challenges Encountered

- **`fetchUser()` never rejects:** The Zustand auth store's `fetchUser()` catches all errors internally and resolves with `user: null`. The callback page couldn't use `.catch()` to detect failures. Solution: check `useAuth.getState().user` after the promise resolves.
- **Missing Suspense boundary:** `useSearchParams()` in Next.js App Router requires a `<Suspense>` boundary. The initial implementation was missing this, which would cause client-side rendering de-optimization. Fixed by following the same wrapper pattern as `verify-email` and `reset-password` pages.
- **Next.js route group gotcha:** Initially placed the file at `app/(auth)/callback/page.tsx`, but `(auth)` is a route group — parentheses are stripped from the URL, making it `/callback` instead of `/auth/callback`. The backend redirects to `/auth/callback`, so this would have still been a 404. Moved to `app/auth/callback/page.tsx` to produce the correct URL.

## Observations

- The SSO backend had a separate session persistence issue discovered after this fix — see [2026-02-06-sso-session-persistence-fix.md](2026-02-06-sso-session-persistence-fix.md). The OAuth callback from Google arrives with an external Referer, so Sanctum's session middleware didn't activate. Fix: moved SSO routes to `web` middleware + cache-based state.
- The SSO backend was correctly implemented — `SSOController` creates a session via `Auth::login()` before redirecting. The only missing piece was the frontend page to receive the redirect.
- The callback page is intentionally provider-agnostic. All providers go through the same `SSOController::callback()` → `redirectToFrontend()` flow. Adding new providers does NOT require modifying the callback page.
- The `ERROR_MESSAGES` map in the callback page covers all error codes that `SSOController` and `SSOService` can produce.

## Files Changed

| File | Change |
|------|--------|
| `frontend/app/auth/callback/page.tsx` | **Created** — SSO callback handler (at `app/auth/`, NOT `app/(auth)/`) |
| `frontend/lib/api.ts` | Added `/callback` to 401 exclusion |
| `docs/ai/context-loading.md` | Added callback page to Auth Work section |
| `docs/ai/recipes/add-sso-provider.md` | Added flow diagram, callback page docs, checklist warnings |
| `docs/features.md` | Added SSO callback flow description |

## Testing Notes

- Test with each SSO provider that is configured (Google at minimum)
- Verify success flow: SSO login → callback page → dashboard redirect with toast
- Verify error flow: disable a provider mid-flow → callback page shows error with "Back to Login"
- Verify new user registration via SSO shows "Account created" toast
- Verify the callback page loading spinner appears briefly before redirect
