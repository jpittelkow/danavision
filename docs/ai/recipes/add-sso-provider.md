# Recipe: Add SSO Provider

Step-by-step guide to add a new OAuth2/OIDC SSO provider to Sourdough.

## Architecture Overview

SSO uses **Laravel Socialite** for OAuth flows. Provider credentials and options are stored in the database (with env fallback) and injected at boot via `ConfigServiceProvider`. The frontend login page shows buttons for each enabled provider.

```
┌─────────────────────────────────────────────────────────────┐
│ Configuration > SSO (Admin)                                  │
│   - Global: enabled, allow_linking, auto_register             │
│   - Per-provider: client_id, client_secret (from DB or env)   │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ ConfigServiceProvider::injectSSOConfig()                     │
│   - Injects config('sso.*') and config('services.{provider}.*') │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ SSOService + Socialite::driver($provider)                    │
│   - Redirect, callback, link/unlink                          │
└─────────────────────────────────────────────────────────────┘
```

### Complete SSO Authentication Flow

Understanding the full flow is critical for debugging SSO issues:

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   Frontend        │     │   Backend         │     │   OAuth Provider  │
│   (Next.js)       │     │   (Laravel)       │     │   (Google, etc.)  │
└────────┬─────────┘     └────────┬─────────┘     └────────┬─────────┘
         │                        │                         │
    1. Click "Continue             │                         │
       with Google"               │                         │
         │                        │                         │
    2. window.location.href =     │                         │
       /api/auth/sso/google       │                         │
         │───────────────────────>│                         │
         │                   3. SSOController::redirect()   │
         │                      Store state in CACHE        │
         │                      (Socialite stateless mode)  │
         │                      302 redirect ──────────────>│
         │                        │                    4. User logs in
         │                        │                    at Google
         │                        │                         │
         │                        │<─────── 5. Redirect to  │
         │                        │    /api/auth/callback/google
         │                   6. SSOController::callback()   │
         │                      - Validate state from CACHE │
         │                      - Exchange code for user    │
         │                      - Create/find user          │
         │                      - Auth::login() (session)   │
         │<───────────────── 7. 302 redirect to             │
         │                   /auth/callback?success=true    │
         │                        │                         │
    8. frontend/app/auth/         │                         │
       callback/page.tsx          │                         │
       - fetchUser()──────────────>│                         │
         │<──────── 9. Return user│                         │
   10. router.replace("/dashboard")                         │
         │                        │                         │
```

> **CRITICAL — SSO routes use `web` middleware, not `api`:**
> The SSO redirect and callback routes are defined in `routes/web.php` (with
> an `/api/auth` prefix), NOT in `routes/api.php`. This is because the OAuth
> callback from Google/GitHub/etc. has a Referer from the external provider,
> and Sanctum's `EnsureFrontendRequestsAreStateful` won't activate session
> middleware for external Referers. The `web` middleware group always includes
> `EncryptCookies` + `StartSession`, ensuring sessions work on both legs of
> the OAuth flow. See `routes/web.php` comments for full explanation.
>
> **State tokens use Cache, not sessions:**
> OAuth state tokens are stored in `Cache` (not `session()`) for robustness.
> Socialite is used in `stateless()` mode to prevent session conflicts.
> See `SSOService::getRedirectUrl()` and `SSOService::validateStateToken()`.

> **IMPORTANT — DO NOT DELETE, RENAME, OR MOVE the frontend callback page:**
> The file `frontend/app/auth/callback/page.tsx` is the **shared SSO callback handler**
> for ALL providers. Every SSO provider (Google, GitHub, Microsoft, Apple, Discord, GitLab,
> OIDC) redirects to this same page after authentication. The backend `SSOController::redirectToFrontend()`
> always redirects to `/auth/callback?...` regardless of which provider was used.
> Removing this file will cause a **404 error** after any SSO provider authentication.
>
> **File location is critical:** This file is at `app/auth/callback/` — NOT inside the
> `app/(auth)/` route group. In Next.js, parenthesized directory names like `(auth)` are
> **route groups** that do NOT affect the URL. Placing the file inside `(auth)` would make
> the URL `/callback` instead of `/auth/callback`, causing a 404.
>
> **When adding a new SSO provider, you do NOT need to modify the callback page.**
> It is provider-agnostic by design.

### Key Files in the SSO Flow

| File | Role |
|------|------|
| `frontend/components/auth/sso-buttons.tsx` | Renders "Continue with {provider}" buttons; navigates to `/api/auth/sso/{provider}` |
| `backend/routes/web.php` | **SSO browser routes** (redirect + callback) — uses `web` middleware for proper session/cookie handling. DO NOT move to `api.php`. |
| `backend/app/Http/Controllers/Api/SSOController.php` | `redirect()` → provider; `callback()` → processes OAuth, redirects to frontend |
| `backend/app/Services/Auth/SSOService.php` | Provider-agnostic OAuth logic (cache-based state tokens, stateless Socialite, user creation, account linking) |
| `frontend/app/auth/callback/page.tsx` | **Shared callback handler** — fetches user session and redirects to dashboard (or shows error). At `app/auth/` (not `app/(auth)/`) so URL is `/auth/callback`. |
| `backend/app/Providers/ConfigServiceProvider.php` | Injects SSO credentials from DB into Socialite config at boot |

## Files to Create/Modify

### Backend

| File | Action | Purpose |
|------|--------|---------|
| `backend/config/settings-schema.php` | Modify | Add provider keys to `sso` group |
| `backend/app/Providers/ConfigServiceProvider.php` | Modify | Add provider to `injectSSOConfig()` |
| `backend/config/sso.php` | Modify | Add provider to `providers` array |
| `backend/config/services.php` | Modify | Add Socialite credentials (env fallback) |
| `backend/app/Http/Controllers/Api/SSOSettingController.php` | Modify | Add validation rules for new keys |
| `backend/.env.example` | Modify | Add env variables |

### Frontend

| File | Action | Purpose |
|------|--------|---------|
| `frontend/components/provider-icons.tsx` | Modify | Add provider icon to the shared icon map so **sign-in, register, and SSO config** show the correct icon |
| `frontend/app/(dashboard)/configuration/sso/page.tsx` | Modify | Add provider card and form fields on the **SSO setup page** (Configuration > SSO) |
| `frontend/app/auth/callback/page.tsx` | **DO NOT MODIFY or MOVE** | Shared SSO callback handler for all providers. Provider-agnostic by design. Must be at `app/auth/callback/` (NOT inside `(auth)` route group) so the URL is `/auth/callback`. |

### Optional (custom Socialite driver)

| File | Action | Purpose |
|------|--------|---------|
| `backend/config/services.php` | Modify | Register custom driver config |
| Composer | Add package | e.g. `socialiteproviders/xxx` if not in Laravel Socialite core |

## Step 1: Add to Settings Schema

Add the provider's **enabled** flag, **test_passed** flag, and client credentials to the `sso` group in `backend/config/settings-schema.php`:

```php
'sso' => [
    // ... existing keys ...
    'example_enabled' => ['env' => null, 'default' => true],
    'example_test_passed' => ['env' => null, 'default' => false],
    'example_client_id' => ['env' => 'EXAMPLE_CLIENT_ID', 'default' => null],
    'example_client_secret' => ['env' => 'EXAMPLE_CLIENT_SECRET', 'default' => null, 'encrypted' => true],
],
```

- **`{provider}_enabled`**: Per-provider toggle. A provider appears on the login page only when it has credentials, has **passed** the connection test, and `{provider}_enabled` is true.
- **`{provider}_test_passed`**: Set to `true` only when "Test connection" succeeds (credentials validated at the provider's token endpoint). Cleared when credentials are changed. Not editable by the client; only the test endpoint sets it.
- Use a short provider slug (e.g. `google`, `github`, `example`) as the key prefix. Secrets must have `'encrypted' => true`.

## Step 2: Add to ConfigServiceProvider

In `backend/app/Providers/ConfigServiceProvider.php`, update `injectSSOConfig()`:

1. Add the provider slug to the `$providers` array if it uses the same pattern (client_id + client_secret):

```php
$providers = ['google', 'github', 'microsoft', 'apple', 'discord', 'gitlab', 'example'];
```

2. The loop automatically handles three things for each provider in `$providers`:
   - Injects `client_id` and `client_secret` from DB settings into `config('services.{provider}.*')`
   - **Sets the redirect URI** from `APP_URL`: `config('services.{provider}.redirect')` is always set to `{APP_URL}/api/auth/callback/{provider}`. This is why `{PROVIDER}_REDIRECT_URI` env vars are **optional** -- the redirect URI is auto-computed.
   - Sets `sso.providers.{provider}.enabled` from both credentials and the explicit `{provider}_enabled` flag: `$hasCredentials && ($settings[$provider . '_enabled'] ?? true)`. Do not set enabled from credentials alone.

3. If the provider has custom config (e.g. OIDC with issuer_url), add a dedicated block after the loop and set `sso.providers.{provider}.enabled` as `$hasCredentials && ($settings['oidc_enabled'] ?? true)` (or the provider's enabled key). **You must also set the redirect URI** in the custom block: `config(['services.{provider}.redirect' => rtrim(config('app.url'), '/') . '/api/auth/callback/{provider}'])`.

> **Important:** All three Socialite config keys (`client_id`, `client_secret`, `redirect`) must be injected by `ConfigServiceProvider`. If you only inject `client_id` and `client_secret` but leave `redirect` dependent on env vars, SSO will fail with `400: invalid_request` when configured via the admin UI.

## Step 3: Add to SSO Config

In `backend/config/sso.php`, add the provider to the `providers` array:

```php
'providers' => [
    // ... existing providers ...
    'example' => [
        'name' => 'Example',
        'icon' => 'key',  // or a Lucide icon name used on the frontend
        'enabled' => !empty(env('EXAMPLE_CLIENT_ID')),
        'color' => '#6B7280',
    ],
],
```

The `enabled` value from env is overridden at runtime by `ConfigServiceProvider` when DB settings exist.

## Step 4: Add to Services Config (Socialite)

In `backend/config/services.php`, add the provider so Socialite can read credentials (env fallback when DB not used):

```php
'example' => [
    'client_id' => env('EXAMPLE_CLIENT_ID'),
    'client_secret' => env('EXAMPLE_CLIENT_SECRET'),
    'redirect' => env('EXAMPLE_REDIRECT_URI', env('APP_URL', 'http://localhost') . '/api/auth/callback/example'),
],
```

The `redirect` key defaults to `{APP_URL}/api/auth/callback/{provider}` so it works out of the box. `ConfigServiceProvider::injectSSOConfig()` overrides this at runtime to ensure consistency. The `EXAMPLE_REDIRECT_URI` env var is **optional** and only needed if you want to override the auto-computed value. Document the callback URL in the provider's developer console and in the SSO setup modal.

## Step 5: Add Socialite Driver (if custom)

If the provider is **not** included in Laravel Socialite's built-in drivers, install a community package (e.g. Socialite Providers) and register the driver in `AppServiceProvider` or a dedicated service provider. See [Laravel Socialite](https://laravel.com/docs/socialite) and [Socialite Providers](https://socialiteproviders.com/).

For built-in drivers (google, github, microsoft, etc.), no code change is needed beyond config.

## Step 6: Add to SSOSettingController Validation and Test

In `backend/app/Http/Controllers/Api/SSOSettingController.php`:

1. **Validation:** Add validation rules for the new keys in the `update()` method. Do **not** validate or accept `{provider}_test_passed` from the request—it is set only by the test endpoint and cleared when credentials change.

```php
'example_enabled' => ['sometimes', 'boolean'],
'example_client_id' => ['sometimes', 'nullable', 'string', 'max:500'],
'example_client_secret' => ['sometimes', 'nullable', 'string', 'max:1000'],
```

2. **Test endpoint:** Add the provider to the allowed list in `test()` (e.g. `$allowed = ['google', 'github', ..., 'example']`). The test **must validate credentials** at the provider's token endpoint, not just check URL reachability:

   - **Credential validation:** POST to the provider's token endpoint with `grant_type=authorization_code`, `code=test_connection_validation`, `client_id`, `client_secret`, and `redirect_uri` (from `config('app.url')` + `/api/auth/callback/{provider}`). Interpret the response:
     - **invalid_client** or HTTP 401 → credentials invalid; do **not** set `{provider}_test_passed`.
     - **invalid_grant**, **invalid_request**, or **bad_verification_code** (GitHub) → credentials accepted; set `{provider}_test_passed = true`.
   - **Require client_secret** for the test; return 422 if missing.
   - For **OIDC:** Fetch discovery, get `token_endpoint`, then validate credentials as above.
   - For **OAuth with discovery** (e.g. Google, Microsoft): Fetch discovery, get `token_endpoint`, then validate credentials.
   - For **OAuth without discovery:** Add the provider's token URL to the `$tokenEndpoints` array in `test()` and use `validateCredentialsAtTokenEndpoint()`.
   - **Apple:** Sign in with Apple uses a JWT client secret; credential validation is not implemented; return a clear message that manual sign-in test is required.

3. **update() logic:** The controller already forces `enabled = false` when credentials are missing or when `{provider}_test_passed` is false, and clears `{provider}_test_passed` when credentials change. No extra work for a new provider if you add the keys to the schema and test logic as above.

## Step 7: Add Sign-In and Config Page Icon

Icons are centralized in **`frontend/components/provider-icons.tsx`**. SSO buttons (`sso-buttons.tsx`) and the SSO config page both use `ProviderIcon` from this file. Add your provider's icon so sign-in, register, and Configuration > SSO show the correct icon (not the generic `key` icon).

### File: `frontend/components/provider-icons.tsx`

1. Add your provider to the `ProviderIconId` type (optional; `string` is accepted).
2. Add an entry to **`SSO_ICONS`** (or `ALL_ICONS` if you prefer a single map) with the same key as `config/sso.php` (e.g. `example`).

### Icon Requirements

- **Format:** SVG path data (inline, not external file). Use the `renderSvg(className, ...)` helper so the SVG uses `fill="currentColor"` and the given `className` for size.
- **ViewBox:** 24x24 (standard). The helper uses `viewBox="0 0 24 24"` by default.
- **Style:** Monochrome using `currentColor` so the icon adapts to the theme. Do not use hardcoded fill colors.
- **Size presets:** The component supports `sm` (16px), `md` (20px), and `lg` (24px) via the `size` prop.

### Example

```tsx
// In SSO_ICONS (or ALL_ICONS), add:
example: (className) =>
  renderSvg(
    className,
    <path d="M... your SVG path from official brand assets ..." />
  ),
```

### Dark/Light Theme Compatibility

Icons use `currentColor` for fill, so they automatically follow the theme's text color. Do not use hardcoded colors in the SVG; this ensures the icon is readable in both light and dark mode.

Use the same key as `config/sso.php` (e.g. `example`). The `icon` value returned by `GET /auth/sso/providers` comes from `config/sso.php`; unknown keys fall back to the `key` icon. See [Patterns: ProviderIcon](../patterns/ui-patterns.md#providericon) and the inline comments in `provider-icons.tsx`.

## Step 8: Add SSO Setup Page Form

In `frontend/app/(dashboard)/configuration/sso/page.tsx` (the **SSO setup page** at Configuration > SSO):

1. Add the provider to the `providers` array (include `enabledKey` and `testPassedKey`):

```ts
const providers = [
  // ... existing ...
  {
    id: "example",
    label: "Example",
    clientIdKey: "example_client_id",
    clientSecretKey: "example_client_secret",
    enabledKey: "example_enabled",
    testPassedKey: "example_test_passed",
  },
];
```

2. Add the keys to the Zod schema and default values: `example_enabled: z.boolean()`, `example_test_passed: z.boolean().optional()`, and map them in `fetchSettings` (including `example_test_passed` from API).

3. **Enable toggle and status:** The "Enable on login page" toggle is shown only when **configured and test passed** (`configured && testPassed`). Status badge: Not configured → Test required → Test passed / Enabled. Use `testPassedKey` from the providers array.

4. **Test connection button:** Enable the button only when both **Client ID and Client secret** are filled (`canTest = configured && hasSecret`). Disable with tooltip "Enter client secret to test credentials" when secret is empty. On success, refetch settings so `test_passed` updates and the Enable toggle appears.

5. **Setup instructions modal:** In `frontend/components/admin/sso-setup-modal.tsx`, add the provider to `SSOSetupProviderId` and to `PROVIDER_CONTENT` with: `title`, `consoleUrl`, optional `docUrl`, `steps` (array of strings), `scopes` (array), optional `notes`.

6. **Backend test:** Ensure the new provider is in the controller's allowed list and implements **credential validation** at the token endpoint (see Step 6). The test must not pass with incorrect credentials.

The existing loop over `providers` will render a card with status, enabled toggle (when test passed), redirect URI, setup instructions button, test button, and per-provider save button automatically. Each provider saves only its own keys via `PUT /sso-settings` (partial payload).

## Step 9: Add Environment Variables

In `backend/.env.example` (and root `.env.example` if present):

```env
# Example SSO Provider
EXAMPLE_CLIENT_ID=
EXAMPLE_CLIENT_SECRET=
# EXAMPLE_REDIRECT_URI=  # Optional: auto-computed from APP_URL as {APP_URL}/api/auth/callback/example
```

## Checklist

### Backend

- [ ] Settings schema: `example_enabled`, `example_test_passed`, `example_client_id`, and `example_client_secret` in `sso` group (secret `encrypted => true`)
- [ ] ConfigServiceProvider: provider included in `injectSSOConfig()` `$providers` array (injects `client_id`, `client_secret`, **and `redirect`** from `APP_URL`; sets enabled = hasCredentials && explicit `{provider}_enabled`)
- [ ] `config/sso.php`: provider entry with name, icon, enabled, color
- [ ] `config/services.php`: provider entry with client_id, client_secret, redirect (with `APP_URL` default)
- [ ] SSOSettingController: validation rules for new keys (including `example_enabled`); **do not** validate `example_test_passed` from request
- [ ] SSOSettingController test(): provider in allowed list; **credential validation** at token endpoint (invalid_client = fail, invalid_grant/invalid_request = pass); require client_secret; set `example_test_passed = true` only on success
- [ ] If custom driver: Socialite provider package installed and registered
- [ ] `.env.example`: EXAMPLE_CLIENT_ID, EXAMPLE_CLIENT_SECRET (redirect URI is optional, auto-computed from APP_URL)

### Frontend

- [ ] **Sign-in/register and SSO config:** Provider icon added to `frontend/components/provider-icons.tsx` (key must match `config/sso.php` icon)
- [ ] **SSO setup page:** Provider added to `providers` array (with `enabledKey` and `testPassedKey`) in `frontend/app/(dashboard)/configuration/sso/page.tsx`; schema and defaultValues include `example_enabled` and `example_test_passed`; Enable toggle only when configured and test passed; Test button only when client_id and client_secret filled; refetch after successful test
- [ ] **Setup modal:** Provider added to `SSOSetupModal` in `frontend/components/admin/sso-setup-modal.tsx` (provider id + content: steps, console URL, doc URL, scopes)

### Shared (DO NOT MODIFY when adding a provider)

- [ ] **SSO callback page exists:** `frontend/app/auth/callback/page.tsx` — handles the redirect from backend after ALL providers. Do not modify, rename, move, or delete this file. It must be at `app/auth/` (NOT `app/(auth)/`) so the URL is `/auth/callback`.

### Testing

- [ ] Admin can set client ID/secret in Configuration > SSO (setup page)
- [ ] **Test connection** requires both client ID and client secret; validates credentials at token endpoint (fails with wrong credentials, passes with correct credentials)
- [ ] Enable toggle appears only after a successful test; provider appears on login only when credentials + test passed + enabled
- [ ] Provider appears with correct icon on **sign-in and register pages** when credentials, test passed, and enabled (buttons come from `GET /auth/sso/providers`)
- [ ] Redirect to provider and callback complete successfully (no 404 on return)
- [ ] New user can register via SSO; existing user can link account (if allow_linking enabled)
- [ ] Error cases show user-friendly messages on the callback page (e.g., invalid state, provider disabled)

## Existing Providers for Reference

| Provider | Schema keys | Socialite driver |
|----------|-------------|------------------|
| Google | google_enabled, google_test_passed, google_client_* | google |
| GitHub | github_enabled, github_test_passed, github_client_* | github |
| Microsoft | microsoft_enabled, microsoft_test_passed, microsoft_client_* | microsoft |
| Apple | apple_enabled, apple_test_passed, apple_client_* | apple |
| Discord | discord_enabled, discord_test_passed, discord_client_* | discord |
| GitLab | gitlab_enabled, gitlab_test_passed, gitlab_client_* | gitlab |
| OIDC | oidc_enabled, oidc_test_passed, oidc_client_*, oidc_issuer_url, oidc_provider_name | oidc (custom) |

Login page shows only providers where **credentials are set, "Test connection" has passed (`{provider}_test_passed`), and `{provider}_enabled` is true**. The test validates credentials at the provider's token endpoint (invalid_client = fail; invalid_grant/invalid_request = credentials accepted).

## Related Documentation

- [ADR-003: SSO Provider Integration](../../adr/003-sso-provider-integration.md)
- [Env to Database Roadmap](../../plans/env-to-database-roadmap.md) (Phase 5: SSO settings migration)

### Implementation Journal

- [SSO Settings Migration (2026-01-28)](../../journal/2026-01-28-sso-settings-migration.md)
- [SSO Settings Enhancement (2026-01-30)](../../journal/2026-01-30-sso-settings-enhancement.md)
- [SSO Test Toggle Fix (2026-02-05)](../../journal/2026-02-05-sso-test-toggle-fix.md)
- [SSO Redirect URI Fix (2026-02-05)](../../journal/2026-02-05-sso-redirect-uri-fix.md)
- [SSO Callback Page Fix (2026-02-06)](../../journal/2026-02-06-sso-callback-page-fix.md)
- [SSO Session Persistence Fix (2026-02-06)](../../journal/2026-02-06-sso-session-persistence-fix.md)
