# Configurable Authentication Features Roadmap

Evaluate and implement optional/configurable user authentication features that admins can enable or disable based on their deployment requirements.

**Priority**: MEDIUM  
**Status**: Complete (Phases 1–8); optional user docs for 2FA/passkey setup remain  
**Last Updated**: 2026-01-30

**Dependencies**: 
- [Email Configuration Dependencies](email-configuration-dependencies-roadmap.md) - Email verification and password reset depend on email being configured

---

## Problem Statement

Currently, authentication features may be hardcoded as always-on or always-off. Different deployments have different security requirements - some need strict 2FA enforcement, others are internal tools that don't need email verification.

**Goal**: Make authentication features configurable so admins can:
- Enable/disable features based on their needs
- Require certain features (e.g., mandatory 2FA)
- Customize the authentication experience for their deployment

---

## Features to Evaluate

### 1. Email Verification (Confirm Email)

**Current State**: Evaluate if currently implemented and how

**Options**:
- Disabled: Users can use accounts immediately after registration
- Optional: Users prompted to verify but not required
- Required: Users must verify email before accessing app

**Configuration**:
```
AUTH_EMAIL_VERIFICATION=disabled|optional|required
```

**Considerations**:
- Depends on email being configured (see Email Configuration Dependencies roadmap)
- If required but email not configured, registration should be disabled

### 2. Self-Service Password Reset

**Current State**: Evaluate if currently implemented

**Options**:
- Enabled: Users can reset their own password via email
- Disabled: Only admins can reset user passwords

**Configuration**:
```
AUTH_PASSWORD_RESET_ENABLED=true|false
```

**Considerations**:
- Depends on email being configured
- When disabled, provide admin UI for password reset
- Consider security implications (some orgs prefer admin-controlled resets)

### 3. Two-Factor Authentication (2FA/MFA)

**Current State**: Evaluate existing 2FA implementation

**Options**:
- Disabled: 2FA not available
- Optional: Users can enable 2FA in their settings
- Required: All users must set up 2FA

**Configuration**:
```
AUTH_2FA_MODE=disabled|optional|required
```

**Supported Methods** (evaluate which to support):
- TOTP (Authenticator apps like Google Authenticator, Authy)
- Email codes
- SMS codes (requires SMS provider)
- Recovery codes

**Considerations**:
- If required, need grace period for users to set up
- Recovery codes essential for account recovery
- Admin ability to reset user's 2FA

### 4. Passkey / WebAuthn

**Current State**: Evaluate if currently implemented

**Options**:
- Disabled: Passkeys not available
- Optional: Users can add passkeys as login method
- Required: Users must register a passkey (advanced)

**Configuration**:
```
AUTH_PASSKEY_MODE=disabled|optional|required
```

**Considerations**:
- Modern, phishing-resistant authentication
- Requires HTTPS in production
- Browser/device support varies
- Consider as password alternative or second factor

---

## Task Checklist

### Phase 1: Audit Current State

- [x] Document current email verification implementation
- [x] Document current password reset implementation
- [x] Document current 2FA implementation
- [x] Document current passkey implementation (if any)
- [x] Identify hardcoded assumptions in auth flows

### Phase 2: Configuration Infrastructure

- [x] Define configuration schema for auth features
- [x] Add settings to System Settings or database
- [x] Create API endpoint to fetch auth configuration
- [x] Ensure settings are admin-only

### Phase 3: Email Verification

- [x] Implement configurable email verification (disabled/optional/required)
- [x] Update registration flow based on setting
- [x] Update login flow to check verification status
- [x] Handle case where email not configured but verification required

### Phase 4: Password Reset

- [x] Implement toggle for self-service password reset
- [x] Update forgot password UI based on setting
- [x] Ensure admin can always reset passwords
- [x] Add admin UI for password reset when self-service disabled

### Phase 5: Two-Factor Authentication

- [x] Implement configurable 2FA mode (disabled/optional/required)
- [x] Ensure TOTP setup flow works
- [x] Add recovery codes generation and usage
- [x] Add admin ability to reset user 2FA
- [x] Handle required mode with grace period

### Phase 6: Passkey Support

- [ ] Evaluate WebAuthn library options for Laravel
- [ ] Implement passkey registration flow
- [ ] Implement passkey login flow
- [ ] Add passkey management in user settings
- [ ] Configure as disabled/optional/required

### Phase 7: Admin UI

- [x] Add auth configuration section to System Settings
- [x] Show current status of each feature
- [x] Provide clear descriptions of each option
- [x] Warn about dependencies (email required for some features)

### Phase 8: Documentation

- [x] Document each auth feature and its options
- [x] Document security implications of each setting
- [x] Add user documentation for 2FA and passkey setup (optional)

---

## Configuration Summary

| Feature | Options | Default | Depends On |
|---------|---------|---------|------------|
| Email Verification | disabled, optional, required | optional | Email configured |
| Password Reset | enabled, disabled | enabled | Email configured |
| 2FA | disabled, optional, required | optional | None |
| Passkeys | disabled, optional, required | disabled | HTTPS |

---

## Files Reference

**Backend**:
- `backend/app/Http/Controllers/Api/AuthController.php` - Auth endpoints (forgot-password checks `password_reset_enabled`)
- `backend/app/Http/Controllers/Api/AuthSettingController.php` - Auth settings CRUD (GET/PUT `/auth-settings`)
- `backend/app/Http/Controllers/Api/SystemSettingController.php` - Public features (`password_reset_available`, `email_verification_mode`, `two_factor_mode`)
- `backend/app/Services/Auth/TwoFactorService.php` - 2FA service
- `backend/config/auth.php` - Auth configuration
- `backend/config/settings-schema.php` - `auth` group (email_verification_mode, password_reset_enabled, two_factor_mode)
- `backend/app/Http/Middleware/EnsureEmailIsVerified.php` - Enforces verification when mode is required
- `backend/app/Http/Middleware/Ensure2FASetupWhenRequired.php` - Returns 403 with `requires_2fa_setup` when 2FA required and user has not set up

**Frontend**:
- `frontend/app/(auth)/` - Auth pages (login, register, forgot-password; login hides "Forgot password?" when `passwordResetAvailable` false)
- `frontend/app/(dashboard)/configuration/security/page.tsx` - Admin-only security page: Authentication (system-wide) card (email verification, password reset, 2FA mode, passkey mode). Per-user password/2FA/passkeys/SSO are on User menu > Security (`/user/security`).
- `frontend/lib/app-config.tsx` - Maps public features (emailVerificationMode, twoFactorMode)
- `frontend/lib/api.ts` - Redirects to `/user/security` on 403 with `requires_2fa_setup`

**Documentation**:
- `docs/adr/002-authentication-architecture.md`
- `docs/adr/004-two-factor-authentication.md`

---

## Success Criteria

- [x] All four auth features are independently configurable (email verification, password reset, 2FA, passkeys)
- [x] Admin UI clearly shows configuration options (Configuration > Security)
- [x] Features gracefully degrade when dependencies unavailable (warning when email not configured)
- [x] Documentation covers all configuration options
- [x] No breaking changes to existing deployments (sensible defaults)

## Implementation Journal

- [Configurable Auth Features](../journal/2026-01-30-configurable-auth-features.md)
