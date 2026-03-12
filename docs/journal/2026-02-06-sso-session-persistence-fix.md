# SSO Session Persistence Fix - 2026-02-06

## Overview

Fixed a `state_not_found` error that occurred during OAuth SSO authentication (Google and all other providers). The OAuth state token stored during the redirect step was not available on the callback step, causing authentication to fail.

## Root Cause

The SSO flow involves two HTTP requests to the backend:

1. **Redirect** (`GET /api/auth/sso/google`) — Stores a CSRF state token, redirects browser to Google.
2. **Callback** (`GET /api/auth/callback/google`) — Google redirects back; backend validates the state token.

Both routes were in `routes/api.php`. Laravel API routes use the `api` middleware group, which includes Sanctum's `EnsureFrontendRequestsAreStateful`. This middleware conditionally adds session/cookie handling **only when the request's Referer matches a stateful domain**.

- **Redirect request**: Referer is `sourdough.303digital.com` (the app) → Sanctum recognizes it as stateful → full session middleware runs (EncryptCookies + StartSession) → state token stored in session with an encrypted cookie.
- **Callback request**: Referer is `accounts.google.com` (external provider) → Sanctum does NOT recognize it as stateful → EncryptCookies does NOT run → the encrypted session cookie from step 1 can't be decrypted → a new empty session is created → state token not found.

Additionally, there was an explicit `StartSession` middleware appended to the API middleware group in `bootstrap/app.php` as a band-aid fix. However, `StartSession` without `EncryptCookies` runs in the wrong order — it starts a new session but can't read the encrypted cookie from the redirect step.

A secondary issue: Socialite's built-in state management conflicted with the custom state handling. Socialite stores its own `state` in the session during `redirect()` and validates it during `user()`. Since the custom code overrides the `state` URL parameter via `->with(['state' => $fullState])`, Socialite's internal state check would always fail with an `InvalidStateException` (the URL has the custom state, but the session has Socialite's state).

## Fix (Three-Part)

### 1. Moved SSO browser routes to `web` middleware (`routes/web.php`)

The SSO redirect and callback routes are browser navigation routes (not AJAX API calls). They need full session/cookie handling on both legs of the OAuth flow. The `web` middleware group always includes `EncryptCookies` + `AddQueuedCookiesToResponse` + `StartSession`, regardless of Referer.

Routes are defined with an `/api/auth` prefix to match existing frontend URLs and nginx routing. A regex constraint on `{provider}` excludes `'providers'` to avoid shadowing the `GET /api/auth/sso/providers` JSON endpoint in `api.php`.

### 2. Switched state storage from session to Cache

State tokens are now stored in `Cache` (not `session()`):
- `Cache::put("sso_state:{$stateToken}", $provider, 600)` — 10-minute TTL
- `Cache::pull("sso_state:{$receivedToken}")` — one-time use, prevents replay attacks
- Provider name is stored as the value for cross-provider validation

Cache-based storage is more robust because:
- No dependency on session cookies or Referer headers
- Works regardless of Sanctum's stateful domain configuration
- Self-cleaning via TTL expiry

### 3. Used Socialite's `stateless()` mode

Both `getRedirectUrl()` and `getSocialUser()` now use `->stateless()`:
- **On redirect**: Prevents Socialite from storing its own state in the session (avoids conflict with custom state)
- **On callback**: Prevents Socialite from checking session-stored state (we validate state ourselves via cache)

Custom state is still passed to the OAuth provider via `->with(['state' => $fullState])`, which works in stateless mode.

### 4. Cleaned up `bootstrap/app.php`

Removed the explicit `StartSession` and `ShareErrorsFromSession` from the API middleware group. These were a band-aid for the SSO session issue and were actually harmful:
- For stateful requests: Duplicated session handling (Sanctum already starts sessions)
- For non-stateful requests: Started sessions without `EncryptCookies`, meaning cookies couldn't be properly encrypted/decrypted

## Observations

- This bug affected ALL SSO providers (Google, GitHub, Microsoft, Apple, Discord, GitLab, OIDC) since they all use the same `SSOController::callback()` flow.
- The issue only manifested in production (where the domain is `sourdough.303digital.com`) or any non-localhost deployment. On localhost, Sanctum's default stateful domains include `localhost`, so the redirect and callback might both be recognized as stateful if the browser sends a localhost Referer.
- The `web` middleware's `VerifyCsrfToken` is not a concern for these routes because they are GET requests — Laravel only verifies CSRF tokens on POST/PUT/PATCH/DELETE requests. The routes are also explicitly excluded in `bootstrap/app.php` for safety.

## Files Changed

| File | Change |
|------|--------|
| `backend/routes/web.php` | Added SSO redirect + callback routes with `/api/auth` prefix and `web` middleware |
| `backend/routes/api.php` | Removed SSO redirect + callback routes (kept link/unlink/providers) |
| `backend/app/Services/Auth/SSOService.php` | Switched state storage from `session()` to `Cache`; added `stateless()` to Socialite driver |
| `backend/bootstrap/app.php` | Removed explicit `StartSession`/`ShareErrorsFromSession` from API middleware; added SSO routes to CSRF exceptions |
| `docs/ai/recipes/add-sso-provider.md` | Updated flow diagram (cache, stateless, web middleware); updated key files table |
| `docs/journal/2026-02-06-sso-session-persistence-fix.md` | This journal entry |

## Testing Notes

- Test SSO login with each configured provider (Google at minimum)
- Verify: Click "Continue with Google" → Google auth → redirect to `/auth/callback?success=true` → dashboard with toast
- Verify: Session persists after SSO login (refresh page, user stays logged in)
- Verify: `GET /api/auth/sso/providers` still returns the provider list (not caught by wildcard route)
- Verify: Error states (disabled provider, expired state) show proper error messages
- Test from a non-localhost domain to confirm the fix works in production
