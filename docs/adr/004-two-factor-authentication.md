# ADR-004: Two-Factor Authentication

## Status

Accepted

## Date

2026-01-24

## Context

Two-factor authentication (2FA) significantly increases account security by requiring "something you know" (password) and "something you have" (TOTP device). We need to implement 2FA that is:
- Easy to set up for users
- Compatible with standard authenticator apps
- Recoverable if the user loses their device

## Decision

We will implement **TOTP (Time-based One-Time Password)** 2FA with recovery codes.

### TOTP Implementation

Using RFC 6238 TOTP with:
- 6-digit codes
- 30-second time step
- SHA-1 algorithm (for maximum compatibility)
- 32-character base32 secret

Compatible with:
- Google Authenticator
- Authy
- 1Password
- Bitwarden
- Microsoft Authenticator
- Any RFC 6238 compliant app

### Recovery Codes

- 10 one-time recovery codes generated on 2FA setup
- Each code is 10 alphanumeric characters
- Codes are displayed once and must be saved by user
- Each code can only be used once
- Using all codes disables 2FA (user must re-setup)

### Architecture

```
┌────────────────────────────────────────────────────────────┐
│                    2FA Setup Flow                           │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  1. User requests 2FA setup                                 │
│         │                                                   │
│         ▼                                                   │
│  2. Generate TOTP secret                                    │
│     Generate 10 recovery codes                              │
│         │                                                   │
│         ▼                                                   │
│  3. Display QR code + manual entry key                      │
│     Display recovery codes (once)                           │
│         │                                                   │
│         ▼                                                   │
│  4. User scans QR code                                      │
│         │                                                   │
│         ▼                                                   │
│  5. User enters verification code                           │
│         │                                                   │
│         ▼                                                   │
│  6. Verify code matches                                     │
│     Enable 2FA on account                                   │
│     Store encrypted secret                                  │
│                                                             │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│                    2FA Login Flow                           │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  1. User logs in with email/password                        │
│         │                                                   │
│         ▼                                                   │
│  2. Check if 2FA is enabled                                 │
│         │                                                   │
│    ┌────┴────────────┐                                     │
│    │ 2FA Disabled    │──► Complete login                   │
│    └─────────────────┘                                     │
│    │ 2FA Enabled     │                                     │
│    └────┬────────────┘                                     │
│         ▼                                                   │
│  3. Create partial session (2FA pending)                    │
│     Return 2FA challenge response                           │
│         │                                                   │
│         ▼                                                   │
│  4. User enters TOTP code or recovery code                  │
│         │                                                   │
│         ▼                                                   │
│  5. Verify code                                             │
│     If recovery: mark code as used                          │
│         │                                                   │
│         ▼                                                   │
│  6. Upgrade to full session                                 │
│     Complete login                                          │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

### Database Schema

```sql
users (existing table, add columns)
├── two_factor_secret (encrypted, nullable)
├── two_factor_recovery_codes (encrypted JSON, nullable)
├── two_factor_confirmed_at (timestamp, nullable)
```

### API Endpoints

```
GET  /api/2fa/status          - Check if 2FA is enabled
POST /api/2fa/setup           - Begin 2FA setup (returns QR code)
POST /api/2fa/confirm         - Confirm 2FA setup with code
POST /api/2fa/verify          - Verify 2FA during login
POST /api/2fa/disable         - Disable 2FA (requires password)
POST /api/2fa/recovery-codes  - Regenerate recovery codes
```

### Security Measures

- TOTP secrets encrypted at rest with APP_KEY
- Recovery codes encrypted at rest using Laravel's `encrypted` cast (not bcrypt)
- Rate limiting on verification attempts (5/minute)
- Partial sessions expire after 5 minutes
- 2FA required for sensitive operations (password change)

### TwoFactorService

Key methods:
- `generateSecret()` - Create new TOTP secret
- `generateQrCodeUrl()` - URL for QR code image
- `verify($code)` - Validate TOTP code (±1 time step window)
- `generateRecoveryCodes()` - Create 10 recovery codes
- `useRecoveryCode($code)` - Validate and consume recovery code

## Consequences

### Positive

- Standard TOTP works with all major authenticator apps
- Recovery codes prevent lockout
- Encrypted storage protects secrets at rest
- Partial sessions prevent bypassing 2FA

### Negative

- Users must securely store recovery codes
- Lost phone + lost recovery = account lockout
- TOTP requires reasonably accurate device clock
- ~~No hardware key support~~ — Now addressed by [ADR-018: Passkey/WebAuthn](./018-passkey-webauthn.md)

### Neutral

- 2FA is opt-in (not required by default)
- Admins can enforce 2FA via policy (future feature)
- SSO-authenticated users may skip 2FA if IdP provides it

## Related Decisions

- [ADR-002: Authentication Architecture](./002-authentication-architecture.md)
- [ADR-003: SSO Provider Integration](./003-sso-provider-integration.md)
- [ADR-018: Passkey/WebAuthn Authentication](./018-passkey-webauthn.md) – Hardware key support (implements Future Enhancement #1)

## Notes

### Future Enhancements

1. ~~**WebAuthn/FIDO2**~~ - **[IMPLEMENTED]** See [ADR-018: Passkey/WebAuthn](./018-passkey-webauthn.md)
2. **SMS Backup** - Phone number as 2FA backup (less secure)
3. ~~**Enforce 2FA**~~ - **[IMPLEMENTED]** Admin can set 2FA mode to required via `AuthSettingController`
4. **Remember Device** - Skip 2FA on trusted devices for 30 days

### Implementation Library

Using `pragmarx/google2fa-laravel` package which provides:
- TOTP generation and verification
- QR code generation
- Time-step window tolerance

## Implementation Journal

- [Configurable Auth Features (2026-01-30)](../journal/2026-01-30-configurable-auth-features.md)
