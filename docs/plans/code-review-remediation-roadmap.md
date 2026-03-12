# Code Review Remediation Roadmap

Comprehensive remediation plan from a full-repository code review covering backend (Laravel), frontend (Next.js), and infrastructure/security. Findings organized into 5 phases by severity and effort.

**Priority**: HIGH
**Status**: Phase 5 Complete
**Created**: 2026-03-03
**Estimated Total Effort**: ~40-50 hours

**Dependencies**:
- [Security Compliance Roadmap](security-compliance-roadmap.md) - Overlaps with security hardening
- [Docker Audit Roadmap](docker-audit-roadmap.md) - Overlaps with container hardening

---

## Task Checklist

### Phase 1: Critical Security Fixes (Day 1 — ~2 hours)
- [x] Encrypt webhook secrets and hide from API responses
- [x] Remove stack trace exposure from `diagnosePush` endpoint
- [x] Fix or remove email enumeration endpoint (`/api/auth/check-email`)
- [x] Auto-generate Reverb WebSocket credentials in entrypoint
- [x] Remove hardcoded Stripe test key from frontend source
- [x] Add `isAdminUser` check to `usePermission` hook
- [x] Sanitize custom CSS injection in `AppConfigProvider`

### Phase 2: High-Priority Hardening (Week 1 — ~8 hours)
- [x] Add `$hidden` to Webhook model for secret field
- [x] Fix `AccessLogController::deleteAll` permission (`logs.view` → `logs.delete`)
- [x] Validate user settings against an allowlist schema
- [x] Validate system settings group/key against `settings-schema.php`
- [x] Escape LIKE metacharacters in all query locations
- [x] Use `cursor()`/`chunk()` for audit/access log exports
- [x] Add `server_tokens off` to Nginx config
- [x] Add HSTS header to security headers
- [x] Remove `unsafe-eval` from CSP (evaluate `unsafe-inline`)
- [x] Set `SESSION_SECURE_COOKIE=true` in production Dockerfile
- [x] Add `no-new-privileges` to docker-compose.yml
- [x] Remove dev tools from production Docker image
- [x] Fix timer leaks in `usePageTitle` hook
- [x] Fix search highlight `dangerouslySetInnerHTML` fallback
- [x] Wrap `fetchGroups` in `useCallback` in `useGroups` hook
- [x] Fix stale redirect flag in auth store

### Phase 3: Medium-Priority Improvements (Weeks 2-3 — ~15 hours)
- [x] Add transaction lock to first-user admin registration
- [x] Consolidate user deletion into `UserService::deleteUser()`
- [x] Add audit log to `UserController::resetPassword`
- [x] Remove 2FA/status fields from User `$fillable`, use `forceFill()`
- [x] Fix rate limiter to only count failed attempts
- [x] Standardize API response format (adopt `ApiResponseTrait` everywhere)
- [x] Extract Form Request classes for MailSetting and LLM validation
- [x] Define allowed options per command in `JobController::run`
- [x] Fix `StripePaymentController::show` authorization consistency
- [x] Fix dead link `/configuration/mail` → `/configuration/email`
- [x] Replace duplicate `User` type with shared `AdminUser extends User`
- [x] Replace `console.error` with `errorLogger` in error boundaries
- [x] Replace deprecated `navigator.platform` usage
- [x] Type the `login` method return as `Promise<LoginResult>`
- [x] Remove `any` types from notification channel field renderer
- [x] Add debouncing to user search input
- [x] Remove `as any` from dynamic form population (use `reset()`)
- [x] Add SHA256 verification for Meilisearch binary download
- [x] Add `composer audit` and `npm audit` to CI pipeline
- [x] Enable session encryption for production
- [x] Add Nginx-level rate limiting
- [x] Bind Reverb to `127.0.0.1` instead of `0.0.0.0`
- [x] Add sensitive file detection to `push.ps1`
- [x] Warn when GraphQL CORS wildcard is used in production

### Phase 4: Low-Priority Cleanup (Weeks 3-4 — ~10 hours)
- [x] Fix auth tests to use plaintext passwords (rely on model cast)
- [x] Add deprecation headers to legacy routes, plan removal
- [x] Replace hardcoded pagination in `StripePaymentController`
- [x] Modernize `Notification` model from `boot()` to `booted()`
- [x] Modernize `UserGroup` from `$casts` property to `casts()` method
- [x] Add return types to model scopes
- [x] Clean up dual API token system (`ApiTokenController` vs `ApiKeyController`)
- [x] Move `formatBytes` from controller to utility
- [x] Remove dead TODO/fallback code in sidebar
- [x] Standardize error display patterns across pages
- [x] Fix missing `useEffect` dependencies across hooks
- [x] Fix `parseInt` safety on email port value
- [x] Remove pointless catch-rethrow in `web-push.ts`
- [x] Store or remove `setupOfflineRetry` cleanup function
- [x] Rename `Notification` type to `AppNotification` to avoid shadow
- [x] Use `npm ci` instead of `npm install` in Dockerfile
- [x] Run migrations as `www-data` or add error trap in entrypoint
- [x] Configure log rotation for supervisor queue/scheduler
- [x] Move platform-specific SWC to `optionalDependencies`
- [x] Remove or gate `console.error` in production service worker

### Phase 5: Test Coverage Expansion (Ongoing — ~15 hours)
- [x] Add frontend tests for `usePermission` / `PermissionGate`
- [x] Add frontend tests for `sanitizeHighlight` and `sanitizeCss`
- [x] Add frontend tests for auth store (2FA and error paths)
- [x] Add frontend tests for form validation schemas (Zod)
- [x] Add frontend tests for `formatDate`/`formatDateTime` with timezones
- [x] Add backend tests for `UserController` (CRUD, toggle admin, disable)
- [x] Add backend tests for `ProfileController` (self-update, self-delete)
- [x] Add backend tests for `WebhookController` (CRUD, SSRF, delivery)
- [x] Add backend tests for `SettingController` (arbitrary key rejection)
- [x] Add backend tests for `SystemSettingController`
- [x] Add backend tests for `FileManagerController` (path traversal)
- [x] Add backend tests for `JobController` (option validation)
- [x] Add backend tests for `MailSettingController`
- [x] Add backend tests for `AccessLogController` (permission check)
- [x] Add backend tests for `StripeConnectController`
- [x] Set up Dependabot for composer and npm dependencies
- [x] Add `npm test` step to CI workflow (was missing)
- [x] Create `WebhookFactory` for test support

---

## Phase 1: Critical Security Fixes

**Target**: Day 1 | **Effort**: ~2 hours | **Risk if skipped**: Active exploitability

### 1.1 Encrypt Webhook Secrets and Hide from API Responses

**Files**:
- `backend/app/Models/Webhook.php`
- `backend/app/Http/Controllers/Api/WebhookController.php`

**Problem**: Webhook signing secrets stored as plaintext in the database with no `encrypted` cast and no `$hidden` property. Secrets are returned in all API responses (index, store, update). Compare with `AIProvider` which correctly uses `'api_key' => 'encrypted'` and `$hidden = ['api_key']`.

**Fix**:
1. Add `'secret' => 'encrypted'` to `casts()` in `Webhook.php`
2. Add `protected $hidden = ['secret'];` to `Webhook.php`
3. In `WebhookController::store`, return the secret only on creation (append it manually to the response)
4. In `WebhookController::index` and `update`, return `secret_set: true/false` instead

---

### 1.2 Remove Stack Trace Exposure from `diagnosePush`

**File**: `backend/app/Http/Controllers/Api/NotificationController.php:215-220`

**Problem**: Returns `$e->getTraceAsString()` to any authenticated user in non-production environments. Exposes file paths, class names, library versions.

**Fix**: Remove the `trace` key entirely from the error response. Stack traces belong in server-side logs only.

```php
// Before
'trace' => app()->isProduction() ? null : $e->getTraceAsString(),

// After — remove the trace key entirely
```

---

### 1.3 Fix or Remove Email Enumeration Endpoint

**File**: `backend/app/Http/Controllers/Api/AuthController.php:41-49`

**Problem**: `/api/auth/check-email` explicitly reveals whether an email is registered. Rate limiting (10/min) is insufficient — an attacker can enumerate 14,400 emails/day from a single IP, and trivially bypass IP throttling with proxies. This undermines the careful enumeration protection in `forgotPassword` (line 235).

**Options**:
- **Option A**: Remove the endpoint entirely. Validate email uniqueness only during registration submission.
- **Option B**: Always return `available: true`. Show "email taken" only on actual registration attempt.
- **Option C**: Keep but tighten — require valid CSRF session, reduce rate to 3/5min, add constant-time delay.

---

### 1.4 Auto-Generate Reverb WebSocket Credentials

**Files**:
- `docker/Dockerfile:57-59`
- `docker/entrypoint.sh`
- `docker-compose.yml:57`

**Problem**: Reverb credentials (`sourdough-key`/`sourdough-secret`) are hardcoded in Dockerfile, docker-compose, and .env.example. All default deployments share the same WebSocket auth credentials. Contrast with `APP_KEY` and `MEILI_MASTER_KEY` which are properly auto-generated in `entrypoint.sh`.

**Fix**: Add credential auto-generation to `entrypoint.sh` following the existing `APP_KEY`/`MEILI_MASTER_KEY` pattern. Persist to data volume files with `chmod 600`. Note: `NEXT_PUBLIC_REVERB_APP_KEY` is baked into the frontend at build time — either document this limitation or add a runtime API endpoint to provide the key.

---

### 1.5 Remove Hardcoded Stripe Test Key

**Problem**: A Stripe publishable test key (`pk_test_...`) is committed in frontend source code. Even though it's a test/publishable key, it should come from environment configuration.

**Fix**: Move to an environment variable (`NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY`) and reference via app config.

---

### 1.6 Fix Admin Permission Bypass in `usePermission`

**Problem**: The `usePermission` hook does not check `isAdminUser(user)`, meaning admin users may not get the expected blanket permission bypass in frontend permission checks.

**Fix**: Add `isAdminUser` check at the top of the hook to return `true` for admin users, consistent with the backend's `PermissionService` behavior.

---

### 1.7 Sanitize Custom CSS in `AppConfigProvider`

**Problem**: Custom CSS from settings is injected into the page without sanitization, potentially allowing CSS-based attacks (data exfiltration via `url()`, UI redressing).

**Fix**: Use the existing `sanitizeCss()` function from `frontend/lib/sanitize.ts` before injecting any custom CSS into `<style>` tags.

---

## Phase 2: High-Priority Hardening

**Target**: Week 1 | **Effort**: ~8 hours | **Risk if skipped**: Data exposure, auth bypass, DoS vectors

### 2.1 Fix `AccessLogController::deleteAll` Permission

**File**: `backend/routes/api.php:316`

A destructive DELETE operation (`deleteAll`) is protected by read-only `logs.view` permission. Change to `settings.edit` or a dedicated `logs.delete` permission. **2-minute fix.**

### 2.2 Validate Settings Against Schema

**Files**: `backend/app/Http/Controllers/Api/SettingController.php`, `SystemSettingController.php`

Both user settings and system settings accept arbitrary group/key/value combinations with no allowlist. Create a user settings schema and validate against it. For system settings, reject keys not defined in `config/settings-schema.php`.

### 2.3 Escape LIKE Metacharacters

**Files**: `NotificationDeliveryController.php`, `AuditService.php`, `UserController.php`, `SearchService.php`, `ApiKeyService.php`

Create a shared `escapeLike()` helper and apply to all 10+ LIKE query locations:
```php
function escapeLike(string $value): string {
    return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
}
```

### 2.4 Stream Large Exports

**Files**: `AuditService.php:189-192`, `AccessLogService.php`

Replace `->get()` with `->cursor()` or `->chunk()` for CSV export queries. Add a maximum date range (e.g., 90 days) to prevent unbounded queries.

### 2.5 Nginx Security Headers

**Files**: `docker/nginx.conf`, `docker/nginx-security-headers.conf`

- Add `server_tokens off;` in `http` block
- Add `Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"` header
- Remove `'unsafe-eval'` from CSP `script-src`
- Evaluate removing `'unsafe-inline'` (replace with nonce-based CSP if Next.js supports it)

### 2.6 Production Cookie & Session Security

**File**: `docker/Dockerfile` ENV block

- Add `SESSION_SECURE_COOKIE=true`
- Add `SESSION_ENCRYPT=true`

### 2.7 Docker Container Hardening

**Files**: `docker-compose.yml`, `docker/Dockerfile`

- Add `security_opt: [no-new-privileges:true]` to docker-compose service
- Remove dev packages from production image (`git`, `npm`, all `-dev` packages) after compilation

### 2.8 Frontend Hook Fixes

**Files**: `frontend/lib/use-page-title.ts`, `frontend/components/search/search-modal.tsx`, `frontend/lib/use-groups.ts`

- Reduce `usePageTitle` to a single `document.title` set + one deferred update, clean up all timers
- Use text content (not `dangerouslySetInnerHTML`) for non-highlighted search result fallbacks
- Wrap `fetchGroups` in `useCallback`

---

## Phase 3: Medium-Priority Improvements

**Target**: Weeks 2-3 | **Effort**: ~15 hours | **Risk if skipped**: Edge-case bugs, compliance gaps, code quality

### 3.1 Backend Fixes

| Fix | File | Effort |
|-----|------|--------|
| Transaction lock on first-user registration | `AuthController.php:64-79` | 30 min |
| Consolidate user deletion logic | `ProfileController.php`, `UserController.php` | 1 hr |
| Audit log for admin password reset | `UserController.php:203-213` | 15 min |
| Remove 2FA fields from `$fillable` | `User.php:37-48` | 30 min |
| Fix rate limiter (count only failures) | `RateLimitSensitive.php:24-46` | 30 min |
| Standardize `ApiResponseTrait` usage | 8 controllers | 2 hrs |
| Extract Form Request classes | `MailSettingController`, `LLMController` | 1 hr |
| Validate `JobController::run` options | `JobController.php:123-137` | 30 min |
| Fix `StripePaymentController::show` auth | `StripePaymentController.php:38-46` | 15 min |

### 3.2 Frontend Fixes

| Fix | File | Effort |
|-----|------|--------|
| Fix dead link `/configuration/mail` | `configuration/system/page.tsx:259` | 1 min |
| Shared `AdminUser extends User` type | `configuration/users/page.tsx`, `lib/auth.ts` | 15 min |
| Error boundaries use `errorLogger` | 3 error boundary files | 10 min |
| Replace `navigator.platform` | `header.tsx`, `preferences/page.tsx` | 15 min |
| Type `login` return properly | `lib/auth.ts:48` | 10 min |
| Remove `any` types in notifications | `notifications/page.tsx:476-486` | 20 min |
| Debounce user search | `users/page.tsx:94-97` | 15 min |
| Use `reset()` instead of `as any` setValue | `system/page.tsx:165-170` | 10 min |

### 3.3 Infrastructure Fixes

| Fix | File | Effort |
|-----|------|--------|
| SHA256 verify Meilisearch download | `Dockerfile:109-113` | 10 min |
| Add `composer audit` + `npm audit` to CI | `.github/workflows/ci.yml` | 10 min |
| Nginx-level rate limiting | `docker/nginx.conf` | 15 min |
| Bind Reverb to `127.0.0.1` | `docker/supervisord.conf:66` | 2 min |
| Sensitive file detection in `push.ps1` | `scripts/push.ps1:99` | 15 min |
| Warn on GraphQL wildcard CORS in prod | `GraphQLCors.php:22-24` | 10 min |

---

## Phase 4: Low-Priority Cleanup

**Target**: Weeks 3-4 | **Effort**: ~10 hours | **Risk if skipped**: Tech debt, inconsistency

### 4.1 Backend Cleanup
- Fix auth tests to use plaintext passwords (not `bcrypt()`)
- Add deprecation headers to legacy notification routes
- Use config-based pagination in `StripePaymentController`
- Modernize `Notification::boot()` → `booted()`, `UserGroup::$casts` → `casts()`
- Add `Builder` return types to model scopes
- Review dual API token systems for consolidation
- Move `DashboardController::formatBytes` to a utility

### 4.2 Frontend Cleanup
- Remove dead TODO fallback code in `sidebar.tsx:56-58`
- Standardize error display patterns (toast vs inline vs FormField)
- Fix missing `useEffect` dependencies across hooks (with `useCallback`)
- Add `Number.isFinite()` safety check on email port `parseInt`
- Remove pointless catch-rethrow in `web-push.ts:106-108`
- Handle or remove `setupOfflineRetry` cleanup function
- Rename `Notification` → `AppNotification` to avoid browser API shadow

### 4.3 Infrastructure Cleanup
- Use `npm ci --ignore-optional` instead of `npm install` in Dockerfile
- Run migrations as `www-data` or add `trap` for permission fix on error
- Add explicit log rotation config for supervisor queue/scheduler
- Move `@next/swc-linux-x64-gnu` to `optionalDependencies`
- Remove or gate `console.error` in production service worker

---

## Phase 5: Test Coverage Expansion

**Target**: Ongoing | **Effort**: ~15 hours | **Risk if skipped**: Regressions go undetected

### 5.1 Frontend Tests (Priority Order)

| Test Area | Why | Effort |
|-----------|-----|--------|
| `usePermission` / `PermissionGate` | Authorization correctness — would have caught C5 | 2 hrs |
| `sanitizeHighlight` / `sanitizeCss` | Security functions need coverage | 1 hr |
| Auth store (2FA, error paths) | Existing tests only cover happy path | 1 hr |
| Zod form validation schemas | Edge cases for each schema | 2 hrs |
| `formatDate`/`formatDateTime` | Timezone handling is error-prone | 1 hr |

### 5.2 Backend Tests (Priority Order)

| Controller | Security-Sensitive Operations | Effort |
|------------|-------------------------------|--------|
| `UserController` | CRUD, toggle admin, disable, IDOR | 2 hrs |
| `WebhookController` | CRUD, secret handling, SSRF | 1 hr |
| `FileManagerController` | Path traversal, upload validation | 1 hr |
| `SettingController` | Arbitrary key rejection | 1 hr |
| `JobController` | Option validation, permission | 30 min |
| `ProfileController` | Self-delete cascade completeness | 30 min |
| `MailSettingController` | Provider-specific validation | 30 min |
| `AccessLogController` | Delete permission check | 15 min |

### 5.3 CI Additions
- Set up Dependabot for `composer` and `npm` dependencies
- Add `composer audit` and `npm audit` as CI steps (also in Phase 3)

---

## Positive Patterns to Preserve

These demonstrate strong engineering and should be maintained as standards:

- **SSRF protection** — DNS pinning, private IP blocking in `UrlValidationService`
- **Backup filename validation** — strict regex, no path traversal possible
- **Sanctum auth** — session regeneration, CSRF, cookie security
- **DOMPurify** — restrictive allowlist for HTML sanitization
- **Correlation IDs** — end-to-end request tracing
- **Encrypted API keys** — `AIProvider` model uses `encrypted` cast + `$hidden`
- **Pinned GitHub Actions** — SHA-based references for supply chain security
- **Lazy-loaded dependencies** — dynamic `import()` for Pusher/Echo
- **Error logger** — structured reporting with correlation IDs
- **Zod validation** — consistent schema-based form validation
- **CSRF exemption documentation** — clear comments on every exemption
- **Mobile-first responsive** — consistent breakpoint usage

---

## Files Reference

**Backend — Security Critical**:
- `backend/app/Models/Webhook.php` — encrypt + hide secret
- `backend/app/Http/Controllers/Api/AuthController.php` — email enumeration
- `backend/app/Http/Controllers/Api/NotificationController.php` — stack trace
- `backend/app/Models/User.php` — mass assignment protection
- `backend/app/Http/Middleware/RateLimitSensitive.php` — rate limiter fix
- `backend/routes/api.php` — permission fixes

**Frontend — Security Critical**:
- `frontend/lib/use-permission.ts` — admin bypass
- `frontend/lib/app-config.tsx` — CSS sanitization
- `frontend/components/search/search-modal.tsx` — XSS via dangerouslySetInnerHTML

**Infrastructure — Security Critical**:
- `docker/Dockerfile` — credential generation, dev tools removal
- `docker/entrypoint.sh` — Reverb credential auto-generation
- `docker/nginx.conf` — server_tokens, rate limiting
- `docker/nginx-security-headers.conf` — HSTS, CSP hardening
- `docker/supervisord.conf` — Reverb binding
- `docker-compose.yml` — no-new-privileges
