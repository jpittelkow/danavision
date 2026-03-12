# SSO Redirect URI Fix

**Date:** 2026-02-05
**Type:** Bug fix
**Severity:** High (all SSO providers broken when configured via admin UI)

## Problem

All SSO providers returned `Error 400: invalid_request` from the OAuth provider (e.g., Google) when clicking "Continue with [Provider]" on the login page. The "Test Connection" button in the admin SSO settings page passed successfully, masking the bug.

## Root Cause

`ConfigServiceProvider::injectSSOConfig()` injected `client_id` and `client_secret` from DB settings into `config('services.{provider}.*')`, but **never set the `redirect` key**. The `redirect` value in `config/services.php` was sourced solely from env vars (e.g., `GOOGLE_REDIRECT_URI`), which are not set when using the admin UI configuration path.

When Socialite built the OAuth authorization URL, it read `config('services.google.redirect')` which was `null`, causing the OAuth provider to reject the request.

The "Test Connection" button worked because `SSOSettingController::test()` constructs the redirect URI independently from `config('app.url')`, bypassing Socialite entirely.

## Affected Providers

All 7: Google, GitHub, Microsoft, Apple, Discord, GitLab, and OIDC.

## Fix

1. **`ConfigServiceProvider::injectSSOConfig()`** -- Added redirect URI injection inside the provider loop and the OIDC block. The redirect URI is always computed as `{APP_URL}/api/auth/callback/{provider}`.

2. **`config/services.php`** -- Updated all 6 OAuth provider entries to include a sensible default: `env('{PROVIDER}_REDIRECT_URI', env('APP_URL', 'http://localhost') . '/api/auth/callback/{provider}')`. This ensures a value exists even before `ConfigServiceProvider` runs.

## Documentation Updates

- **Recipe (`docs/ai/recipes/add-sso-provider.md`):** Updated Steps 2, 4, and 9 to document that the redirect URI is auto-computed from `APP_URL` and that `{PROVIDER}_REDIRECT_URI` env vars are optional. Updated checklist.
- **Anti-pattern (`docs/ai/anti-patterns/backend.md`):** Added "Don't: Partially Inject Socialite Config" entry warning against injecting only some Socialite keys.

## Files Changed

- `backend/app/Providers/ConfigServiceProvider.php`
- `backend/config/services.php`
- `docs/ai/recipes/add-sso-provider.md`
- `docs/ai/anti-patterns/backend.md`
