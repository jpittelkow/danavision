# Phase 1: Security and Authentication Review - 2026-02-05

## Overview

Comprehensive security review of authentication flows, user data access, route protection, SSRF, secrets management, and frontend security as specified in the Phase 1 Security Review plan.

## Summary

| Severity | Count |
|----------|-------|
| CRITICAL | 0 |
| HIGH     | 0 |
| MEDIUM   | 2 |
| LOW      | 2 |

## Findings

### [MEDIUM] Vision query image_url not validated for SSRF (OpenAI/Anthropic/Azure)

**File:** `backend/app/Http/Controllers/Api/LLMController.php` (visionQuery), `backend/app/Services/LLM/Providers/OpenAIProvider.php`, `AnthropicProvider.php`, `AzureOpenAIProvider.php`

**Issue:** When `image_url` is supplied to the vision query endpoint, it is passed to OpenAI, Anthropic, and Azure providers, which send the URL to their external APIs. Those APIs then fetch the URL. Gemini and Bedrock validate URLs via `UrlValidationService` before fetching; OpenAI, Anthropic, and Azure do not. A user could supply an internal/private URL and cause the external provider to fetch it (information disclosure to third party).

**Recommendation:** Validate `image_url` with `UrlValidationService::validateUrl()` in `LLMController::visionQuery()` before passing to the orchestrator; return 400 if invalid.

**Reference:** [ADR-024: Security Hardening](../adr/024-security-hardening.md), [anti-patterns – SSRF](../ai/anti-patterns.md)

---

### [MEDIUM] Inline request validation in auth and user controllers

**Files:** `backend/app/Http/Controllers/Api/AuthController.php`, `TwoFactorController.php`, `PasskeyController.php`, `SSOController.php`, `UserController.php`, `ProfileController.php`

**Issue:** Controllers use inline `$request->validate()` instead of dedicated FormRequest classes. This works but reduces reusability and centralizes validation in controllers rather than request classes.

**Recommendation:** Introduce FormRequest classes for auth and user operations (e.g. `LoginRequest`, `RegisterRequest`, `StoreUserRequest`) and use them in controllers. Lower priority than functional security fixes.

**Reference:** [Code review recipe](../ai/recipes/code-review.md), [anti-patterns – Form Request](../ai/anti-patterns.md)

---

### [LOW] Failed 2FA verification attempts not audited

**File:** `backend/app/Http/Controllers/Api/TwoFactorController.php` (verify method)

**Issue:** Successful 2FA verification is audited (`logAuth('login', $user)`). Failed verification attempts (invalid code or recovery code) are not audited, which reduces visibility for brute-force or credential-stuffing detection.

**Recommendation:** Call `$this->auditService->logAuth('2fa_verify_failed', $user ?? null, ['reason' => 'invalid_code'], 'warning')` (or similar) when verification fails, taking care not to leak whether the code was TOTP vs recovery format.

**Reference:** [Logging compliance](../../.cursor/rules/logging-compliance.mdc)

---

### [LOW] Email verification endpoint has no rate limiting

**File:** `backend/routes/api.php` – `Route::post('/verify-email', ...)`

**Issue:** `POST /auth/verify-email` is public and has no throttle. An attacker could try many (id, hash) pairs. The hash is derived from the user’s email, so guessing is non-trivial but not impossible for small IDs or predictable emails.

**Recommendation:** Add `->middleware('throttle:10,1')` (or similar) to the verify-email route to limit attempts per IP.

**Reference:** [Code review – rate limiting](../ai/recipes/code-review.md)

---

## Checklist Verification

### 1.1 Authentication controllers

- **Password handling:** Passwords passed as plaintext; User model uses `hashed` cast. No `Hash::make()` in controllers. Verified in AuthController (register, resetPassword callback), UserController (store, update, resetPassword), ProfileController (updatePassword).
- **Session:** Session regenerated after login (AuthController, TwoFactorController verify, PasskeyController login). Logout invalidates and regenerates token.
- **Disabled accounts:** Blocked in AuthController::login and PasskeyController::login with audit.
- **Failed login:** Audited in AuthController::login (login_failed).
- **OAuth state:** SSOController::callback validates state via `SSOService::validateStateToken`.
- **Rate limiting:** Sensitive auth routes use `rate.sensitive:login`, `rate.sensitive:2fa`, `rate.sensitive:password_reset`, `rate.sensitive:register`; check-email and passkey login use `throttle:10,1`.

### 1.2 User data controllers

- **AdminAuthorizationTrait:** Used in UserController (destroy, toggleAdmin, toggleDisabled) and ProfileController (destroy) for last-admin checks.
- **User scoping:** UserController index/show are behind `can:users.view`; profile and user settings use `$request->user()`. No unscoped user data found.
- **Sensitive fields:** User model `$hidden` includes password, two_factor_secret, two_factor_recovery_codes. UserController responses use `makeHidden([...])` for user payloads.
- **Permission middleware:** User and group routes use `can:users.*`, `can:groups.*` as appropriate.

### 1.3 Route security

- **Auth and permissions:** Authenticated routes use `auth:sanctum`, `verified`, `2fa.setup`. Admin/config routes use `can:settings.view`, `can:settings.edit`, `can:users.view`, etc. File manager uses `can:admin`.
- **Access logging:** `log.access:User` on profile, users, search; `log.access:Setting` on user settings. Matches plan.
- **Rate limiting:** Auth endpoints use `rate.sensitive:*` or `throttle:10,1` as noted above.
- **Public routes:** Only version, health, SSO providers, system-settings/public, branding, client-errors, and auth flows (login, register, verify-email, etc.) are public; none expose sensitive data.

### 1.4 Middleware

- **RateLimitSensitive:** Implemented with per-key limits (login 5/5min, 2fa 5/5min, password_reset 3/1hr, register 3/1hr). Key uses IP and, for login, email; for 2fa, session user id.
- **LogResourceAccess:** Logs PHI access with resource type, action, resource ID, and filtered field list. Registered as `log.access`.
- **Ensure2FASetupWhenRequired / Ensure2FAVerified:** 2fa.setup blocks when mode is required and user has not enabled 2FA. 2fa alias (Ensure2FAVerified) checks session `2fa:verified`; used where needed in auth flow.
- **Registration:** All middleware aliases registered in `backend/bootstrap/app.php`.

### 1.5 SSRF

- **UrlValidationService:** Used in WebhookController (store, update, test), SSOSettingController (OIDC discovery), BedrockProvider and GeminiProvider (vision image URL fetch). Private IP ranges and cloud metadata blocked; redirects validated in fetchContent.
- **Gap:** Vision `image_url` passed to OpenAI, Anthropic, and Azure without validation; see MEDIUM finding above. Fix: validate in LLMController before calling orchestrator.

### 1.6 Secrets

- **No hardcoded secrets:** Grep found no literal API keys or tokens in backend app code. LLM and notification providers use config/settings and env fallback.
- **Encryption:** settings-schema.php marks sensitive keys (passwords, client secrets, tokens, etc.) with `'encrypted' => true`.
- **.env.example:** Uses placeholders (e.g. MEILI_MASTER_KEY=your-secure-master-key-here); no real secrets.

### 1.7 FormRequest

- **GroupController:** Uses StoreGroupRequest, UpdateGroupRequest, UpdateGroupPermissionsRequest, UpdateGroupMembersRequest.
- **Auth/User:** Use inline validate(); see MEDIUM finding above. Password rules use `Password::defaults()` where applicable.

### 1.8 Frontend

- **isAdminUser:** Admin checks use `isAdminUser(user)` from `@/lib/auth` in configuration layout, api page, storage, user-table, help center, onboarding, etc. Sidebar uses `isAdminUser(user)` with fallback to `user?.is_admin` for stale bundle.
- **API:** `api` from `@/lib/api` uses `withCredentials: true`; baseURL from env or `/api`. No hardcoded API URLs in app code.
- **CSRF:** Auth flows (login, register) call `api.get("/sanctum/csrf-cookie", { baseURL: ... })` before mutations.
- **Manifest route:** Server-side fetch to public endpoints (branding, system-settings/public); no credentials required. error-logger uses `credentials: "include"` for client-errors.

## Next Steps

1. **SSRF fix (done):** `image_url` is now validated in `LLMController::visionQuery()` via `UrlValidationService::validateUrl()`; invalid or internal URLs return 400.
2. **Optional:** Add audit logging for failed 2FA verification and rate limiting for verify-email in a follow-up.
3. **Optional:** Introduce FormRequest classes for auth and user management over time.

## Testing Notes

- Re-run auth flows (login, 2FA, passkey, SSO) and confirm rate limiting and audit entries.
- Call vision query with `image_url` set to an internal URL (e.g. http://169.254.169.254/) and confirm it is rejected after the LLMController fix.
- Confirm profile, users, search, and user settings routes log access when HIPAA logging is enabled.
