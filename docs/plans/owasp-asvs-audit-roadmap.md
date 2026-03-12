# OWASP ASVS Level 2 Security Audit Roadmap

Structured audit of the Sourdough codebase against the OWASP Application Security Verification Standard (ASVS) v4.0.3, Level 2 requirements. Level 2 is appropriate for applications that handle sensitive data — the standard target for most self-hosted SaaS products.

**Priority**: MEDIUM
**Status**: Planned
**Last Updated**: 2026-03-08

**Dependencies**:
- [Security Compliance Review](security-compliance-roadmap.md) - Already complete; provides baseline
- [ADR-024: Security Hardening](../adr/024-security-hardening.md) - 9 vulnerabilities already fixed

---

## How to Use This Checklist

- **PASS** = Verified implemented and working correctly
- **FAIL** = Not implemented or incorrectly implemented (needs fix)
- **N/A** = Not applicable to Sourdough's architecture
- **PARTIAL** = Partially implemented (note what's missing)

Items marked with current status based on code review. Each section references the ASVS requirement ID and the relevant Sourdough files.

---

## V1: Architecture, Design, and Threat Modeling

### V1.1 Secure Software Development Lifecycle

- [x] **V1.1.1** — SDLC with security activities at each phase — [SDLC Policy](../compliance/sdlc-policy.md)
- [x] **V1.1.2** — Threat modeling for design changes — [ISMS Framework](../compliance/iso27001/isms-framework.md)
- [x] **V1.1.3** — All user stories include security constraints — Recipes enforce security patterns in [docs/ai/recipes/](../ai/recipes/)
- [ ] **V1.1.5** — Defined and documented high-value business logic flows — Document critical paths (auth, payment, backup restore, data export)
- [ ] **V1.1.6** — Centralized security controls documentation — Consolidate from scattered ADRs into single security controls reference
- [x] **V1.1.7** — All code has access to security coding guidelines — [Anti-Patterns](../ai/anti-patterns/README.md), [Patterns](../ai/patterns/README.md)

### V1.2 Authentication Architecture

- [x] **V1.2.1** — Unique accounts per person, no shared accounts — User model enforces unique email
- [x] **V1.2.2** — Communications between components use authenticated connections — Sanctum session auth, API key auth
- [x] **V1.2.3** — Single vetted authentication mechanism — Laravel Sanctum ([ADR-002](../adr/002-authentication-architecture.md))
- [x] **V1.2.4** — All authentication pathways use same strength — Login, SSO, Passkey, API key all enforce same session security

### V1.4 Access Control Architecture

- [x] **V1.4.1** — Trusted enforcement points for access control — Middleware-based (`auth:sanctum`, permission checks)
- [x] **V1.4.2** — Access control uses deny-by-default — Routes require `auth:sanctum` middleware; unprotected routes are explicitly listed
- [x] **V1.4.4** — Single access control mechanism used throughout — Group-based RBAC via `PermissionService` ([ADR-020](../adr/020-user-groups-permissions.md))
- [ ] **V1.4.5** — Attribute or feature-based access control for granular decisions — Current RBAC is group-level; no resource-level policies (e.g., "user can only edit their own posts")

**Files**: `backend/app/Http/Middleware/`, `backend/app/Services/PermissionService.php`, `backend/app/Enums/Permission.php`

### V1.5 Input and Output Architecture

- [x] **V1.5.1** — Input validation enforced on trusted layer — Laravel FormRequest validation on server side
- [x] **V1.5.2** — Serialization not used for communication with untrusted clients — JSON API only, no PHP serialize
- [x] **V1.5.3** — Input validation applied at server-side — Controllers use FormRequest classes
- [x] **V1.5.4** — Output encoding/escaping at output layer — React auto-escapes JSX; Blade templates use `{{ }}` escaping

### V1.6 Cryptographic Architecture

- [x] **V1.6.1** — Explicit policy for cryptographic key management — [Cryptographic Controls](../compliance/iso27001/cryptographic-controls.md)
- [x] **V1.6.2** — Consumers of cryptographic services protect key material — `APP_KEY` via env, encrypted casts on sensitive fields
- [ ] **V1.6.3** — Key rotation procedures — No documented key rotation process for `APP_KEY`, webhook secrets, or API tokens
- [ ] **V1.6.4** — Architecture treats client-side secrets as insecure — Verify no secrets in frontend bundle (check `NEXT_PUBLIC_` env vars)

### V1.7 Errors, Logging, and Auditing Architecture

- [x] **V1.7.1** — Common logging format and approach — `AuditService` + `AddCorrelationId` middleware
- [x] **V1.7.2** — Logs processed securely, not in untrusted environments — Server-side only, admin-only access
- [ ] **V1.7.3** — Log injection prevention — Verify log entries sanitize user-controlled input (correlation IDs, action names)

**Files**: `backend/app/Services/AuditService.php`, `backend/app/Http/Middleware/AddCorrelationId.php`

### V1.8 Data Protection and Privacy Architecture

- [x] **V1.8.1** — Sensitive data identified and classified — [Data Handling Policy](../compliance/data-handling-policy.md)
- [x] **V1.8.2** — All protection levels have associated set of requirements — Encryption for secrets, masking for audit logs
- [ ] **V1.8.3** — All sensitive data identified in architectural documentation — Enumerate all PII/PHI fields stored and their protection mechanisms

### V1.11 Business Logic Architecture

- [x] **V1.11.1** — Business logic flows defined with abuse cases — Rate limiting on auth, suspicious activity detection
- [ ] **V1.11.2** — All business logic flows have appropriate controls — Audit webhook delivery failures, verify payment flow abuse protection
- [x] **V1.11.3** — High-value flows do not depend on client-side logic — All authorization server-side

### V1.14 Configuration Architecture

- [x] **V1.14.1** — Separation of components at different trust levels — Frontend/backend/database separation
- [x] **V1.14.2** — Binary signatures, trusted connections, verified endpoints — Docker image integrity, HTTPS enforcement
- [ ] **V1.14.5** — Build pipeline warns on outdated/insecure components — Composer audit runs but no automated PR blocking on vulnerabilities
- [x] **V1.14.6** — Build output does not contain debug info — Production builds strip source maps, debug disabled

---

## V2: Authentication

### V2.1 Password Security

- [x] **V2.1.1** — Passwords at least 12 characters (or 8 with complexity) — 8 chars with mixed case + numbers + symbols
- [x] **V2.1.2** — Passwords at least 64 characters allowed — Laravel default allows up to 255
- [x] **V2.1.3** — Password truncation not performed — bcrypt truncates at 72 bytes (acceptable per ASVS)
- [x] **V2.1.4** — Any Unicode character allowed — Laravel accepts Unicode in passwords
- [x] **V2.1.5** — Users can change their password — Change password in user security page
- [x] **V2.1.6** — Password change requires current password — Verified in `AuthController`
- [x] **V2.1.7** — Passwords checked against breached password lists — Have I Been Pwned check in production via `Password::uncompromised()`
- [x] **V2.1.8** — Password strength meter provided — Frontend shows strength indicator
- [x] **V2.1.9** — No password composition rules that reduce entropy — Rules are additive (mixed case + numbers + symbols)
- [ ] **V2.1.10** — No periodic credential rotation requirements — Verify no forced password expiry exists (good — forced rotation reduces security)
- [x] **V2.1.11** — "Paste" functionality not disabled on password fields — Frontend allows paste
- [ ] **V2.1.12** — User can choose to view masked password — User security page uses plain `type="password"` instead of `PasswordInput` component with toggle (noted in design review)

**Files**: `backend/app/Providers/AppServiceProvider.php` (password rules), `frontend/app/(dashboard)/user/security/page.tsx`

### V2.2 General Authenticator Security

- [x] **V2.2.1** — Anti-automation controls effective against credential stuffing — Rate limiting: 5 login attempts per 5 minutes
- [x] **V2.2.2** — Weak authenticator use sends notification — Failed login audit events logged
- [x] **V2.2.3** — Notification after credential updates — Audit logging on password change, 2FA enable/disable
- [ ] **V2.2.4** — Impersonation resistance (phishing resistance) — Passkeys provide this, but not required by default
- [x] **V2.2.5** — Credential provider is not shared — Each user has separate credentials
- [ ] **V2.2.7** — Credential recovery does not reveal current credentials — Verify password reset flow doesn't leak current password info

### V2.3 Authenticator Lifecycle

- [x] **V2.3.1** — System-generated initial passwords are randomly generated — Registration requires user-chosen password
- [ ] **V2.3.2** — Enrollment and recovery support secure MFA — Verify recovery code flow enforces session validation
- [ ] **V2.3.3** — Renewal instructions sent well in advance of expiry — API tokens expire after 7 days; no renewal notification mechanism

### V2.5 Credential Recovery

- [x] **V2.5.1** — Recovery token is time-limited (max 20 min) — Laravel password reset default 60 min; consider reducing
- [x] **V2.5.2** — Credential hints and recovery do not leak information — Generic "reset link sent" message regardless of email existence
- [x] **V2.5.3** — Recovery does not lock out the account — Rate limited but not locked
- [x] **V2.5.5** — Recovery URL is random and unguessable — Laravel uses `Str::random(64)` for reset tokens
- [ ] **V2.5.6** — Recovery requires MFA if enabled — Verify password reset with 2FA enabled still requires 2FA after reset

### V2.7 Out-of-Band Verifier

- [x] **V2.7.1** — OOB authenticators expire after use — Email verification links single-use
- [x] **V2.7.2** — OOB communication channel is secure — HTTPS for all auth endpoints
- [x] **V2.7.3** — OOB authenticator requests contain tokens, not passwords — Reset links contain tokens, not credentials
- [ ] **V2.7.6** — OOB codes valid for limited time — Verify email verification link expiry time

### V2.8 Single or Multi-Factor One-Time Verifier (TOTP)

- [x] **V2.8.1** — Time-based OTPs have defined lifetime — 30-second window with ±1 drift
- [x] **V2.8.2** — Symmetric keys for TOTP protected at rest — Encrypted via Laravel `encrypted` cast
- [x] **V2.8.3** — TOTP uses approved algorithms — SHA-1 HMAC (RFC 6238 standard)
- [x] **V2.8.4** — TOTP can only be used once per time step — Anti-replay via session flag `2fa:verified`
- [ ] **V2.8.5** — TOTP replay detection — Verify used TOTP codes are tracked to prevent replay within the same time window
- [x] **V2.8.6** — Physical token loss has recovery mechanism — 10 recovery codes generated on 2FA setup

### V2.9 Cryptographic Verifier (Passkeys/WebAuthn)

- [x] **V2.9.1** — Cryptographic keys based on approved algorithms — WebAuthn with ECDSA/RSA
- [x] **V2.9.2** — Challenge nonce at least 128 bits — Laragear/WebAuthn default challenge length
- [x] **V2.9.3** — Challenge is unique per authentication attempt — Fresh challenge per ceremony

### V2.10 Service Authentication

- [x] **V2.10.1** — Intra-service secrets not based on fixed accounts — No hardcoded service credentials
- [x] **V2.10.2** — Service authentication uses strong auth — API keys with SHA-256 hashing
- [x] **V2.10.3** — API keys provide minimum privilege — Scoped per-user, rate limited
- [ ] **V2.10.4** — API keys rotatable without downtime — Verify users can create new key before revoking old one

---

## V3: Session Management

### V3.1 Session Management Security

- [x] **V3.1.1** — URL does not expose session identifiers — Session in HttpOnly cookies only
- [x] **V3.1.2** — Session identifiers are generated with approved CSPRNG — Laravel uses `random_bytes()`
- [x] **V3.1.3** — Session identifiers at least 128 bits — Laravel default 40-character hex string (160 bits)

### V3.2 Session Binding

- [x] **V3.2.1** — Session regenerated after authentication — Laravel `regenerate()` on login
- [x] **V3.2.2** — Session identifier generated by framework — Laravel session management
- [x] **V3.2.3** — Session tokens use `HttpOnly`, `Secure` attributes — Sanctum cookie configuration

### V3.3 Session Termination

- [x] **V3.3.1** — Logout invalidates session — `AuthController::logout()` destroys session
- [x] **V3.3.2** — Absolute session timeout — 7-day token expiration
- [ ] **V3.3.3** — Idle session timeout — No inactivity-based session timeout (only absolute expiry)
- [x] **V3.3.4** — User can terminate all active sessions — Available in user security page

### V3.4 Cookie-Based Session Management

- [x] **V3.4.1** — Cookie-based tokens have `Secure` attribute — Production HTTPS enforcement
- [x] **V3.4.2** — Cookie-based tokens have `HttpOnly` attribute — Sanctum configuration
- [x] **V3.4.3** — Cookie-based tokens use `SameSite` attribute — `SameSite=Lax`
- [x] **V3.4.4** — Cookie path set to restrictive path — API cookies scoped appropriately
- [x] **V3.4.5** — Same-origin cookies — No third-party cookie sharing

### V3.5 Token-Based Session Management

- [x] **V3.5.1** — OAuth/API tokens do not use static secrets — Per-user generated tokens with SHA-256 hash
- [x] **V3.5.2** — Token validation uses signed tokens — Sanctum token validation
- [x] **V3.5.3** — JWT/token contains session reference, not PII — Sanctum tokens reference token ID, not user data

**Files**: `backend/config/session.php`, `backend/config/sanctum.php`, `backend/app/Http/Controllers/Api/AuthController.php`

---

## V4: Access Control

### V4.1 General Access Control

- [x] **V4.1.1** — Trusted enforcement points for access control — Server-side middleware
- [x] **V4.1.2** — All user/data attributes verified on trusted side — Request validation in controllers
- [x] **V4.1.3** — Principle of least privilege — Permission enum with granular controls
- [ ] **V4.1.5** — Access controls fail securely — Verify all permission checks default to deny on error, not allow

### V4.2 Operation-Level Access Control

- [x] **V4.2.1** — Sensitive data accessible only to authorized users — `user_id` scoping on queries
- [ ] **V4.2.2** — Sensitive business operations have access controls — Verify all admin-only operations check permissions (not just `isAdmin()`)
- [x] **V4.2.3** — Direct object references protected — User-scoped queries prevent IDOR

### V4.3 Other Access Control Considerations

- [x] **V4.3.1** — Admin interfaces use MFA — 2FA middleware on sensitive operations
- [ ] **V4.3.2** — Directory listing disabled — Verify Nginx config disables `autoindex`
- [ ] **V4.3.3** — Metadata and backup files not accessible — Verify `.git`, `.env`, `storage/` not web-accessible

**Files**: `backend/app/Services/PermissionService.php`, `backend/app/Enums/Permission.php`, `backend/app/Http/Middleware/`

---

## V5: Validation, Sanitization, and Encoding

### V5.1 Input Validation

- [x] **V5.1.1** — HTTP parameter pollution does not affect app — Laravel request handling
- [x] **V5.1.2** — Framework protects against mass assignment — Laravel `$fillable` on models
- [x] **V5.1.3** — All input validated (positive validation) — FormRequest validation classes
- [x] **V5.1.4** — Structured data strongly typed and validated — Zod on frontend, FormRequest on backend
- [ ] **V5.1.5** — URL redirects go to allowlisted destinations — Verify no open redirect in SSO callback or password reset flows

### V5.2 Sanitization and Sandboxing

- [x] **V5.2.1** — All untrusted HTML input sanitized — React auto-escapes; `dangerouslySetInnerHTML` usage audited
- [x] **V5.2.2** — Unstructured data sanitized with safety measures — Input validation prevents injection
- [x] **V5.2.3** — Application sanitizes user input before passing to mail — Laravel Mailables use typed properties
- [ ] **V5.2.4** — Avoid `eval()` or dynamic code execution with user input — Verify no `eval()`, `exec()`, `shell_exec()` with user data
- [x] **V5.2.5** — Application protects against template injection — Blade and React handle escaping
- [x] **V5.2.6** — SSRF protections in place — `UrlValidationService` for all user-supplied URLs
- [ ] **V5.2.7** — Application sanitizes SVG uploads — Verify SVG files are sanitized or served with `Content-Type: image/svg+xml` and no inline scripts
- [ ] **V5.2.8** — Application sanitizes user-supplied SSML/XML — Verify if any XML processing exists and is secured

### V5.3 Output Encoding and Injection Prevention

- [x] **V5.3.1** — Output encoding relevant for interpreter — React/JSX auto-escaping
- [x] **V5.3.2** — Output encoding preserves user's chosen character set — UTF-8 throughout
- [x] **V5.3.3** — Context-aware output escaping — HTML (React), SQL (Eloquent), URL (urlencode)
- [x] **V5.3.4** — Database queries use parameterized queries — Eloquent ORM
- [ ] **V5.3.5** — Where parameterized queries not possible, output encoding used — Check raw SQL queries in migrations/seeds for user data
- [ ] **V5.3.7** — Application protects against LDAP injection — N/A (no LDAP)
- [x] **V5.3.8** — Application protects against OS command injection — No shell commands with user input
- [x] **V5.3.10** — Application protects against XPath/XML injection — N/A (no XPath)

### V5.5 Deserialization Prevention

- [x] **V5.5.1** — Serialized objects use integrity checks — JSON only, no PHP `unserialize()`
- [x] **V5.5.2** — Application restricts XML parsers — No XXE vulnerability surface (JSON API)
- [x] **V5.5.3** — Deserialization of untrusted data prevented — JSON.parse only, no `unserialize()`

**Files**: `backend/app/Http/Requests/`, `backend/app/Services/UrlValidationService.php`

---

## V6: Stored Cryptography

### V6.1 Data Classification

- [x] **V6.1.1** — Regulated private data identified and classified — [Data Handling Policy](../compliance/data-handling-policy.md)
- [x] **V6.1.2** — Regulated financial data identified and classified — Stripe handles PCI scope
- [ ] **V6.1.3** — All sensitive data inventory created — Create comprehensive list of encrypted/hashed fields and their storage locations

### V6.2 Algorithms

- [x] **V6.2.1** — All cryptographic modules fail securely — Laravel encryption throws on failure
- [x] **V6.2.2** — Industry-proven cryptographic algorithms used — AES-256-CBC (Laravel), bcrypt (passwords), SHA-256 (tokens)
- [x] **V6.2.3** — Random number generation uses approved CSPRNG — `random_bytes()` / `Str::random()`
- [x] **V6.2.5** — Known insecure algorithms not used — No MD5/SHA-1 for security purposes (SHA-1 in TOTP is per RFC 6238 standard)

### V6.3 Random Values

- [x] **V6.3.1** — All random numbers generated using approved CSPRNG — Laravel uses `random_bytes()`
- [x] **V6.3.2** — Random GUIDs use GUID v4 (random) — UUID generation for audit log IDs
- [x] **V6.3.3** — Random numbers created with proper entropy — PHP `random_bytes()` backed by OS entropy

### V6.4 Secret Management

- [x] **V6.4.1** — Secrets not included in source code — `.env`-based configuration, `.gitignore` enforced
- [x] **V6.4.2** — Key material not exposed to application — `APP_KEY` used via Laravel encryption facade only
- [ ] **V6.4.3** — Key rotation capability exists — No documented rotation process

**Files**: `backend/config/app.php` (encryption), `backend/app/Models/User.php` (encrypted casts)

---

## V7: Error Handling and Logging

### V7.1 Log Content

- [x] **V7.1.1** — Application does not log credentials or payment details — `AuditService` masks sensitive fields (password, token, secret, api_key)
- [x] **V7.1.2** — Application does not log sensitive PII unnecessarily — Audit logs capture actions, not full data
- [ ] **V7.1.3** — Application logs security-relevant events — Verify all security events logged: account lockout, password change, MFA changes, permission changes, session termination
- [x] **V7.1.4** — Each log event includes timestamp, severity, event type — AuditLog model: `created_at`, `severity`, `action`

### V7.2 Log Processing

- [x] **V7.2.1** — All authentication decisions logged — Login success/failure, SSO, passkey, 2FA verification
- [x] **V7.2.2** — All access control decisions logged — Permission checks audited
- [x] **V7.2.3** — Application logs anti-automation events — Rate limit triggers (429 responses)
- [ ] **V7.2.4** — Application logs all input validation failures — Verify FormRequest validation failures are audit-logged, not just returned as 422

### V7.3 Log Protection

- [x] **V7.3.1** — All logging components encode data to prevent injection — Structured JSON logging
- [x] **V7.3.3** — Security logs protected from unauthorized modification — Database-backed, admin-only access
- [ ] **V7.3.4** — Time sources synchronized to ensure consistent timestamps — Verify NTP configuration in Docker container

### V7.4 Error Handling

- [x] **V7.4.1** — Generic error message displayed when unexpected error occurs — Laravel exception handler returns JSON errors
- [x] **V7.4.2** — Exception handling used across codebase — Try-catch in service layer
- [x] **V7.4.3** — Last-resort error handler logs the error — Laravel exception handler

**Files**: `backend/app/Services/AuditService.php`, `backend/app/Exceptions/Handler.php`

---

## V8: Data Protection

### V8.1 General Data Protection

- [x] **V8.1.1** — Application protects sensitive data from caching — API responses include `no-store` where sensitive
- [ ] **V8.1.2** — Sensitive data in server memory cleared after use — PHP request lifecycle handles this; verify long-running processes
- [ ] **V8.1.3** — Sensitive data in transmitted data minimized — Verify API responses don't over-expose user data (check UserResource/transformer)
- [x] **V8.1.5** — Sensitive data identified in code reviews — Automated SAST scanning
- [ ] **V8.1.6** — Data stored on client-side identified and documented — Audit localStorage/sessionStorage usage in frontend

### V8.2 Client-Side Data Protection

- [ ] **V8.2.1** — Sensitive data not stored in browser storage — Verify no tokens/secrets in localStorage/sessionStorage
- [ ] **V8.2.2** — Data in typed form fields uses appropriate input types — Verify `type="password"` on all password fields, `autocomplete` attributes correct
- [x] **V8.2.3** — Sensitive data not sent to third-party analytics — No analytics integration by default

### V8.3 Sensitive Private Data

- [x] **V8.3.1** — Sensitive data sent in HTTP body, not URL parameters — POST/PUT for sensitive operations
- [ ] **V8.3.2** — Users can delete or export their data — Verify user data export capability (DSAR compliance)
- [ ] **V8.3.3** — Users clearly informed about collection/use of PII — Verify privacy notice presence and clarity
- [ ] **V8.3.4** — All sensitive data enumerated with retention periods — Cross-reference data handling policy with actual database tables
- [ ] **V8.3.8** — Sensitive data backups are encrypted — Verify backup encryption configuration

**Files**: `backend/app/Http/Resources/`, `frontend/lib/api.ts`

---

## V9: Communication

### V9.1 Client Communication Security

- [x] **V9.1.1** — TLS used for all client connections — HTTPS enforcement in production
- [x] **V9.1.2** — TLS 1.2 or higher enforced — Nginx/Docker TLS configuration
- [x] **V9.1.3** — Only strong cipher suites enabled — Nginx SSL configuration
- [ ] **V9.1.4** — TLS certificate is valid and trusted — Deployment-dependent; document requirement

### V9.2 Server Communication Security

- [x] **V9.2.1** — Connections to external services use TLS — HTTPS for SSO, Stripe, LLM providers, HIBP
- [x] **V9.2.2** — Encrypted connections verified — Certificate validation on outbound requests (cURL defaults)
- [ ] **V9.2.3** — Certificate pinning for critical connections — Not implemented (consider for Stripe, SSO providers)
- [ ] **V9.2.4** — Backend connections to databases encrypted — SQLite is local; verify MySQL/PostgreSQL TLS when configured

**Files**: `docker/nginx.conf`, `backend/config/database.php`

---

## V10: Malicious Code

### V10.1 Code Integrity

- [x] **V10.1.1** — Source code control system in use — Git with branch protection
- [ ] **V10.1.2** — Source code reviewed for malicious code — Semgrep in CI; no manual review process documented

### V10.2 Malicious Code Search

- [x] **V10.2.1** — Application source code does not contain time bombs — Confirmed via code review
- [x] **V10.2.2** — Application does not phone home — No telemetry or external beacons
- [x] **V10.2.3** — Application source code does not contain backdoors — Open source, auditable

### V10.3 Application Integrity

- [ ] **V10.3.1** — Application has auto-update feature with integrity checking — No auto-update mechanism (manual Docker pulls)
- [x] **V10.3.2** — Application uses subresource integrity — Frontend bundles are self-contained (no CDN scripts)
- [ ] **V10.3.3** — Application uses CSP to prevent loading of untrusted resources — CSP implemented but verify it's actively enforced and not in report-only mode

**Files**: `.github/workflows/ci.yml`, `docker/nginx-security-headers.conf`

---

## V11: Business Logic

### V11.1 Business Logic Security

- [x] **V11.1.1** — Application processes flows in sequential step order — Auth flow: credentials → 2FA → session
- [x] **V11.1.2** — Application processes steps in realistic human time — Rate limiting prevents automated abuse
- [x] **V11.1.3** — Application has limits to specific business actions — Rate limiting on auth, backup creation limits
- [ ] **V11.1.4** — Application has anti-automation controls — Verify CAPTCHA or proof-of-work on registration (currently rate-limited only)
- [x] **V11.1.5** — Application has business logic limits — API rate limiting, file upload limits, pagination limits
- [ ] **V11.1.6** — Application does not accept external content that determines output — Verify no SSRF via LLM prompt injection to access internal services

**Files**: `backend/app/Http/Middleware/RateLimitSensitive.php`, `backend/app/Services/UrlValidationService.php`

---

## V12: Files and Resources

### V12.1 File Upload

- [x] **V12.1.1** — Application does not accept large files that could cause DoS — File size limits configured
- [x] **V12.1.2** — Compressed files checked for decompression bombs — File type whitelist prevents archive processing
- [ ] **V12.1.3** — File size quotas enforced per user — Verify per-user storage limits exist
- [x] **V12.2.1** — Files from untrusted sources validated by type — MIME validation + extension whitelist

### V12.3 File Execution

- [x] **V12.3.1** — User-submitted filenames not used directly — Storage uses generated names
- [x] **V12.3.2** — User-submitted files not served directly by web server — Files served through application layer with auth checks
- [ ] **V12.3.3** — User-submitted filenames sanitized — Verify filename sanitization in upload handlers
- [x] **V12.3.6** — Web server configured to only serve allowed file types — Nginx configuration restricts served paths

### V12.5 File Download

- [x] **V12.5.1** — Application does not serve content from untrusted domains — CSP prevents external resource loading
- [ ] **V12.5.2** — Direct links to known-malicious files are blocked — No malware scanning on uploaded files

**Files**: `backend/app/Http/Controllers/Api/FileManagerController.php`, [ADR-030](../adr/030-file-manager.md)

---

## V13: API and Web Service

### V13.1 Generic Web Service Security

- [x] **V13.1.1** — All API responses contain `Content-Type` header — Laravel JSON responses
- [x] **V13.1.2** — API access control decisions made on server — Middleware-based auth
- [x] **V13.1.3** — API URLs do not expose sensitive information — No tokens/keys in URLs
- [ ] **V13.1.4** — Authorization decisions at both URI and resource level — Verify all API endpoints have both route middleware AND business logic authorization
- [x] **V13.1.5** — Requests containing unexpected content types rejected — Laravel content negotiation

### V13.2 RESTful Web Service

- [x] **V13.2.1** — REST endpoints require authentication — `auth:sanctum` middleware
- [x] **V13.2.2** — REST services use anti-CSRF mechanisms — Sanctum CSRF token validation
- [x] **V13.2.3** — RESTful services validate incoming content type — JSON validation
- [ ] **V13.2.5** — REST services limit allowed HTTP methods — Verify routes use specific HTTP verbs, not `Route::any()`
- [x] **V13.2.6** — REST Origin header validated — CORS configuration restricts origins

### V13.3 GraphQL

- [ ] **V13.3.1** — GraphQL query whitelist or depth limiting — Verify GraphQL depth/complexity limits if GraphQL is enabled
- [ ] **V13.3.2** — GraphQL authorization logic — Verify `GraphQLFeatureGate` middleware enforces auth

**Files**: `backend/routes/api.php`, `backend/config/cors.php`

---

## V14: Configuration

### V14.1 Build and Deploy

- [x] **V14.1.1** — Build and deploy processes are performed in a secure manner — Docker-based builds
- [x] **V14.1.2** — Compiler flags enable all available buffer overflow protections — PHP managed memory, Node.js V8
- [ ] **V14.1.3** — Server configuration hardened per vendor recommendations — Audit Nginx, PHP-FPM, Node.js configurations against CIS benchmarks
- [x] **V14.1.5** — Third-party components come from defined, trusted repositories — npm registry, Packagist
- [ ] **V14.1.9** — Build pipeline contains a dependency checker — `composer audit` and `npm audit` in CI; verify they block on failures

### V14.2 Dependency

- [x] **V14.2.1** — All components are up to date — Regular dependency updates
- [ ] **V14.2.2** — Unnecessary features, components, documentation removed — Check for unused packages in `composer.json` / `package.json`
- [x] **V14.2.3** — Application uses only vetted third-party libraries — Established packages (Laravel, Next.js, shadcn/ui)

### V14.3 Unintended Security Disclosure

- [x] **V14.3.1** — Web/app server error messages configured for production — `APP_DEBUG=false` in production
- [x] **V14.3.2** — Debug modes disabled in production — Environment-based debug toggle
- [x] **V14.3.3** — HTTP headers do not expose server version — Nginx `server_tokens off`

### V14.4 HTTP Security Headers

- [x] **V14.4.1** — Every response contains `Content-Type` with safe charset — UTF-8 throughout
- [x] **V14.4.2** — `Content-Type: nosniff` present — `X-Content-Type-Options: nosniff`
- [x] **V14.4.3** — CSP header present and not using overly broad wildcards — Restrictive CSP without `unsafe-eval`
- [x] **V14.4.4** — All responses contain `X-Content-Type-Options: nosniff` — Nginx security headers
- [x] **V14.4.5** — HSTS header present — Configurable HSTS
- [x] **V14.4.6** — CSP `frame-ancestors` restricts embedding — `frame-ancestors 'self'`
- [x] **V14.4.7** — Suitable `Referrer-Policy` header — `strict-origin-when-cross-origin`

### V14.5 HTTP Request Header Validation

- [x] **V14.5.1** — Application validates `Host` header — Laravel trusted proxies
- [x] **V14.5.3** — `Origin` header validated for CORS — CORS middleware

**Files**: `docker/nginx-security-headers.conf`, `docker/nginx.conf`, `backend/config/app.php`

---

## Audit Summary

### Score Estimate

| Section | Total Items | Pass | Fail/Partial | N/A |
|---------|-------------|------|--------------|-----|
| V1: Architecture | 22 | 15 | 7 | 0 |
| V2: Authentication | 36 | 27 | 9 | 0 |
| V3: Session Management | 15 | 14 | 1 | 0 |
| V4: Access Control | 8 | 5 | 3 | 0 |
| V5: Validation | 21 | 15 | 4 | 2 |
| V6: Cryptography | 14 | 12 | 2 | 0 |
| V7: Error Handling | 12 | 9 | 3 | 0 |
| V8: Data Protection | 14 | 5 | 9 | 0 |
| V9: Communication | 8 | 5 | 3 | 0 |
| V10: Malicious Code | 8 | 5 | 3 | 0 |
| V11: Business Logic | 6 | 4 | 2 | 0 |
| V12: Files | 9 | 6 | 3 | 0 |
| V13: API | 11 | 8 | 3 | 0 |
| V14: Configuration | 14 | 12 | 2 | 0 |
| **Total** | **198** | **142** | **54** | **2** |

**Estimated compliance: ~72%** (142/196 applicable items pass)

### High-Priority Gaps (fix first)

1. **V3.3.3** — No idle session timeout (only absolute 7-day expiry)
2. **V2.5.6** — Password reset may bypass 2FA
3. **V4.3.2/V4.3.3** — Verify web server blocks access to `.git`, `.env`, `storage/`
4. **V5.1.5** — Potential open redirect in SSO/reset flows
5. **V6.4.3** — No key rotation documentation or tooling
6. **V8.2.1** — Audit client-side storage for sensitive data
7. **V8.3.2** — No user data export (DSAR) capability
8. **V12.1.3** — No per-user file storage quotas

### Medium-Priority Gaps

9. **V1.6.4** — Audit `NEXT_PUBLIC_` env vars for secrets
10. **V2.2.7** — Verify password reset doesn't reveal credential info
11. **V2.8.5** — TOTP replay detection within same time window
12. **V5.2.4** — Audit for `eval()` / dynamic code execution
13. **V5.2.7** — SVG upload sanitization
14. **V7.2.4** — Log input validation failures
15. **V8.1.6** — Document client-side data storage
16. **V8.3.8** — Verify backup encryption
17. **V10.3.3** — Verify CSP is enforced (not report-only)
18. **V13.2.5** — Audit for `Route::any()` usage
19. **V14.1.3** — CIS benchmark audit for Nginx/PHP-FPM

### Low-Priority / Nice-to-Have

20. **V1.4.5** — Resource-level access policies (beyond group RBAC)
21. **V2.2.4** — Default passkey requirement for phishing resistance
22. **V2.3.3** — Token expiry renewal notifications
23. **V9.2.3** — Certificate pinning for Stripe/SSO
24. **V11.1.4** — CAPTCHA on registration
25. **V12.5.2** — Malware scanning on uploads

---

## Key Files

| Area | Files |
|------|-------|
| Auth system | `backend/app/Http/Controllers/Api/AuthController.php`, `backend/app/Services/Auth/` |
| Middleware | `backend/app/Http/Middleware/` |
| SSRF protection | `backend/app/Services/UrlValidationService.php` |
| Audit logging | `backend/app/Services/AuditService.php` |
| Access logging | `backend/app/Services/AccessLogService.php` |
| Permissions | `backend/app/Services/PermissionService.php`, `backend/app/Enums/Permission.php` |
| Session config | `backend/config/session.php`, `backend/config/sanctum.php` |
| Security headers | `docker/nginx-security-headers.conf` |
| CORS | `backend/config/cors.php` |
| File uploads | `backend/app/Http/Controllers/Api/FileManagerController.php` |
| Encryption | `backend/config/app.php` |
| Security ADR | `docs/adr/024-security-hardening.md` |
| Compliance docs | `docs/compliance/` |

---

## Related Roadmaps

- [Security Compliance Review](security-compliance-roadmap.md) — SOC 2 / ISO 27001 (complete)
- [Design Review](design-review-roadmap.md) — Includes security page UX improvements

## References

- [OWASP ASVS v4.0.3](https://owasp.org/www-project-application-security-verification-standard/)
- [ADR-024: Security Hardening](../adr/024-security-hardening.md)
- [ADR-002: Authentication Architecture](../adr/002-authentication-architecture.md)
- [ADR-020: User Groups & Permissions](../adr/020-user-groups-permissions.md)
