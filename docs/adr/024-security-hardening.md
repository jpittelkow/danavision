# ADR-024: Security Hardening

## Status

Accepted

## Date

2026-02-01

## Context

A security audit identified 9 vulnerabilities across the codebase ranging from critical to low severity. These included:

1. **SSRF vulnerabilities** - Multiple endpoints fetched user-provided URLs without validating they didn't point to internal resources (localhost, private IPs, cloud metadata endpoints like 169.254.169.254)
2. **SQL injection in backup restore** - The backup restore function used `DB::unprepared()` with regex validation that could potentially be bypassed
3. **2FA session bypass** - The 2FA middleware checked for a session flag that was never set after verification
4. **OAuth CSRF vulnerability** - SSO callback didn't validate the OAuth state parameter
5. **Webhook signatures missing** - The webhook secret field existed but was never used to sign payloads
6. **Weak password policy** - No password complexity requirements beyond 8 characters minimum
7. **Tokens never expire** - Sanctum API tokens had no expiration configured
8. **Weak admin password reset** - Admin password reset had minimal validation
9. **File upload allows any type** - Empty file type whitelist allowed any file upload

## Decision

### 1. SSRF Protection via UrlValidationService

Created a reusable `UrlValidationService` that:
- Validates URLs against private IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, etc.)
- Blocks cloud metadata endpoints (169.254.169.254)
- Only allows http/https schemes
- Resolves hostnames to check all IPs (prevents DNS rebinding)
- Validates redirect targets during fetch operations

Integrated into:
- LLM providers (GeminiProvider, BedrockProvider) for image URL fetching
- WebhookController for webhook URL validation
- SSOSettingController for OIDC issuer URL validation

### 2. Backup Restore SQL Injection Fix

Changed backup format from raw SQL to JSON for MySQL/PostgreSQL:
- New backups export tables as JSON with integrity hash (SHA-256)
- Restore uses `DB::table()->updateOrInsert()` with proper parameter binding
- Legacy SQL format still supported (read-only) via strict parsing into parameterized queries
- SQLite continues using file replacement (safe, no SQL execution)

### 3. 2FA Session Flag

Added `$request->session()->put('2fa:verified', true)` after successful 2FA verification so the `Ensure2FAVerified` middleware can properly validate.

### 4. OAuth State Validation

Modified SSO flow to include CSRF protection:
- `getRedirectUrl()` generates cryptographic state token using `bin2hex(random_bytes(32))`
- Token stored in session keyed by provider
- `validateStateToken()` validates with timing-safe comparison (`hash_equals`)
- Callback rejects requests with missing or invalid state

### 5. Webhook Signatures

Implemented HMAC-SHA256 signatures for webhooks:
- `X-Webhook-Timestamp`: Unix timestamp of request
- `X-Webhook-Signature`: `sha256=<hmac of timestamp.payload>`
- Signature computed over `{timestamp}.{json_payload}` to prevent replay attacks

### 6. Password Policy

Configured `Password::defaults()` in AppServiceProvider:
- Minimum 8 characters
- Mixed case required
- Numbers required
- Symbols required
- Compromised password check in production (via Have I Been Pwned API)

Applied to all password fields in UserController.

### 7. Sanctum Token Expiration

Changed `config/sanctum.php` expiration from `null` to `env('SANCTUM_TOKEN_EXPIRATION', 10080)` (7 days in minutes).

### 8. File Upload Whitelist

Added default file type whitelist when none configured:
- Documents: pdf, doc, docx, xls, xlsx, ppt, pptx, odt, ods, odp, rtf, txt, csv
- Images: jpg, jpeg, png, gif, bmp, webp, svg, ico, tiff, tif
- Audio: mp3, wav, ogg, flac, aac, m4a
- Video: mp4, webm, avi, mov, mkv, wmv
- Archives: zip, rar, 7z, tar, gz
- Other: json, xml, yaml, yml, md

Added MIME type validation to prevent extension spoofing.

## Consequences

### Positive

- Prevents SSRF attacks against internal infrastructure and cloud metadata services
- Eliminates SQL injection risk in backup restore
- Properly enforces 2FA on protected routes
- Prevents OAuth CSRF attacks
- Enables webhook consumers to verify payload authenticity
- Enforces strong passwords reducing credential stuffing risk
- API tokens now expire, limiting breach window
- Blocks potentially dangerous file uploads by default

### Negative

- Backup format change requires migration path for existing backups
- Password policy may cause friction for users with weak existing passwords
- Token expiration requires clients to handle refresh
- MIME validation may reject some edge-case valid files

### Neutral

- UrlValidationService adds slight latency to URL fetches (DNS resolution)
- Webhook consumers must implement signature verification to benefit

## Related Decisions

- [ADR-002: Authentication Architecture](./002-authentication-architecture.md)
- [ADR-003: SSO Provider Integration](./003-sso-provider-integration.md)
- [ADR-004: Two-Factor Authentication](./004-two-factor-authentication.md)
- [ADR-007: Backup System Design](./007-backup-system-design.md)

## Notes

### Webhook Signature Verification

Consumers should verify signatures as follows:

```php
$timestamp = $request->header('X-Webhook-Timestamp');
$signature = $request->header('X-Webhook-Signature');
$payload = $request->getContent();

$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

if (!hash_equals($expected, $signature)) {
    abort(401, 'Invalid signature');
}

// Optionally check timestamp is recent (e.g., within 5 minutes)
if (abs(time() - $timestamp) > 300) {
    abort(401, 'Request too old');
}
```

### Password Policy Override

In development, the compromised password check is disabled to avoid API calls. The policy can be customized via AppServiceProvider if needed.

## Implementation Journal

- [Security Vulnerability Fixes (2026-02-01)](../journal/2026-02-01-security-vulnerability-fixes.md)
- [Security Review Phase 1 (2026-02-02)](../journal/2026-02-02-security-review-phase-1.md)
- [Security SAST Automation (2026-02-02)](../journal/2026-02-02-security-sast-automation.md)
- [Security Page Cleanup (2026-02-04)](../journal/2026-02-04-security-page-cleanup.md)
- [Phase 1 Security Review (2026-02-05)](../journal/2026-02-05-phase-1-security-review.md)
- [Security Compliance Documentation Completion (2026-02-05)](../journal/2026-02-05-security-compliance-documentation-completion.md)
- [GitHub Actions Hardening (2026-02-05)](../journal/2026-02-05-github-actions-hardening.md)
- [Compliance Templates (2026-02-05)](../journal/2026-02-05-compliance-templates.md)
