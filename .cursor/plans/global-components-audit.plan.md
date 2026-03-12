---
name: Global Components Audit
overview: Audit the codebase for violations of the global components principle, extract shared components from repeated patterns, and improve maintainability across frontend and backend.
todos:
  - id: phase-1-skeleton
    content: Create SettingsPageSkeleton component for loading states
    status: completed
  - id: phase-1-hook
    content: Create useSettingsForm hook for form lifecycle management (SKIPPED - page variations too significant)
    status: cancelled
  - id: phase-1-save-button
    content: Create SaveButton component with isDirty/isSaving logic
    status: completed
  - id: phase-2-auth-layout
    content: Create AuthPageLayout component with Logo + title + description
    status: completed
  - id: phase-2-form-field
    content: Create FormField component for Label + Input + error
    status: completed
  - id: phase-2-divider
    content: Create AuthDivider component for 'Or continue with' divider
    status: completed
  - id: phase-2-state-card
    content: Create AuthStateCard component for success/error/pending states
    status: completed
  - id: phase-2-loading-button
    content: Create LoadingButton component with spinner support
    status: completed
  - id: phase-3-admin-trait
    content: Create AdminAuthorizationTrait for 'last admin' checks
    status: completed
  - id: phase-3-response-trait
    content: Create ApiResponseTrait for standardized JSON responses
    status: completed
  - id: phase-4-settings
    content: Refactor 5 settings pages to use new components
    status: completed
  - id: phase-4-auth
    content: Refactor 5 auth pages to use new components
    status: completed
  - id: phase-4-backend
    content: Refactor backend controllers to use new traits
    status: completed
  - id: phase-5-docs
    content: Update patterns.md, anti-patterns.md, and context-loading.md
    status: completed
isProject: false
---

# Global Components Audit Plan

## Audit Findings Summary

Based on exploration of the codebase, here are the key areas of duplication:

### Frontend - Dashboard Pages (`frontend/app/(dashboard)/`)

**High Impact - Repeated Form Patterns:**
- Form management: `useState` for `isLoading`/`isSaving`, `useForm` with zod, `fetchSettings()` pattern
- UI structure: Card + CardHeader + CardContent + CardFooter repeated on every settings page
- Loading state: `Loader2` spinner in centered container duplicated across all pages
- Save button: Same `disabled={!isDirty || isSaving}` pattern everywhere

**Files affected:** `configuration/branding/page.tsx`, `configuration/system/page.tsx`, `configuration/email/page.tsx`, `user/profile/page.tsx`, `notifications/page.tsx`

### Frontend - Auth Pages (`frontend/app/(auth)/`)

**High Impact - Repeated Patterns:**
- Auth page layout: Logo + title + description header (5 pages)
- Form field: Label + Input + error message pattern
- Auth divider: "Or continue with email" divider (login + register)
- State cards: Success/error/pending state cards (forgot-password, reset-password, verify-email)
- Loading button: Button with spinner and loading text

**Files affected:** All 5 auth pages in `frontend/app/(auth)/`

### Frontend - Lib (`frontend/lib/`)

**Low Impact - Consistency Issues:**
- Some providers use React Query (`app-config.tsx`, `version-provider.tsx`)
- Others use direct API calls (`auth.ts`, `notifications.tsx`)
- Not a violation, but could be standardized

### Backend - Controllers (`backend/app/Http/Controllers/Api/`)

**Medium Impact - Repeated Patterns:**
- "Last admin" authorization check duplicated 4 times
- Pagination defaults inconsistent (15 vs 20)
- Error handling varies (some try/catch, some rely on Laravel exceptions)
- `makeHidden(['password', 'two_factor_secret', ...])` repeated 6 times

**Files affected:** `UserController.php`, `ProfileController.php`, `ApiTokenController.php`, and others

### Backend - Services (`backend/app/Services/`)

**Low Impact - Pattern Duplication:**
- `LLMOrchestrator` and `NotificationOrchestrator` share similar instance management logic
- `BackupService` has destination interfaces but doesn't use them

---

## Implementation Plan

### Phase 1: Frontend Form Components (High Priority) - COMPLETE

Extract shared components for the repeated settings page patterns:

**1.1 Create `SettingsPageSkeleton` component** - DONE
- Centered `Loader2` spinner for loading states
- Location: `frontend/components/ui/settings-page-skeleton.tsx`

**1.2 Create `useSettingsForm` hook** - SKIPPED
- Page variations too significant (different endpoints, query invalidation, custom operations)
- The UI components alone provide sufficient value

**1.3 Create `SaveButton` component** - DONE
- Button with `disabled={!isDirty || isSaving}` logic built in
- Shows loading spinner when saving
- Location: `frontend/components/ui/save-button.tsx`

**Settings pages refactored:** branding, system, email, profile

### Phase 2: Frontend Auth Components (High Priority) - COMPLETE

Extract shared components for auth pages:

**2.1 Create `AuthPageLayout` component** - DONE
- Wrapper with Logo + title + description
- Consistent spacing and centering
- Location: `frontend/components/auth/auth-page-layout.tsx`

**2.2 Create `FormField` component** - DONE
- Label + Input + error display
- Integrates with react-hook-form
- Location: `frontend/components/ui/form-field.tsx`

**2.3 Create `AuthDivider` component** - DONE
- "Or continue with" divider
- Location: `frontend/components/auth/auth-divider.tsx`

**2.4 Create `AuthStateCard` component** - DONE
- Success/error/pending state cards with icons
- Location: `frontend/components/auth/auth-state-card.tsx`

**2.5 Create `LoadingButton` component** - DONE
- Button with loading spinner support
- Location: `frontend/components/ui/loading-button.tsx`

### Phase 3: Backend Consolidation (Medium Priority) - COMPLETE

**3.1 Create `AdminAuthorizationTrait`** - DONE
- Extract "last admin" checks into reusable trait
- Method: `ensureNotLastAdmin(User $user, string $action)`
- Location: `backend/app/Http/Traits/AdminAuthorizationTrait.php`

**3.2 Standardize pagination defaults** - DONE
- Config: `config('app.pagination.default')` (20), `config('app.pagination.audit_log')` (50)
- UserController, NotificationController, JobController, WebhookController, AuditLogController updated

**3.3 Create `ApiResponseTrait`** - DONE
- Standard response helpers: `successResponse()`, `errorResponse()`, `createdResponse()`
- Consistent status codes and format
- Location: `backend/app/Http/Traits/ApiResponseTrait.php`

### Phase 4: Update Pages to Use New Components - COMPLETE

**4.1 Refactor settings pages** - DONE
- branding, system, email, profile use `SettingsPageSkeleton`, `SaveButton`

**4.2 Refactor auth pages** - DONE
- login, register, forgot-password, reset-password, verify-email use `AuthPageLayout`, `FormField`, `AuthDivider`, `AuthStateCard`, `LoadingButton`

**4.3 Refactor backend controllers** - DONE
- UserController, ProfileController use `AdminAuthorizationTrait` and `ApiResponseTrait`
- AuthController uses `ApiResponseTrait`
- Pagination config applied to listed controllers

### Phase 5: Documentation & Prevention - COMPLETE

**5.1 Update documentation** - DONE
- Backend Traits section in `docs/ai/patterns.md`
- Anti-patterns in `docs/ai/anti-patterns.md` (last-admin, pagination)
- `docs/ai/context-loading.md` updated with traits and recipes
- Recipe: `docs/ai/recipes/add-admin-protected-action.md`
- `docs/ai/recipes/add-api-endpoint.md` – Using Shared Traits section

**5.2 Update roadmap** - DONE
- Global Components Audit moved to Completed in `docs/roadmaps.md` (2026-01-28)
- `docs/plans/global-components-audit-roadmap.md` updated

---

## Estimated Scope

| Phase | New Files | Files Modified | Complexity |
|-------|-----------|----------------|------------|
| Phase 1 | 3 | 0 | Low |
| Phase 2 | 5 | 0 | Low |
| Phase 3 | 2 | 0 | Medium |
| Phase 4 | 0 | 15+ | Medium |
| Phase 5 | 0 | 3 | Low |

---

## Files to Create

```
frontend/components/ui/settings-page-skeleton.tsx
frontend/components/ui/save-button.tsx
frontend/components/ui/form-field.tsx
frontend/components/ui/loading-button.tsx
frontend/components/auth/auth-page-layout.tsx
frontend/components/auth/auth-divider.tsx
frontend/components/auth/auth-state-card.tsx
frontend/lib/use-settings-form.ts
backend/app/Http/Traits/AdminAuthorizationTrait.php
backend/app/Http/Traits/ApiResponseTrait.php
```

## Key Files to Modify

```
frontend/app/(dashboard)/configuration/branding/page.tsx
frontend/app/(dashboard)/configuration/system/page.tsx
frontend/app/(dashboard)/configuration/email/page.tsx
frontend/app/(dashboard)/user/profile/page.tsx
frontend/app/(auth)/login/page.tsx
frontend/app/(auth)/register/page.tsx
frontend/app/(auth)/forgot-password/page.tsx
frontend/app/(auth)/reset-password/page.tsx
frontend/app/(auth)/verify-email/page.tsx
backend/app/Http/Controllers/Api/UserController.php
backend/app/Http/Controllers/Api/ProfileController.php
backend/app/Http/Controllers/Api/AuthController.php
backend/config/app.php
docs/ai/patterns.md
docs/ai/anti-patterns.md
docs/ai/context-loading.md
docs/ai/recipes/add-api-endpoint.md
docs/plans/global-components-audit-roadmap.md
docs/roadmaps.md
```

## Files Created (Backend Consolidation)

```
backend/app/Http/Traits/AdminAuthorizationTrait.php
backend/app/Http/Traits/ApiResponseTrait.php
docs/ai/recipes/add-admin-protected-action.md
```
