# Recipe: Add a New Permission

Step-by-step guide to add a new permission to the user groups system and use it for route protection and UI.

## When to Use

- Adding a new feature area that requires role-based access (e.g. `reports.view`, `reports.export`)
- Restricting a new API or configuration section to specific groups

## Pattern Reference

See [Patterns: Permission Checking (User Groups)](../patterns/permission-checking.md).

## Key Files

| Area | File |
|------|------|
| Enum | `backend/app/Enums/Permission.php` |
| Seeder | `backend/database/seeders/UserGroupSeeder.php` |
| Gates | `backend/app/Providers/AppServiceProvider.php` (auto-registered from enum) |
| Routes | `backend/routes/api.php` – use `can:permission.name` |
| Frontend | `frontend/lib/use-permission.ts`, `frontend/components/permission-gate.tsx` |
| Config nav | `frontend/app/(dashboard)/configuration/layout.tsx` – add `permission` to nav items |

## Steps

### 1. Add to the Permission enum

In [backend/app/Enums/Permission.php](backend/app/Enums/Permission.php):

- Add a new case, e.g. `case REPORTS_VIEW = 'reports.view';`
- Add it to `categories()` under the appropriate group (or add a new category):

```php
// In categories()
'Reports' => [self::REPORTS_VIEW, self::REPORTS_EXPORT],
```

The value is a string used in Gates, API responses, and frontend checks. Use `resource.action` (e.g. `reports.view`, `reports.export`).

### 2. Update default group permissions (optional)

In [backend/database/seeders/UserGroupSeeder.php](backend/database/seeders/UserGroupSeeder.php):

- If the Admin group gets all permissions implicitly, no change needed.
- If the default "User" group should have this permission, add it in `assignUserPermissions()` (or equivalent).

### 3. Protect routes

In [backend/routes/api.php](backend/routes/api.php), use the permission in middleware:

```php
Route::get('/reports', [ReportController::class, 'index'])->middleware('can:reports.view');
Route::get('/reports/export', [ReportController::class, 'export'])->middleware('can:reports.export');
```

Gates are auto-registered in `AppServiceProvider` from `Permission::cases()`, so no extra Gate definition is needed.

### 4. Frontend: use permission in UI

- **Hook:** `usePermission('reports.view')` returns a boolean.
- **Component:** `<PermissionGate permission="reports.view">{children}</PermissionGate>` renders children only when the user has the permission.
- **Config nav:** In [frontend/app/(dashboard)/configuration/layout.tsx](frontend/app/(dashboard)/configuration/layout.tsx), add `permission: 'reports.view'` to the nav item so the link only appears for users with that permission.

### 5. Frontend types (optional)

The auth user type in [frontend/lib/auth.ts](frontend/lib/auth.ts) already includes `permissions?: string[]`. No change needed unless you introduce a typed permission constant on the frontend.

## Checklist

- [ ] New case in `Permission` enum and in `categories()`
- [ ] Default permissions in `UserGroupSeeder` if needed
- [ ] Routes protected with `can:permission.value`
- [ ] Config nav item has `permission` if it’s a config page
- [ ] UI uses `usePermission()` or `PermissionGate` where visibility depends on the permission

## Implementation Journal

- [User Groups Phase 4: Admin UI (2026-01-30)](../../journal/2026-01-30-user-groups-phase-4-admin-ui.md)
