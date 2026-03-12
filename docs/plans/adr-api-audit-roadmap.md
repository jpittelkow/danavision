# ADR & API Documentation Audit

Systematic audit of all 30 ADRs against API documentation (`docs/api/README.md`, `docs/api/openapi.yaml`) and actual code implementation.

## Summary

- **Total ADRs audited**: 30/30 ✅
- **API doc issues found**: 9 + 27 + 11 + 13 + 5 = 65
- **Implementation gaps found**: 2 + 2 = 4
- **ADR updates needed**: 4 + 2 + 4 + 6 = 16
- **Bugs logged**: 1

## Implementation Journals

- [Batch 2: Communication & Notifications (2026-03-08)](../journal/2026-03-08-adr-api-audit-batch-2.md)
- [Batches 3 & 4: Data & Storage + Features & Integrations (2026-03-08)](../journal/2026-03-08-adr-api-audit-batches-3-4.md)
- [Batch 5: Infrastructure & UI (2026-03-08)](../journal/2026-03-08-adr-api-audit-batch-5.md)

## How to Audit

For each ADR:
1. Read the ADR's Decision/Architecture sections
2. Cross-reference against `backend/routes/api.php` and relevant controllers/services
3. Cross-reference against `docs/api/README.md` and `docs/api/openapi.yaml`
4. Check frontend integration where ADR describes UI
5. Record findings in the table below

**Legend**: ✅ Matches | ⚠️ Partial/minor issues | ❌ Missing or significantly wrong

---

## Batch 1: Auth & Identity

| ADR | Title | Docs Accuracy | ADR Alignment | Implementation Complete | Notes |
|-----|-------|--------------|---------------|------------------------|-------|
| 002 | Authentication Architecture | ✅ | ✅ | ✅ | All endpoints, rate limiting, session auth correct |
| 003 | SSO Provider Integration | ⚠️ | ✅ | ⚠️ | 5 endpoints missing from OpenAPI. `linkedAccounts()` was unrouted — fixed |
| 004 | Two-Factor Authentication | ⚠️ | ✅ | ✅ | Recovery code endpoints missing from OpenAPI. 2FA verify schema had `recovery_code` instead of `is_recovery_code` — fixed |
| 012 | Admin-Only Settings Access | ✅ | ✅ | ✅ | All settings routes use `can:settings.view/edit` middleware |
| 015 | Env-Only Settings | ✅ | ✅ | ✅ | APP_KEY, DB_*, LOG_* correctly excluded from SettingService |
| 018 | Passkey/WebAuthn | ⚠️ | ✅ | ⚠️ | Passkey login used generic `throttle:10,1` instead of `rate.sensitive` — fixed. OpenAPI passkey ID type was integer instead of string — fixed |
| 020 | User Groups & Permissions | ✅ | ✅ | ✅ | 13 permissions, GroupService/PermissionService with caching, all routes permission-gated |
| 024 | Security Hardening | ✅ | ✅ | ✅ | SSRF, backup SQL injection, OAuth state, webhook signatures all implemented |

**Key files**: `AuthController`, `SSOController`, `TwoFactorController`, `PasskeyController`, `GroupController`, `AuthSettingController`

## Batch 2: Communication & Notifications

| ADR | Title | Docs Accuracy | ADR Alignment | Implementation Complete | Notes |
|-----|-------|--------------|---------------|------------------------|-------|
| 005 | Notification System Architecture | ⚠️ | ⚠️ | ✅ | 2 missing endpoints in README, 3 OpenAPI schema errors (Notification.id type, mark-read body field/type), ~15 endpoints missing from docs — all fixed. ADR model schema stale (body→message, channels_sent removed), ntfy missing from channel table, orchestrator signature outdated — all fixed |
| 016 | Email Template System | ✅ | ✅ | ✅ | All 6 endpoints documented in both README and OpenAPI. 4 default templates match seeder. Fully aligned |
| 017 | Notification Template System | ⚠️ | ⚠️ | ✅ | 5 endpoints missing from README — fixed. OpenAPI had all 5. ADR seeder list outdated (6 types → 14 types, 3 channel groups → 4) — fixed |
| 025 | Novu Notification Integration | ⚠️ | ✅ | ✅ | 7 endpoints (1 user + 6 admin) missing from both README and OpenAPI — all fixed |
| 027 | Real-Time Streaming | ⚠️ | ✅ | ✅ | Broadcasting auth endpoint missing from README and OpenAPI — fixed. ADR well-aligned with implementation |

**Key files**: `NotificationController`, `EmailTemplateController`, `NotificationTemplateController`, `NovuSettingController`, `echo.ts`

## Batch 3: Data & Storage

| ADR | Title | Docs Accuracy | ADR Alignment | Implementation Complete | Notes |
|-----|-------|--------------|---------------|------------------------|-------|
| 007 | Backup System Design | ⚠️ | ⚠️ | ✅ | Missing `DELETE /backup/{filename}` in OpenAPI (added); `POST /backup/create` response was 200, fixed to 201; ADR used stale `manage-backups` permission — fixed to `can:backups.*` |
| 010 | Database Abstraction | ✅ | ✅ | ✅ | Architecture ADR only — no API surface. `db:migrate-to` command and Supabase client remain aspirational/unimplemented |
| 014 | Database Settings / Env Fallback | ⚠️ | ✅ | ✅ | Missing System Settings section in README (added); `GET /system-settings/public` and `GET /system-settings/{group}` missing from OpenAPI (added) |
| 021 | Search / Meilisearch | ❌ | ✅ | ✅ | README missing entire Search section (added); OpenAPI `type` enum wrong (fixed to match actual values); `q` incorrectly required (fixed to optional); response schema stale (updated to flat array); test-connection missing body (added); reindex model enum incomplete (added `pages`) |
| 022 | Storage Provider System | ⚠️ | ⚠️ | ⚠️ | ADR endpoint URLs wrong (`/storage/settings` → `/storage-settings`) and 5 endpoints missing — all fixed; Bug: `GET /storage-settings` returns only alert settings (missing provider config) — logged in bug-tracker |

**Key files**: `BackupController`, `BackupSettingController`, `SettingController`, `StorageSettingController`, `SearchService`

## Batch 4: Features & Integrations

| ADR | Title | Docs Accuracy | ADR Alignment | Implementation Complete | Notes |
|-----|-------|--------------|---------------|------------------------|-------|
| 006 | LLM Orchestration Modes | ⚠️ | ⚠️ | ✅ | README missing 6 endpoints (added); OpenAPI missing 5 endpoints (added); config PUT had wrong `primary_provider` field (fixed to `providers` array); vision query schema fixed (`image`/`image_url` alternatives); ADR council config had stale `consensus_threshold` — fixed to `strategy` enum |
| 026 | Stripe Connect Integration | ❌ | ✅ | ✅ | All 14 Stripe endpoints missing from README (added); All 14 endpoints + schemas missing from OpenAPI (added); ADR well-aligned with implementation |
| 028 | Webhook System | ⚠️ | ⚠️ | ✅ | All 7 webhook endpoints missing from README (added); OpenAPI had 6 endpoints (added `GET /webhooks/{webhook}`); `name` missing from create required fields (fixed); ADR table updated to include show endpoint |
| 029 | Usage Tracking & Alerts | ❌ | ⚠️ | ⚠️ | All 3 usage endpoints missing from README (added); All 3 endpoints missing from OpenAPI (added); `payments` missing from controller filter enum (fixed); ADR listed `api` integration type not in model (removed from ADR) |
| 030 | File Manager | ⚠️ | ❌ | ✅ | All 7 file manager endpoints missing from README (added); OpenAPI had endpoints but upload field was `file` (fixed to `files[]`); ADR had completely wrong paths and HTTP methods — all fixed to `/api/storage/files/*` with PUT for rename/move |

**Key files**: `LLMController`, `LLMSettingController`, `StripeConnectController`, `StripePaymentController`, `WebhookController`, `UsageController`, `FileManagerController`

## Batch 5: Infrastructure & UI

| ADR | Title | Docs Accuracy | ADR Alignment | Implementation Complete | Notes |
|-----|-------|--------------|---------------|------------------------|-------|
| 001 | Technology Stack | ✅ | ✅ | ✅ | All stack claims verified: Laravel 11, PHP 8.3, Next.js 16, React 18, SQLite default, Zustand, Tailwind, Sanctum, shadcn/ui |
| 008 | Testing Strategy | ✅ | ⚠️ | ✅ | MSW reference was stale (uses `vi.mock()` instead) — fixed. Coverage targets aspirational (not enforced in CI) |
| 009 | Docker Single-Container | ⚠️ | ⚠️ | ✅ | Process diagram showed 5 services (actual: 8 — missing Reverb, Scheduler, Search-Reindex) — fixed. Health check overclaimed (basic status only) — fixed. Base image `slim` → `alpine` — fixed. Supervisord path — fixed |
| 011 | Global Navigation Architecture | ✅ | ⚠️ | ✅ | Header described "logo/branding" but actually shows breadcrumbs — fixed. Missing localStorage persistence docs, AppShell provider orchestration — fixed |
| 013 | Responsive Mobile-First Design | ✅ | ✅ | ✅ | Pure frontend architecture. Breakpoints, `useIsMobile` hook, mobile-first CSS all accurately documented and implemented |
| 019 | Progressive Web App | ✅ | ✅ | ✅ | All 5 phases implemented. Service worker, push notifications, offline support, install experience, background sync all verified |
| 023 | Audit Logging System | ⚠️ | ✅ | ✅ | OpenAPI schema had fabricated `description`/`metadata` fields instead of actual polymorphic fields — fixed. Export missing `severity`/`correlation_id` params — fixed. Stats endpoint missing permission description — fixed. README used stale "admin ability" language — fixed |

**Key files**: `docker/Dockerfile`, `docker-compose.yml`, `frontend/app/(dashboard)/`, `AuditLogController`, `sw.js`

---

## Issues Found

### API Doc Fixes Needed

**Batch 1 (Auth & Identity):**
1. ~~`POST /auth/check-email` missing from OpenAPI~~ — added
2. ~~`POST /auth/2fa/recovery-codes` missing from OpenAPI~~ — added
3. ~~`POST /auth/2fa/recovery-codes/regenerate` missing from OpenAPI~~ — added
4. ~~`POST /auth/sso/{provider}/link` missing from OpenAPI~~ — added
5. ~~`DELETE /auth/sso/{provider}/unlink` missing from OpenAPI~~ — added
6. ~~`DELETE /profile` path documented as `/profile/delete` in OpenAPI~~ — fixed
7. ~~2FA verify schema used `recovery_code` string instead of `is_recovery_code` boolean~~ — fixed
8. ~~Passkey `{id}` parameter typed as integer instead of string~~ — fixed

### Implementation Gaps

**Batch 1 (Auth & Identity):**
1. ~~`SSOController::linkedAccounts()` unrouted~~ — added `GET /auth/sso/linked-accounts` route
2. ~~Passkey login rate limiting too permissive (`throttle:10,1` vs `rate.sensitive`)~~ — upgraded to `rate.sensitive:passkey`

### ADR Updates Needed

_(None found in Batch 1)_

**Batch 2 (Communication & Notifications):**

**API Doc Fixes:**
1. ~~`POST /notifications/delete-batch` missing from README~~ — added
2. ~~`GET /notifications/diagnose-push` missing from README~~ — added
3. ~~Notification Settings section (5 endpoints) missing from README~~ — added
4. ~~Admin Notification Channels section (4 endpoints) missing from README~~ — added
5. ~~Notification Deliveries section (2 endpoints) missing from README~~ — added
6. ~~User Notification Settings section (8 endpoints) missing from README~~ — added
7. ~~Notification Templates section (5 endpoints) missing from README~~ — added
8. ~~Novu Integration section (7 endpoints) missing from README~~ — added
9. ~~Real-Time Streaming / Broadcasting section missing from README~~ — added
10. ~~OpenAPI `Notification.id` typed as integer instead of string (UUID)~~ — fixed
11. ~~OpenAPI `mark-read` body used `notification_ids` (integer array) instead of `ids` (UUID string array)~~ — fixed
12. ~~All notification-settings, admin notification-channels, notification-deliveries, user notification-settings, Novu, and broadcasting endpoints missing from OpenAPI~~ — added (~25 endpoints)

**ADR Updates:**
1. ~~ADR-005: Notification model schema used `body` instead of `message`, included removed `channels_sent` column~~ — fixed
2. ~~ADR-005: `ntfy` channel missing from Supported Channels table~~ — added
3. ~~ADR-005: NotificationOrchestrator code sample used stale `send(User, Notification)` signature~~ — updated to current signature with `sendByType()` and evolution note
4. ~~ADR-017: Seeder list outdated (6 types × 3 groups → 14 types × 4 groups including email)~~ — updated

**Batch 3 (Data & Storage):**

**API Doc Fixes:**
1. ~~Search section missing entirely from README (6 endpoints)~~ — added
2. ~~System Settings section missing from README (3 endpoints)~~ — added
3. ~~`DELETE /backup/{filename}` missing from OpenAPI~~ — added
4. ~~`POST /backup/create` response code was 200~~ — fixed to 201
5. ~~`GET /system-settings/public` missing from OpenAPI~~ — added
6. ~~`GET /system-settings/{group}` missing from OpenAPI~~ — added
7. ~~Search `type` enum wrong in OpenAPI (`user,page,setting`)~~ — fixed to actual values
8. ~~Search `q` parameter marked `required: true` in OpenAPI~~ — fixed to optional
9. ~~Search response schema stale (grouped format)~~ — updated to flat array with meta
10. ~~`POST /admin/search/test-connection` missing request body schema~~ — added
11. ~~`POST /admin/search/reindex` model enum incomplete~~ — added `pages`

**ADR Updates:**
1. ~~ADR-007: Permission model used `manage-backups` / `manage-settings`~~ — updated to `can:backups.*` / `can:settings.*`
2. ~~ADR-022: All 7 endpoint URLs wrong (`/storage/settings` vs `/storage-settings`), 5 endpoints missing~~ — all fixed and extended

**Implementation Fixes:**
1. ~~`GET /storage-settings` returns incomplete data (missing provider config)~~ — logged as bug in bug-tracker (Low severity)

**Batch 4 (Features & Integrations):**

**API Doc Fixes:**
1. ~~LLM section missing 6 endpoints (provider CRUD + settings GET/PUT/DELETE) from README~~ — added
2. ~~All 14 Stripe endpoints missing from README~~ — added
3. ~~All 7 webhook endpoints missing from README~~ — added
4. ~~All 3 usage endpoints missing from README~~ — added
5. ~~All 7 file manager endpoints missing from README~~ — added
6. ~~OpenAPI missing 5 LLM endpoints (provider CRUD, test, settings reset)~~ — added
7. ~~OpenAPI `PUT /llm/config` body had wrong `primary_provider` field~~ — fixed to `providers` array
8. ~~OpenAPI vision query required `image` (not `image_url` alternative)~~ — fixed to accept both
9. ~~All 14 Stripe endpoints + schemas missing from OpenAPI~~ — added
10. ~~Webhook create missing `name` in required fields~~ — added
11. ~~`GET /webhooks/{webhook}` missing from OpenAPI~~ — added
12. ~~All 3 usage endpoints missing from OpenAPI~~ — added
13. ~~File manager upload field `file` should be `files[]`~~ — fixed

**ADR Updates:**
1. ~~ADR-006: Council config had `consensus_threshold` / `include_dissent`~~ — updated to `strategy` enum
2. ~~ADR-028: Missing `GET /webhooks/{webhook}` from API table~~ — added
3. ~~ADR-029: Listed `api` as integration type not in model~~ — removed
4. ~~ADR-030: All endpoint paths wrong (`/api/files/*` vs `/api/storage/files/*`), rename/move were POST not PUT~~ — all fixed

**Implementation Fixes:**
1. ~~`UsageController` validation excluded `payments` from integration filter enum~~ — fixed in `stats`, `breakdown`, and `export` methods

**Batch 5 (Infrastructure & UI):**

**API Doc Fixes:**
1. ~~OpenAPI `AuditLog` schema had fabricated `description` and `metadata` fields~~ — replaced with actual `auditable_type`, `auditable_id`, `old_values`, `new_values`, `user` relation
2. ~~OpenAPI `/audit-logs/export` missing `severity` and `correlation_id` parameters~~ — added
3. ~~OpenAPI `/audit-logs/stats` missing permission description~~ — added `Requires can:audit.view permission`
4. ~~README Audit Logs section used stale "admin ability" language~~ — updated to `can:audit.view` / `can:logs.export`
5. ~~README `/audit-logs` missing `correlation_id` in filter list; `/audit-logs/export` said "same filters as list" without specifying~~ — explicit filter lists added

**ADR Updates:**
1. ~~ADR-008: Mocking strategy claimed "Mock API responses with MSW"~~ — updated to `vi.mock()` (MSW never installed)
2. ~~ADR-009: Process diagram showed 5 services, actual is 8 (missing Reverb, Scheduler, Search-Reindex)~~ — updated diagram and list
3. ~~ADR-009: Health check claimed to verify database, queue, disk~~ — corrected to basic status-only endpoint
4. ~~ADR-009: Base image `node:20-slim` should be `node:20-alpine`; supervisord path wrong~~ — both fixed
5. ~~ADR-011: Header described as "logo/branding" but shows breadcrumbs~~ — corrected
6. ~~ADR-011: Missing localStorage persistence, AppShell provider orchestration~~ — added to Notes section

---

## Cross-Cutting Checks

- [x] **All routes in `api.php` documented in `README.md`** — Verified. All ~240 routes are present in README.
- [x] **All endpoints in `openapi.yaml` exist in `api.php`** — Verified with 2 exceptions: `/auth/sso/{provider}` is a web route (noted in OpenAPI), `/broadcasting/auth` is a Laravel-internal route.
- [x] **No orphaned endpoints** — Fixed: removed duplicate `/notification-settings` and `/user/notification-settings` definitions in OpenAPI (stale copies from earlier batch additions). ~25 endpoints exist in api.php but are not in OpenAPI (onboarding, graphql admin, user API keys, branding mutations, client-errors, etc.) — these are lower-priority and can be added incrementally.
- [ ] Rate limiting documented matches middleware applied — Not checked (out of scope for ADR audit)
- [ ] Permission/middleware requirements match docs — Spot-checked during per-ADR audit; comprehensive check deferred
- [ ] Error response schemas in OpenAPI match actual error responses — Deferred (would require running all endpoints)
