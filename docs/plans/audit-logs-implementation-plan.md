# Audit Logs & Logging Implementation Plan

Full implementation plan for the Audit Logs & Logging roadmap. See [audit-logs-roadmap.md](audit-logs-roadmap.md) for the high-level checklist.

## Current State

Infrastructure exists but is **not being used**:
- `backend/app/Models/AuditLog.php` - Model with polymorphic relationships
- `backend/app/Services/AuditService.php` - Basic service (unused)
- `backend/app/Http/Controllers/Api/AuditLogController.php` - API endpoints
- `frontend/app/(dashboard)/configuration/audit/page.tsx` - Frontend page

**Problem:** `AuditService` is never called anywhere - no actual audit logging occurs.

---

## Phase 1: Enhance AuditService (Core Pattern)

Follow the NotificationOrchestrator pattern: simple API, auto-detection, error resilience.

### 1.1 Define Standard Action Types

Standard action format: `{resource}.{action}`

- Auth: auth.login, auth.logout, auth.login_failed, auth.password_reset
- Users: user.created, user.updated, user.deleted, user.disabled, user.enabled, user.admin_granted, user.admin_revoked
- Settings: settings.updated (with group in data)
- Backup: backup.created, backup.restored, backup.deleted
- Email Templates: email_template.updated, email_template.reset

### 1.2 Enhance AuditService

Key improvements:
- Singleton registration in `AppServiceProvider`
- Auto-detect request from app container (no need to pass `$request`)
- Add `severity` support (info, warning, error, critical)
- Add convenience methods: `logAuth()`, `logSettings()`
- Sensitive data filtering (don't log passwords, tokens)
- Error resilience: try/catch so audit never breaks the request

### 1.3 Create AuditLogging Trait

Trait for easy controller integration (similar to ApiResponseTrait). Controllers that `use AuditLogging` can call `$this->audit('user.created', $user, newValues: ['name' => $user->name])`.

### 1.4 Database Enhancement

Add `severity` column to `audit_logs` table (string, default 'info').

---

## Phase 2: Integrate Audit Logging

### 2.1 Authentication Events (HIGH Priority)

File: `backend/app/Http/Controllers/Api/AuthController.php`

- auth.login, auth.login_failed (severity: warning), auth.logout
- auth.password_reset_requested, auth.password_reset
- auth.2fa_enabled / auth.2fa_disabled

### 2.2 User Management (HIGH Priority)

File: `backend/app/Http/Controllers/Api/UserController.php`

- user.created, user.updated, user.deleted
- user.disabled / user.enabled
- user.admin_granted / user.admin_revoked

### 2.3 System Settings (MEDIUM Priority)

Files: SystemSettingController, MailSettingController, SSOSettingController, BackupSettingController

- settings.updated (with group, masked values for secrets)

### 2.4 Backup Operations (MEDIUM Priority)

File: `backend/app/Http/Controllers/Api/BackupController.php`

- backup.created, backup.restored (severity: warning), backup.deleted, backup.downloaded

### 2.5 Email Templates (LOW Priority)

File: `backend/app/Http/Controllers/Api/EmailTemplateController.php`

- email_template.updated, email_template.reset

---

## Phase 3: Frontend Enhancements

File: `frontend/app/(dashboard)/configuration/audit/page.tsx`

- Add user filter dropdown, search, severity badges
- Improve detail view (modal)
- Action type filter with categories
- Statistics: activity trends, by type, by user, warning/error counts

---

## Phase 4: Documentation Updates

- Add AuditService Pattern to `docs/ai/patterns.md`
- Create recipes: `trigger-audit-logging.md`, `add-auditable-action.md`
- Update `docs/ai/context-loading.md` with Audit Logging section
- Update `docs/architecture.md` if ADR created
- Journal entry and roadmap updates

---

## Key Files Summary

| File | Action |
|------|--------|
| `backend/app/Services/AuditService.php` | Enhance |
| `backend/app/Http/Traits/AuditLogging.php` | Create |
| `backend/app/Providers/AppServiceProvider.php` | Modify (singleton) |
| `backend/database/migrations/..._add_severity_to_audit_logs.php` | Create |
| `backend/app/Models/AuditLog.php` | Modify (add severity) |
| `backend/app/Http/Controllers/Api/AuditLogController.php` | Modify (severity filter) |
| Auth, User, Settings, Backup, EmailTemplate controllers | Add logging |
| `frontend/app/(dashboard)/configuration/audit/page.tsx` | Enhance |
| `docs/ai/patterns.md`, recipes, context-loading | Update |

---

## Action Naming Convention

`{resource}.{action}`

Resources: auth, user, settings, backup, email_template, notification  
Actions: created, updated, deleted, enabled, disabled, login, logout, etc.

Examples: auth.login, auth.login_failed, user.created, settings.updated, backup.restored

---

## Verification Checklist

- [ ] AuditService enhanced with auto-request detection
- [ ] AuditLogging trait created and tested
- [ ] Auth events logged
- [ ] User management logged
- [ ] Settings changes logged
- [ ] Backup operations logged
- [ ] Frontend has user filter, severity badges, improved detail view
- [ ] patterns.md updated with AuditService Pattern
- [ ] Recipes created
- [ ] context-loading.md updated
- [ ] Journal entry created
- [ ] Roadmap marked complete
