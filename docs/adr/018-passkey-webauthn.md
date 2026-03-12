# ADR-018: Passkey / WebAuthn Authentication

## Status

Accepted

## Date

2026-01-30

## Context

Passkeys (WebAuthn) provide phishing-resistant authentication using device-bound credentials (fingerprint, Face ID, or hardware security keys). They improve security over passwords and complement or replace TOTP 2FA. We need a standard, maintainable implementation that fits the existing auth architecture.

## Decision

We will implement **passkey (WebAuthn) authentication** using the **Laragear/WebAuthn** package, with configurable mode (disabled/optional/required) consistent with email verification and 2FA.

### Library Choice

- **Backend**: [laragear/webauthn](https://packagist.org/packages/laragear/webauthn) v4.x (Laravel 11/12, MIT, actively maintained)
- **Frontend**: Native WebAuthn API (no additional npm dependency)

### Configuration

- **Setting**: `auth.passkey_mode` (database + env `AUTH_PASSKEY_MODE`)
- **Values**: `disabled` (default), `optional`, `required`
- **Public feature**: Exposed via `/system-settings/public` as `passkey_mode` for login UI and user security page

### Flows

**Registration (authenticated)**  
User adds a passkey from User menu > Security (`/user/security`): request options from `/auth/passkeys/register/options`, create credential via `navigator.credentials.create()`, submit to `/auth/passkeys/register`. Credentials stored in `webauthn_credentials` (polymorphic to User).

**Login (unauthenticated)**  
User clicks "Sign in with Passkey" on login page: request options from `/auth/passkeys/login/options`, get credential via `navigator.credentials.get()`, submit to `/auth/passkeys/login`. Session established on success; audit event `passkey_login`.

### Security and Compatibility

- **HTTPS**: WebAuthn requires a secure context (HTTPS in production; localhost allowed for development).
- **Browser support**: All modern browsers support WebAuthn; frontend checks `PublicKeyCredential` and only shows passkey options when supported and when `passkey_mode` is not `disabled`.
- **Audit**: Events `passkey_registered`, `passkey_deleted`, `passkey_login`, `passkey_login_failed` are logged via AuditService.

### Key Files

- **Backend**: `backend/app/Services/Auth/PasskeyService.php`, `backend/app/Http/Controllers/Api/PasskeyController.php`, `backend/app/Models/User.php` (WebAuthnAuthenticatable trait), `backend/config/settings-schema.php` (auth.passkey_mode), `backend/routes/api.php` (auth passkey routes), `backend/database/migrations/2026_01_30_000022_create_webauthn_credentials_table.php`
- **Frontend**: `frontend/lib/use-passkeys.ts`, `frontend/components/auth/passkey-register-dialog.tsx`, `frontend/components/auth/passkey-login-button.tsx`, `frontend/app/(dashboard)/user/security/page.tsx` (Passkeys card), `frontend/app/(auth)/login/page.tsx` (passkey login button), `frontend/app/(dashboard)/configuration/security/page.tsx` (admin passkey mode), `frontend/lib/app-config.tsx` (passkeyMode)

### Attestation Strategy

Attestation is set to `"none"` by design. This is standard for consumer-facing applications where the goal is user authentication, not device identity verification. Certificate chain verification (attestation types `direct` or `enterprise`) adds complexity and is primarily useful for enterprise deployments that need to restrict authentication to specific hardware vendors (e.g., only YubiKeys). To enable attestation verification, configure it via the Laragear WebAuthn config at `backend/config/webauthn.php`.

### Passkey Sync Behavior

Modern passkeys are discoverable credentials that sync across devices via platform providers:
- **Apple**: iCloud Keychain (across iPhone, iPad, Mac)
- **Google**: Google Password Manager (across Android, Chrome)
- **Microsoft**: Windows Hello (Windows devices)

This means a single registered passkey may authenticate from multiple devices without re-registration. Deleting a passkey server-side removes access but does not remove the credential from the user's sync provider. Users should be informed that removing a passkey on the server only disables authentication — the credential data persists in their password manager until manually deleted there.

## API Documentation

- [Laragear WebAuthn](https://github.com/Laragear/WebAuthn)
- [Web Authentication (WebAuthn) W3C Spec](https://www.w3.org/TR/webauthn-2/)

## Consequences

- Users can sign in with passkeys when enabled, improving security and UX.
- Admins control passkey availability via Configuration > Security (Authentication card).
- Implementation follows existing patterns (TwoFactorService, AuthSettingController, public features).
- Requires `laragear/webauthn` and migration; app will not load the WebAuthn trait until the package is installed (e.g. via `composer require laragear/webauthn` and `php artisan migrate`).

## Implementation Journal

- [Configurable Auth Features (2026-01-30)](../journal/2026-01-30-configurable-auth-features.md)
