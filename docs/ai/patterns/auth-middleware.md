# Auth Middleware Stack Pattern

The application uses a layered middleware stack for authentication. Apply the correct middleware when adding new routes.

## Middleware Reference

| Middleware | Purpose | When to Apply |
|-----------|---------|---------------|
| `auth:sanctum` | Session-based authentication | All API routes requiring login |
| `Ensure2FAVerified` | Block until 2FA code is verified this session | Routes after login that need 2FA gate |
| `Ensure2FASetupWhenRequired` | Block until user has set up 2FA (when system requires it) | Post-auth routes when `auth.two_factor_mode` is `required` |
| `EnsureEmailIsVerified` | Block until email is verified (when system requires it) | Post-auth routes when `auth.email_verification_mode` is `required` |
| `can:admin` | Restrict to admin group members | Admin-only routes |

## How They Work

### `Ensure2FAVerified`

Checks `session('2fa:verified')`. If user has 2FA enabled but hasn't verified this session, returns 403 with `requires_2fa: true`. Frontend redirects to 2FA verification page.

```php
// Returns 403 with { requires_2fa: true } if not verified
if ($user->hasTwoFactorEnabled() && !$request->session()->has('2fa:verified')) {
    return response()->json(['requires_2fa' => true], 403);
}
```

### `Ensure2FASetupWhenRequired`

Only active when `SettingService::get('auth', 'two_factor_mode')` is `'required'`. Returns 403 with `requires_2fa_setup: true` if user hasn't set up 2FA.

### `EnsureEmailIsVerified`

Only active when `SettingService::get('auth', 'email_verification_mode')` is `'required'`. Returns 403 if email is unverified.

## Applying to New Routes

```php
// Standard authenticated route
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-feature', [MyController::class, 'index']);
});

// Admin-only route
Route::middleware(['auth:sanctum', 'can:admin'])->group(function () {
    Route::get('/admin/feature', [AdminController::class, 'index']);
});
```

The 2FA and email verification middleware are applied globally to the `auth:sanctum` group in `bootstrap/app.php` — you do not need to add them to individual routes.

## Frontend Handling

The frontend API client checks for `requires_2fa` and `requires_2fa_setup` in 403 responses and redirects accordingly. See `frontend/lib/api.ts`.

**Key files:** `backend/app/Http/Middleware/Ensure2FAVerified.php`, `backend/app/Http/Middleware/Ensure2FASetupWhenRequired.php`, `backend/app/Http/Middleware/EnsureEmailIsVerified.php`, `backend/bootstrap/app.php`

**Related:** [ADR-002](../../adr/002-authentication-architecture.md), [ADR-004](../../adr/004-two-factor-authentication.md), [Pattern: Permission Checking](permission-checking.md)

## Implementation Journal

- [Configurable Auth Features (2026-01-30)](../../journal/2026-01-30-configurable-auth-features.md)
- [Login Testing Review (2026-02-05)](../../journal/2026-02-05-login-testing-review.md)
- [SSO Callback Page Fix (2026-02-06)](../../journal/2026-02-06-sso-callback-page-fix.md)
- [SSO Session Persistence Fix (2026-02-06)](../../journal/2026-02-06-sso-session-persistence-fix.md)
