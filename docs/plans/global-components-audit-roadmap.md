# Global Components Audit Roadmap

Audit existing codebase for violations of the global components principle and refactor duplicated logic into shared components.

**Priority:** MEDIUM  
**Dependencies:** None

## Background

The project has established a rule for [global components](.cursor/rules/global-components.mdc) requiring all reusable functionality to be implemented as shared components. This audit will identify existing violations and create a plan to consolidate duplicated logic.

## Phase 1: Audit & Discovery - COMPLETE

- [x] Scan all page components for inline implementations that should be shared
- [x] Identify duplicated UI patterns across pages
- [x] Identify duplicated hooks/logic across components
- [x] Identify duplicated utility functions
- [x] Document all findings with file locations and severity

**Findings documented in:** `.cursor/plans/global-components-audit.plan.md`

### Areas to Audit

| Area | Location | Check For |
|------|----------|-----------|
| Dashboard pages | `frontend/app/(dashboard)/` | Inline components, duplicated state logic |
| Auth pages | `frontend/app/(auth)/` | Form patterns, validation logic |
| Configuration pages | `frontend/app/(dashboard)/configuration/` | Repeated settings UI patterns |
| Components | `frontend/components/` | Similar functionality that could be merged |
| Lib/Hooks | `frontend/lib/` | Duplicated utility functions |
| Backend Controllers | `backend/app/Http/Controllers/` | Repeated response patterns, validation |
| Backend Services | `backend/app/Services/` | Duplicated business logic |

## Phase 2: Prioritize & Plan - COMPLETE

- [x] Categorize findings by impact (HIGH/MEDIUM/LOW)
- [x] Identify quick wins (easy consolidations)
- [x] Identify complex refactors requiring careful planning
- [x] Create task list with dependencies

**Plan documented in:** `.cursor/plans/global-components-audit.plan.md`

### Prioritization Criteria

- **HIGH:** Duplicated logic that affects consistency (bugs fixed in one place but not others)
- **MEDIUM:** Duplicated UI patterns that affect maintainability
- **LOW:** Minor duplications with limited impact

## Phase 3: Refactor - COMPLETE

- [x] Extract shared components from identified duplications (Settings: SettingsPageSkeleton, SaveButton)
- [x] Extract shared auth components (AuthPageLayout, FormField, AuthDivider, AuthStateCard, LoadingButton)
- [x] Create missing hooks for repeated state logic (skipped - useSettingsForm variations too significant)
- [x] Consolidate utility functions (skipped - audit found no significant duplication, only pattern variations)
- [x] Update settings pages to use new shared components (branding, system, email, profile)
- [x] Update auth pages to use new shared components (login, register, forgot-password, reset-password, verify-email)
- [x] Update backend controllers to use new traits (AdminAuthorizationTrait, ApiResponseTrait, pagination config)
- [x] Remove deprecated inline implementations (last-admin checks, hardcoded pagination)

**Components created:**
- `frontend/components/ui/settings-page-skeleton.tsx`
- `frontend/components/ui/save-button.tsx`
- `frontend/components/ui/loading-button.tsx`
- `frontend/components/ui/form-field.tsx`
- `frontend/components/auth/auth-page-layout.tsx`
- `frontend/components/auth/auth-divider.tsx`
- `frontend/components/auth/auth-state-card.tsx`

**Backend traits created:**
- `backend/app/Http/Traits/AdminAuthorizationTrait.php`
- `backend/app/Http/Traits/ApiResponseTrait.php`
- `config('app.pagination.default')` and `config('app.pagination.audit_log')`

## Phase 4: Prevention - COMPLETE

- [x] Update documentation with new shared components
- [x] Add examples to `docs/ai/patterns.md` for common patterns found (Settings + Auth components)
- [x] Add warnings to `docs/ai/anti-patterns.md` for common mistakes found
- [x] Update `docs/ai/context-loading.md` with new component locations
- [x] Consider adding lint rules or automated checks (deferred - optional enhancement)

## Success Criteria

- [x] No duplicated UI components across pages (Settings + Auth pages completed)
- [x] All repeated logic extracted to hooks/utilities (backend traits completed)
- [x] Documentation updated with new shared components
- [x] Team/AI aware of global component rule

## Progress Summary

**Frontend Components:** ✅ Complete
- Settings pages: 4 pages refactored with 2 shared components
- Auth pages: 5 pages refactored with 5 shared components
- Total: 9 pages refactored, 7 shared components created

**Backend Consolidation:** ✅ Complete
- AdminAuthorizationTrait (for "last admin" checks) – UserController, ProfileController
- ApiResponseTrait (for standardized JSON responses) – UserController, ProfileController, AuthController
- Pagination config – UserController, NotificationController, JobController, WebhookController, AuditLogController
- Recipe: [Add admin-protected action](../ai/recipes/add-admin-protected-action.md)

**Documentation:** ✅ Complete
- Patterns documented in `docs/ai/patterns.md` (Backend Traits section)
- Anti-patterns and context-loading updated
- Roadmap status tracked

## Notes

Reference the global components rule at `.cursor/rules/global-components.mdc` for guidelines on what should be shared vs. page-specific.
