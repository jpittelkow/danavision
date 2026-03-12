# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quick Context (always loaded)

- **Stack**: Laravel 11 (PHP 8.3+) + Next.js 16 (React 18, TypeScript) + SQLite default
- **Architecture**: Single Docker container (Nginx + PHP-FPM + Next.js via Supervisor), API-first
- **Backend**: routes in `backend/routes/api.php`, controllers in `backend/app/Http/Controllers/Api/`, services in `backend/app/Services/`
- **Frontend**: pages in `frontend/app/(dashboard)/`, components in `frontend/components/`, utilities in `frontend/lib/`
- **Config pages**: `frontend/app/(dashboard)/configuration/`, nav in `configuration/layout.tsx` (`navigationGroups`)
- **Settings schema**: `backend/config/settings-schema.php` (system settings via SettingService), `backend/config/user-settings-schema.php` (user settings allowlist)
- **Search registration**: dual — `backend/config/search-pages.php` + `frontend/lib/search-pages.ts`

## Development Commands

**PHP is not available locally** — run all backend commands via Docker (`sourdough-dev` container).

```bash
# Start/rebuild dev environment
docker-compose up -d
docker-compose up -d --build

# Backend tests (Pest) — all tests
docker exec sourdough-dev bash -c "cd /var/www/html/backend && php artisan test"

# Backend — single test file
docker exec sourdough-dev bash -c "cd /var/www/html/backend && php artisan test --filter=AuthTest"

# Backend — single test method
docker exec sourdough-dev bash -c "cd /var/www/html/backend && php artisan test --filter='it can login with valid credentials'"

# Backend — Laravel commands
docker exec sourdough-dev bash -c "cd /var/www/html/backend && php artisan migrate"
docker exec sourdough-dev bash -c "cd /var/www/html/backend && php artisan route:list"

# Frontend tests (Vitest)
docker exec sourdough-dev bash -c "cd /var/www/html/frontend && npm test"

# Frontend lint
docker exec sourdough-dev bash -c "cd /var/www/html/frontend && npm run lint"

# Frontend build
docker exec sourdough-dev bash -c "cd /var/www/html/frontend && npm run build"

# E2E tests (Playwright)
docker exec sourdough-dev bash -c "cd /var/www/html/frontend && npm run test:e2e"

# Add shadcn component (from frontend/)
docker exec sourdough-dev bash -c "cd /var/www/html/frontend && npx shadcn@latest add <component>"

# Release (bumps version, runs tests, tags, pushes)
./scripts/push.ps1 patch "feat: description of changes"
```

## Task-Type File Lookup

| Task Type | Key Files & Docs |
|-----------|-----------------|
| **Full-Stack Feature** | [add-full-stack-feature recipe](docs/ai/recipes/add-full-stack-feature.md) |
| Frontend UI | `frontend/app/(dashboard)/`, `frontend/components/`, `frontend/lib/api.ts` |
| Backend API | `backend/routes/api.php`, `backend/app/Http/Controllers/Api/` |
| Config Page | [add-config-page recipe](docs/ai/recipes/add-config-page.md), [add-configuration-menu-item recipe](docs/ai/recipes/add-configuration-menu-item.md) |
| Settings (SettingService) | [ADR-014](docs/adr/014-database-settings-env-fallback.md), `backend/app/Services/SettingService.php`, `backend/config/settings-schema.php` |
| Notifications | [ADR-005](docs/adr/005-notification-system-architecture.md), `backend/app/Services/Notifications/`, [trigger-notifications recipe](docs/ai/recipes/trigger-notifications.md) |
| LLM | [ADR-006](docs/adr/006-llm-orchestration-modes.md), `backend/app/Services/LLM/` |
| Auth | [ADR-002](docs/adr/002-authentication-architecture.md), `backend/app/Http/Controllers/Api/AuthController.php` |
| Backup | [ADR-007](docs/adr/007-backup-system-design.md), `backend/app/Services/Backup/BackupService.php` |
| Payments/Stripe | [ADR-026](docs/adr/026-stripe-integration.md), `backend/app/Services/Stripe/`, [setup-stripe recipe](docs/ai/recipes/setup-stripe.md) |
| Search | `backend/app/Services/Search/SearchService.php`, `frontend/lib/search.ts` |
| Help/Docs | `frontend/lib/help/help-content.ts`, `frontend/components/help/` |
| Docker | [ADR-009](docs/adr/009-docker-single-container.md), `docker/Dockerfile`, `docker-compose.yml` |
| Testing | [ADR-008](docs/adr/008-testing-strategy.md), `e2e/`, `backend/tests/` |
| User Groups/Permissions | [ADR-020](docs/adr/020-user-groups-permissions.md), `backend/app/Services/GroupService.php`, `backend/app/Services/PermissionService.php` |
| Audit Logging | [ADR-023](docs/adr/023-audit-logging-system.md), `backend/app/Services/AuditService.php`, [trigger-audit-logging recipe](docs/ai/recipes/trigger-audit-logging.md) |
| Access Logging (HIPAA) | `backend/app/Services/AccessLogService.php`, `backend/app/Http/Middleware/LogResourceAccess.php`, [add-access-logging recipe](docs/ai/recipes/add-access-logging.md) |
| Application Logging | `backend/app/Http/Middleware/AddCorrelationId.php`, `backend/app/Services/AppLogExportService.php`, [extend-logging recipe](docs/ai/recipes/extend-logging.md) |
| Email Templates | [ADR-016](docs/adr/016-email-template-system.md), `backend/app/Services/EmailTemplateService.php`, [add-email-template recipe](docs/ai/recipes/add-email-template.md) |
| Notification Templates | [ADR-017](docs/adr/017-notification-template-system.md), `backend/app/Services/NotificationTemplateService.php`, [add-notification-template recipe](docs/ai/recipes/add-notification-template.md) |
| SSO | [ADR-003](docs/adr/003-sso-provider-integration.md), `backend/app/Services/Auth/SSOService.php`, [add-sso-provider recipe](docs/ai/recipes/add-sso-provider.md) |
| Security Settings | `backend/app/Http/Controllers/Api/AuthSettingController.php`, `frontend/app/(dashboard)/configuration/security/page.tsx` |
| Suspicious Activity | `backend/app/Services/SuspiciousActivityService.php`, `backend/app/Console/Commands/CheckSuspiciousActivityCommand.php` |
| Scheduled Jobs | `backend/app/Services/ScheduledTaskService.php`, `backend/app/Http/Controllers/Api/JobController.php` |
| PWA | [PWA roadmap](docs/plans/pwa-roadmap.md), `frontend/public/sw.js` |
| Mobile/Responsive | [ADR-013](docs/adr/013-responsive-mobile-first-design.md), `frontend/lib/use-mobile.ts` |
| Branding | `frontend/config/app.ts`, `frontend/components/logo.tsx`, `frontend/lib/app-config.tsx` |
| Real-Time Streaming | [ADR-027](docs/adr/027-real-time-streaming.md), `frontend/lib/echo.ts`, [add-real-time-streaming recipe](docs/ai/recipes/add-real-time-streaming.md) |
| Webhooks | [ADR-028](docs/adr/028-webhook-system.md), `backend/app/Services/WebhookService.php`, [add-webhook recipe](docs/ai/recipes/add-webhook.md) |
| Usage Tracking | [ADR-029](docs/adr/029-usage-tracking-alerts.md), `backend/app/Services/UsageTrackingService.php`, [add-usage-tracking recipe](docs/ai/recipes/add-usage-tracking.md) |
| File Manager | [ADR-030](docs/adr/030-file-manager.md), `backend/app/Http/Controllers/Api/FileManagerController.php`, [add-file-manager-feature recipe](docs/ai/recipes/add-file-manager-feature.md) |
| Passkeys | [ADR-018](docs/adr/018-passkey-webauthn.md), `backend/app/Services/Auth/PasskeyService.php`, [add-passkey-support recipe](docs/ai/recipes/add-passkey-support.md) |
| Onboarding | `backend/app/Http/Controllers/Api/OnboardingController.php`, [extend-onboarding recipe](docs/ai/recipes/extend-onboarding.md) |
| Changelog | `backend/app/Services/ChangelogService.php`, [add-changelog-entry recipe](docs/ai/recipes/add-changelog-entry.md), [changelog-entries pattern](docs/ai/patterns/changelog-entries.md) |
| API Keys | `backend/app/Services/ApiKeyService.php`, [api-key-service pattern](docs/ai/patterns/api-key-service.md) |
| Release/Deploy | [commit-and-release recipe](docs/ai/recipes/commit-and-release.md), `scripts/push.ps1`, `VERSION` |
| New Project Setup | Say **"Get cooking"** -- [setup-new-project recipe](docs/ai/recipes/setup-new-project.md) |

**For detailed file lists per task type:** [context-loading.md](docs/ai/context-loading.md)

## Gotchas

- **Service layer** - Business logic in `Services/`, not controllers
- **User scoping** - Most tables have `user_id`. Always filter by `$request->user()->id`
- **User password** - User model uses `hashed` cast. Pass plaintext; never use `Hash::make()` in controllers
- **Admin is group-based** - Use `$user->isAdmin()` / `$user->inGroup('admin')` on backend; `isAdminUser(user)` from `frontend/lib/auth.ts` on frontend
- **Sanctum cookies** - Auth uses session cookies, not Bearer tokens. Include `credentials: 'include'` in fetch
- **SQLite default** - Test array/JSON columns carefully; code also supports MySQL/PostgreSQL
- **API prefix** - All backend routes under `/api/`. Frontend calls go through Nginx proxy
- **Settings models** - System settings use `SystemSetting` via **SettingService** (not `SystemSetting::get/set` directly). User settings use `Setting` model; schema in `backend/config/user-settings-schema.php`
- **shadcn/ui** - Components in `frontend/components/ui/` are CLI-managed. Use `npx shadcn@latest add <component>` from `frontend/`
- **Form fields optional by default** - Use `z.string().optional()`, `mode: "onBlur"`, `reset()` for initial values, `setValue(..., { shouldDirty: true })` for custom inputs
- **Mobile-first CSS** - Base styles for mobile, add `md:`, `lg:` for larger. Use `useIsMobile()` for conditional rendering
- **Global components** - Never duplicate logic across pages. Search `frontend/components/` and `frontend/lib/` first
- **Audit actions** - Use `AuditService` with `{resource}.{action}` naming (e.g. `user.created`)
- **Config nav registration** - New config pages need an entry in `configuration/layout.tsx` `navigationGroups`
- **Search dual registration** - New pages need entries in both `backend/config/search-pages.php` and `frontend/lib/search-pages.ts`
- **User deletion** - Always use `UserService::deleteUser()`, never `$user->delete()` directly — handles cascade cleanup
- **SSRF protection** - Any user-supplied URL (webhooks, Ollama host, SSO URIs) must validate through `UrlValidationService`
- **Correlation IDs** - `AddCorrelationId` middleware adds `X-Correlation-ID` to every request. Use `app('correlation_id')` for log correlation, don't generate your own
- **Route deprecation** - Use `DeprecateRoute` middleware to add RFC 8594 `Deprecation` + `Sunset` headers to old routes

- **Bug tracker** - When you encounter or suspect a bug that could affect multiple locations, **always** log it in [docs/plans/bug-tracker.md](docs/plans/bug-tracker.md) before moving on. Include file path, what's wrong, suspected scope, and severity. This ensures nothing gets lost.
- **Journaling** - After completing any non-trivial implementation task, **always** write a journal entry in `docs/journal/YYYY-MM-DD-brief-description.md` following the template in [documentation-guide.md](docs/ai/documentation-guide.md). Link the journal entry from related ADRs, recipes, patterns, and roadmaps. When working on a task, check `docs/journal/` for existing entries that provide useful context.

**Pre-submit checklist:** [docs/ai/anti-patterns/README.md](docs/ai/anti-patterns/README.md#quick-checklist)

## Deep-Dive Docs (read for complex tasks)

| Guide | When to Read |
|-------|-------------|
| [AI Development Guide](docs/ai/README.md) | Recipes index, workflow, planning requirements |
| [Context Loading](docs/ai/context-loading.md) | Detailed file lists per task type |
| [Patterns](docs/ai/patterns/README.md) | Code patterns with examples |
| [Anti-Patterns](docs/ai/anti-patterns/README.md) | Common mistakes and pre-submit checklist |
| [Quick Reference](docs/quick-reference.md) | Commands, structure, naming conventions |
| [Architecture ADRs](docs/architecture.md) | Design decisions |
| [Roadmaps](docs/roadmaps.md) | What's planned |

**Using as a Template**: See [FORK-ME.md](FORK-ME.md) for instructions on using Sourdough as a base for your own project.
