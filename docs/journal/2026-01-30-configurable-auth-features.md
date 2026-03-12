# Configurable Auth Features - 2026-01-30

## Overview

Implemented configurable authentication features per the Configurable Auth Features roadmap: admins can set **email verification mode** (disabled/optional/required), **self-service password reset** (on/off), and **two-factor mode** (disabled/optional/required). Settings are stored in the `auth` group in the settings schema; public features are exposed via `GET /system-settings/public` so the login and forgot-password pages show/hide the "Forgot password?" link and enforce 2FA setup when required.

## Implementation Approach

- **Backend:** Added `auth` group to `settings-schema.php` (email_verification_mode, password_reset_enabled, two_factor_mode). AuthSettingController provides GET/PUT `/auth-settings` (admin-only). SystemSettingController publicSettings now sets `password_reset_available` to email configured AND password_reset_enabled, and exposes `email_verification_mode` and `two_factor_mode`. AuthController forgotPassword returns 503 when password reset is disabled. EnsureEmailIsVerified middleware enforces verification only when mode is `required`; applied to main authenticated route group. Ensure2FASetupWhenRequired middleware returns 403 with `requires_2fa_setup: true` when 2FA is required and user has not set it up; applied to main group so users can still use auth prefix routes (user, 2fa/*, logout) to set up 2FA.
- **Frontend:** Configuration > Security page gained "Authentication (system-wide)" card (email verification mode radios, password reset switch, two-factor mode radios; warning when email not configured but features depend on it). Security nav link added under Users & Access. Login and forgot-password already used `passwordResetAvailable`; no change. Forgot-password unavailable message updated to cover "disabled by admin". App config maps `emailVerificationMode` and `twoFactorMode`. API interceptor redirects to `/configuration/security` on 403 with `requires_2fa_setup`. 2FA card on security page hidden when `two_factor_mode === 'disabled'`; "Required" badge when required.
- **Docs:** Updated features.md (configurable auth, Security page), api/README.md (Auth Settings, public features, forgot-password 503), configurable-auth-features roadmap (status, checkboxes, files reference), roadmaps.md (moved to Completed Core Done).

## Key Files

- `backend/config/settings-schema.php` (auth group)
- `backend/app/Http/Controllers/Api/AuthSettingController.php`
- `backend/app/Http/Controllers/Api/AuthController.php` (forgotPassword toggle check)
- `backend/app/Http/Controllers/Api/SystemSettingController.php` (public features)
- `backend/app/Http/Middleware/EnsureEmailIsVerified.php`, `Ensure2FASetupWhenRequired.php`
- `backend/routes/api.php` (auth-settings routes, verified + 2fa.setup on main group)
- `frontend/app/(dashboard)/configuration/security/page.tsx` (auth settings card)
- `frontend/app/(dashboard)/configuration/layout.tsx` (Security nav)
- `frontend/lib/app-config.tsx`, `frontend/lib/api.ts`, `frontend/app/(auth)/forgot-password/page.tsx`

## Observations

- When 2FA is required and the user has not set it up, GET `/auth-settings` returns 403 (2fa.setup); the security page still loads (user is on that page) and the auth settings card shows defaults until they set up 2FA. Flow is correct.
- Passkeys (Phase 6) and optional user docs for 2FA/passkey setup were not implemented.

## Next Steps (Future Considerations)

- Phase 6: Passkey/WebAuthn support if desired.
- Optional: User documentation for 2FA setup.

## Testing Notes

- Toggle password reset off: login "Forgot password?" hidden, forgot-password shows unavailable, POST forgot-password returns 503.
- Email verification required: unverified user gets 403 on protected routes; can still use /auth/user, resend-verification.
- 2FA required: user without 2FA gets 403 on protected routes with requires_2fa_setup; redirect to /configuration/security; can set up 2FA via auth prefix routes.
- 2FA disabled: 2FA card hidden on security page.
