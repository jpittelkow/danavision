# Permission Checking Pattern (User Groups)

Use `PermissionService` for cached permission checks, or `User::hasPermission()` / `User::inGroup()` on the User model (which uses the `HasGroups` trait).

## Usage (Backend)

```php
use App\Enums\Permission;
use App\Services\PermissionService;

// In controller or service: cached check
public function __construct(private PermissionService $permissionService) {}

if (!$this->permissionService->check($request->user(), Permission::BACKUPS_CREATE)) {
    abort(403, 'You do not have permission to create backups.');
}

// On User model (from HasGroups trait): direct check
if ($user->inGroup('admin')) { /* admin has all permissions */ }
if ($user->hasPermission(Permission::SETTINGS_EDIT)) { /* ... */ }
if ($user->hasPermission('users.view', null, null)) { /* global permission */ }
```

- **Admin group**: Users in the `admin` group have all permissions implicitly; no need to store every permission.
- **Caching**: `PermissionService::check()` caches results per user (single key per user for SQLite compatibility). Call `clearUserCache($user)` or `clearGroupCache($group)` when membership or group permissions change.
- **Admin check**: `User::isAdmin()` returns `true` if the user is in the `admin` group. The API includes a computed `is_admin` attribute derived from this.
- **Route protection**: All permissions are registered as Laravel Gates in `AppServiceProvider`. Use `->middleware('can:permission.name')` on routes (e.g. `can:users.view`, `can:backups.create`). See [backend/routes/api.php](backend/routes/api.php) for examples.

**Key files:** `backend/app/Services/PermissionService.php`, `backend/app/Traits/HasGroups.php`, `backend/app/Enums/Permission.php`, `backend/app/Models/UserGroup.php`, `backend/app/Providers/AppServiceProvider.php`.

---

## Permission Gate (Frontend)

Use `usePermission(permission)` or `<PermissionGate>` to conditionally render UI based on the current user's permissions. The auth user from `GET /auth/user` includes a computed `permissions` array (admin users receive all permission strings).

```tsx
import { usePermission } from "@/lib/use-permission";
import { PermissionGate } from "@/components/permission-gate";

// Hook: single permission check
const canEditBackups = usePermission("backups.restore");
if (canEditBackups) {
  // show restore button
}

// Component: wrap content that requires a permission
<PermissionGate permission="users.create" fallback={null}>
  <Button>Create User</Button>
</PermissionGate>
```

- **Config navigation**: Configuration layout filters nav items by permission; each item can specify `permission` so only users with that permission see the link. Access to the Configuration area requires at least one of the config-related permissions (or admin).
- **Backend is source of truth**: Frontend permission checks are for UX only; API routes are protected with `can:permission` middleware.
- **Admin check**: Prefer `isAdminUser(user)` from `@/lib/auth` over `user?.is_admin` so the UI stays correct if the API ever drops the computed `is_admin` attribute (admin is derived from admin group membership).

**Key files:** `frontend/lib/use-permission.ts`, `frontend/lib/auth.ts` (`isAdminUser`), `frontend/components/permission-gate.tsx`, `frontend/app/(dashboard)/configuration/layout.tsx`.

---

## User Group Assignment (Frontend)

Use the shared **`useGroups()`** hook and **`UserGroupPicker`** component for group lists and user-group assignment. Do not fetch groups inline in multiple places.

```tsx
// Use the shared hook for group list (filter dropdown, picker data)
import { useGroups } from "@/lib/use-groups";

const { groups, isLoading, error, refetch } = useGroups();
// groups: Group[] from GET /groups
```

```tsx
// For user edit: use UserGroupPicker (uses useGroups internally)
import { UserGroupPicker } from "@/components/admin/user-group-picker";

<UserGroupPicker
  selectedGroupIds={selectedGroupIds}
  onChange={setSelectedGroupIds}
  currentUserId={currentUser?.id}
  editedUserId={user?.id}
/>
```

- **Backend**: Load groups on user list/detail (`UserController::index` / `show` with `with('groups:id,name,slug')`). Update memberships via `PUT /users/{user}/groups` with `{ group_ids: number[] }`; audit and clear permission cache.
- **Current user**: Pass `currentUserId` so the picker can prevent removing self from the admin group (`editedUserId === currentUserId`).
- **Profile**: Auth user from `GET /auth/user` includes `groups`; display read-only on profile page.

**Key files:** `frontend/lib/use-groups.ts`, `frontend/components/admin/user-group-picker.tsx`, `backend/app/Http/Controllers/Api/UserController.php` (`updateGroups`).

**Related:** [Recipe: Add a new permission](../recipes/add-new-permission.md), [Recipe: Create custom group](../recipes/create-custom-group.md), [Recipe: Assign user to groups](../recipes/assign-user-to-groups.md).

---

## Permission Policy: `can:admin` vs Granular Permissions

**Rule: New features should always use granular permissions.**

Granular permissions (e.g. `can:users.view`, `can:backups.create`, `can:settings.edit`) allow admins to create custom groups with specific access levels. This is the standard pattern for all routes.

**When `can:admin` is acceptable:**
- Features that inherently require full system access and should never be delegated to a non-admin group
- Currently only the **File Manager** (`/api/storage/files/*`) uses `can:admin` because it provides raw filesystem access

**Do not use `can:admin` for:**
- Configuration pages (use `can:settings.view` / `can:settings.edit`)
- Data management (use feature-specific permissions like `can:backups.create`)
- Monitoring/logs (use `can:logs.view` / `can:audit.view`)

Adding a new permission: define it in `App\Enums\Permission`, it auto-registers as a Laravel Gate. See [Recipe: Add a new permission](../recipes/add-new-permission.md).

## Implementation Journal

- [User Groups Phase 4 Admin UI (2026-01-30)](../../journal/2026-01-30-user-groups-phase-4-admin-ui.md)
- [Remove is_admin Group Only (2026-01-30)](../../journal/2026-01-30-remove-is-admin-group-only.md)
