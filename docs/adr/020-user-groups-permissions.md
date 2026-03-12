# ADR-020: User Groups and Permissions System

## Status

Accepted

## Date

2026-01-31

## Context

Sourdough needs a flexible authorization system that:
- Supports multiple user roles beyond simple admin/non-admin
- Allows granular permission control
- Enables future extension with custom groups
- Works with self-hosted single-user and enterprise multi-user deployments

A simple `is_admin` flag on users is insufficient for enterprise deployments where different users need different levels of access (e.g., backup operators, notification managers).

## Decision

We implement a **group-based permission system** with predefined system groups and support for custom groups.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                   Groups & Permissions                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  User ──► belongs to many ──► UserGroup                         │
│                                    │                             │
│                                    ▼                             │
│                           ┌──────────────┐                      │
│                           │GroupPermission│                     │
│                           │  (pivot)      │                     │
│                           └──────────────┘                      │
│                                    │                             │
│                                    ▼                             │
│                           Permission Enum                        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### System Groups

| Group | Slug | Description | Default Permissions |
|-------|------|-------------|---------------------|
| Administrators | `admin` | Full system access | All permissions |
| Users | `user` | Standard users | Basic view permissions |

### Permission Enum

Permissions are defined in `App\Enums\Permission`:

```php
enum Permission: string
{
    // Settings
    case SETTINGS_VIEW = 'settings.view';
    case SETTINGS_EDIT = 'settings.edit';
    
    // User Management
    case USERS_VIEW = 'users.view';
    case USERS_EDIT = 'users.edit';
    
    // Backup
    case BACKUPS_VIEW = 'backups.view';
    case BACKUPS_CREATE = 'backups.create';
    case BACKUPS_RESTORE = 'backups.restore';
    
    // ... additional permissions
}
```

### Database Schema

```sql
user_groups
├── id
├── name
├── slug (unique)
├── description (nullable)
├── is_system (boolean) -- System groups cannot be deleted
├── is_default (boolean) -- Assigned to new users
├── created_at / updated_at

group_permissions
├── id
├── user_group_id (FK → user_groups)
├── permission (string, enum value)
├── created_at / updated_at
UNIQUE(user_group_id, permission)

user_group_user (pivot)
├── user_id (FK → users)
├── user_group_id (FK → user_groups)
UNIQUE(user_id, user_group_id)
```

### Services

- **GroupService**: Create, update, delete groups; manage group permissions; assign/remove users
- **PermissionService**: Check user permissions; get all permissions for user; cache permission lookups

### Authorization Flow

```php
// Check permission via PermissionService
if ($permissionService->userCan($user, Permission::SETTINGS_EDIT)) {
    // Allow action
}

// Check via User model helper
if ($user->hasPermission(Permission::BACKUPS_CREATE)) {
    // Allow action
}

// Check group membership
if ($user->inGroup('admin')) {
    // Allow action
}
```

### Frontend Integration

- `PermissionGate` component for conditional rendering
- `usePermission` hook for permission checks
- Admin UI for group management at Configuration > Groups

## Consequences

### Positive

- Granular permission control beyond simple admin/user dichotomy
- System groups provide sensible defaults
- Custom groups enable enterprise role customization
- Permission enum provides type safety and discoverability
- Backward compatible: admin group replaces `is_admin` flag

### Negative

- More complex than simple `is_admin` flag
- Requires migration from `is_admin` to group membership
- Permission checks add slight overhead (mitigated by caching)

### Neutral

- New users are assigned to default group(s) automatically
- System groups cannot be deleted, only modified
- Permissions can be added by extending the enum

## Key Files

- `backend/app/Services/GroupService.php` - Group CRUD and user management
- `backend/app/Services/PermissionService.php` - Permission checking and caching
- `backend/app/Models/UserGroup.php` - Group model
- `backend/app/Models/GroupPermission.php` - Permission pivot model
- `backend/app/Enums/Permission.php` - Permission enum
- `backend/app/Http/Controllers/Api/GroupController.php` - Group API
- `backend/database/migrations/2026_01_30_000019_create_user_groups_tables.php`
- `backend/database/seeders/UserGroupSeeder.php`
- `frontend/components/permission-gate.tsx` - Conditional rendering component
- `frontend/lib/use-permission.ts` - Permission hook
- `frontend/app/(dashboard)/configuration/groups/page.tsx` - Admin UI
- `frontend/components/admin/group-table.tsx` - Group management table
- `frontend/components/admin/group-dialog.tsx` - Create/edit group dialog
- `frontend/components/admin/permission-matrix.tsx` - Permission assignment matrix

## Related Decisions

- [ADR-002: Authentication Architecture](./002-authentication-architecture.md)
- [ADR-012: Admin-Only Settings Access](./012-admin-only-settings.md)

## Implementation Journal

- [User Groups Phase 4 Admin UI (2026-01-30)](../journal/2026-01-30-user-groups-phase-4-admin-ui.md)
- [Remove is_admin Group Only (2026-01-30)](../journal/2026-01-30-remove-is-admin-group-only.md)
- [User Management Admin (2026-01-29)](../journal/2026-01-29-user-management-admin.md)
