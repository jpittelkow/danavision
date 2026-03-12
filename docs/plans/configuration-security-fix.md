# Plan: Fix Configuration Route Security

## Problem

Non-admin users can access configuration and user management pages by navigating directly to the URL (e.g. `/configuration/users`). The backend API correctly returns 403, so no data leaks, but the page UI/structure is fully rendered. Two distinct issues:

1. **No per-page permission check** — The configuration layout only checks if the user has *any* config permission. A user with only `audit.view` can navigate to `/configuration/users` and see the page skeleton.
2. **Client-side only protection** — All auth checks use `useEffect` + `router.push()`, meaning the page HTML/JS is served before the redirect fires (brief flash of protected UI).

## Approach

Create a reusable permission-guarded wrapper hook and apply it to every configuration page. This is the minimal, lowest-risk fix. (Next.js middleware for server-side protection is a separate future enhancement — see "Future" section at bottom.)

## Context Files to Read First

```
docs/ai/context-loading.md                          # General context loading
frontend/app/(dashboard)/configuration/layout.tsx    # Current layout guard
frontend/lib/use-permission.ts                       # usePermission() hook
frontend/components/permission-gate.tsx              # PermissionGate component
frontend/lib/auth.ts                                 # useAuth, isAdminUser
backend/app/Enums/Permission.php                     # All permission strings
backend/routes/api.php                               # Backend route permission mapping
```

## Step 1: Create `useRequirePermission` Hook

**Create file:** `frontend/lib/use-require-permission.ts`

This hook checks if the current user has a specific permission. If not, it redirects to `/dashboard` and returns `{ authorized: false }`. If the permission is `null`, it allows any authenticated user (for pages like changelog that have no permission requirement).

```typescript
"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth, isAdminUser } from "./auth";

/**
 * Guard hook for configuration pages. Checks if the current user has the
 * required permission. Redirects to /dashboard if not.
 *
 * @param permission - Permission string (e.g. "users.view"). Pass null for
 *                     pages that only require authentication (no specific permission).
 * @returns { authorized, isLoading } - Use authorized to conditionally render page content.
 */
export function useRequirePermission(permission: string | null): {
  authorized: boolean;
  isLoading: boolean;
} {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  const hasPermission =
    isLoading || !user
      ? false
      : permission === null
        ? true // null = any authenticated user
        : isAdminUser(user) || (user.permissions?.includes(permission) ?? false);

  useEffect(() => {
    if (!isLoading && (!user || !hasPermission)) {
      router.push("/dashboard");
    }
  }, [isLoading, user, hasPermission, router]);

  return {
    authorized: !isLoading && !!user && hasPermission,
    isLoading,
  };
}
```

## Step 2: Add Guard to Every Configuration Page

For **each** configuration page, add the hook at the top of the component and return a loading/null state when unauthorized. The pattern is:

```typescript
import { useRequirePermission } from "@/lib/use-require-permission";

export default function SomeConfigPage() {
  const { authorized, isLoading } = useRequirePermission("the.permission");

  if (isLoading || !authorized) {
    return null; // Layout already shows a spinner during loading
  }

  // ... rest of existing component
}
```

### Permission Mapping per Page

Apply these exact permission strings. This matches the backend `can:` middleware on the API routes each page calls.

| Page file | Permission argument |
|---|---|
| `configuration/page.tsx` (index/redirect) | `null` (any authenticated config user — layout handles broad check) |
| `configuration/system/page.tsx` | `"settings.view"` |
| `configuration/branding/page.tsx` | `"settings.edit"` |
| `configuration/changelog/page.tsx` | `null` (no permission — any authenticated user) |
| `configuration/users/page.tsx` | `"users.view"` |
| `configuration/groups/page.tsx` | `"groups.view"` |
| `configuration/security/page.tsx` | `"settings.view"` |
| `configuration/sso/page.tsx` | `"settings.view"` |
| `configuration/api/page.tsx` | `"settings.view"` |
| `configuration/notifications/page.tsx` | `"settings.view"` |
| `configuration/email/page.tsx` | `"settings.view"` |
| `configuration/email-templates/page.tsx` | `"settings.view"` |
| `configuration/email-templates/[key]/page.tsx` | `"settings.view"` |
| `configuration/notification-templates/page.tsx` | `"settings.view"` |
| `configuration/notification-templates/[id]/page.tsx` | `"settings.view"` |
| `configuration/novu/page.tsx` | `"settings.view"` |
| `configuration/ai/page.tsx` | `"settings.view"` |
| `configuration/storage/page.tsx` | `"settings.view"` |
| `configuration/storage/files/page.tsx` | `"settings.view"` |
| `configuration/search/page.tsx` | `"settings.view"` |
| `configuration/audit/page.tsx` | `"audit.view"` |
| `configuration/logs/page.tsx` | `"logs.view"` |
| `configuration/access-logs/page.tsx` | `"logs.view"` |
| `configuration/log-retention/page.tsx` | `"settings.view"` |
| `configuration/jobs/page.tsx` | `"settings.view"` |
| `configuration/usage/page.tsx` | `"usage.view"` |
| `configuration/backup/page.tsx` | `"backups.view"` |
| `configuration/profile/page.tsx` | `null` (user's own profile — any authenticated user) |

### Implementation Pattern for Each Page

For most pages the change is 4 lines added at the top of the default export function. Example for `configuration/users/page.tsx`:

**Before:**
```typescript
export default function UsersPage() {
  const { user: currentUser } = useAuth();
  // ... rest of component
```

**After:**
```typescript
import { useRequirePermission } from "@/lib/use-require-permission";

export default function UsersPage() {
  const { authorized, isLoading: permLoading } = useRequirePermission("users.view");
  const { user: currentUser } = useAuth();

  if (permLoading || !authorized) return null;

  // ... rest of component (unchanged)
```

For pages that **don't** already import `useAuth`, just add the hook and guard:

```typescript
import { useRequirePermission } from "@/lib/use-require-permission";

export default function SystemPage() {
  const { authorized, isLoading } = useRequirePermission("settings.view");
  if (isLoading || !authorized) return null;

  // ... rest of existing component (unchanged)
```

**Important:** If the page already has its own `isLoading` variable (from data fetching), name the destructured variable `permLoading` to avoid conflicts.

## Step 3: Verify the Layout Guard Still Works

The existing layout guard in `configuration/layout.tsx` should remain as-is. It provides the broad "can this user see the config section at all" check and renders the sidebar navigation. The per-page guards add defense-in-depth for the specific permission.

**Do NOT remove** the layout-level `hasConfigAccess` check or the `useEffect` redirect — it still serves as first-line protection and controls sidebar visibility.

## Step 4: Test

1. Log in as a non-admin user with **no** group permissions
   - Navigate to `/configuration/users` — should redirect to `/dashboard`
   - Navigate to `/configuration/system` — should redirect to `/dashboard`
2. Log in as a non-admin user with only `audit.view` permission
   - Navigate to `/configuration/audit` — should render normally
   - Navigate to `/configuration/users` — should redirect to `/dashboard`
   - Navigate to `/configuration/system` — should redirect to `/dashboard`
3. Log in as admin — all pages should work as before

## Files Changed Summary

| Action | File |
|--------|------|
| **Create** | `frontend/lib/use-require-permission.ts` |
| **Edit** | All 28 page files under `frontend/app/(dashboard)/configuration/` (add 3-4 lines each) |

## Future Enhancement (Out of Scope)

**Next.js middleware** (`frontend/middleware.ts`) could provide server-side route protection by checking the session cookie before the page HTML is ever sent to the browser. This would eliminate the brief client-side flash entirely. However, it requires:
- Reading the Sanctum session cookie server-side
- Making a server-side API call to validate the session and fetch permissions
- Careful handling of cookie forwarding

This is a larger change and should be tracked separately. The per-page guards implemented here are sufficient for preventing unauthorized access to page content.
