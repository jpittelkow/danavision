# User Groups Roadmap

User groups system for role-based permissions and feature access control.

**Priority**: MEDIUM  
**Status**: Complete  
**Last Updated**: 2026-01-30

**Dependencies**:
- ~~[Admin Features](admin-features-roadmap.md)~~ - User Management complete

**Implementation notes (Phases 1–3)**:
- **Permission enum**: Use `backend/app/Enums/Permission.php` for type-safe permissions (string-backed enum with `all()` and `categories()`).
- **Caching**: PermissionService uses key-based cache only (no Cache tags) for SQLite/file cache compatibility. Clear per-user with `Cache::forget('permissions:' . $user->id)` or iterate member keys when clearing group cache.
- **Migrations**: Use Laravel Blueprint (e.g. `$table->foreignId('user_id')->constrained()`) so schema is database-agnostic (SQLite, MySQL, PostgreSQL).
- **Frontend (Phase 4)**: Plan hooks such as `useGroups()`, `useGroup(id)`, `useGroupMembers(id)`, `usePermissions(id)`, and include `groups` in auth user response (`GET /auth/user`) for client-side checks.

---

## Overview

Implement a flexible user groups system that allows organizing users into groups for permission management. Groups define what features, widgets, and resources users can access. The system includes built-in groups (Admin, User) with support for custom groups.

---

## Task Checklist

### Phase 1: Database & Models
- [x] Create `user_groups` table (id, name, slug, description, is_system, is_default)
- [x] Create `user_group_members` pivot table (user_id, group_id)
- [x] Create `group_permissions` table (group_id, permission, resource_type, resource_id)
- [x] Create `UserGroup` model with relationships
- [x] Create `GroupPermission` model
- [x] Update `User` model with groups relationship
- [x] Seed default groups (Admin, User)
- [x] Migrate existing admin users to Admin group

### Phase 2: Group Service & Permissions
- [x] Create `GroupService` for group management
- [x] Create `PermissionService` for permission checks
- [x] Implement permission caching strategy
- [x] Create middleware for group-based access control
- [x] Create `HasGroups` trait for User model
- [x] Implement permission inheritance (group → user)

### Phase 3: API Endpoints
- [x] `GET /api/groups` - List groups (admin only)
- [x] `POST /api/groups` - Create group (admin only)
- [x] `PUT /api/groups/{id}` - Update group (admin only)
- [x] `DELETE /api/groups/{id}` - Delete group (admin only, non-system)
- [x] `GET /api/groups/{id}/members` - List group members
- [x] `POST /api/groups/{id}/members` - Add members to group
- [x] `DELETE /api/groups/{id}/members/{userId}` - Remove member
- [x] `GET /api/groups/{id}/permissions` - Get group permissions
- [x] `PUT /api/groups/{id}/permissions` - Update group permissions

### Phase 4: Admin UI
- [x] Create Group Management page at `/configuration/groups`
- [x] Group list with member count
- [x] Create/Edit group modal
- [x] Member management interface (add/remove users)
- [x] Permission matrix UI for configuring group permissions
- [x] Bulk user assignment to groups

### Phase 5: User Integration
- [x] Show user's groups in User Management
- [x] Add group assignment in user edit modal
- [x] Update user profile to show group memberships
- [x] Filter users by group in User Management

### Phase 6: Permission Integration
- [x] Integrate with existing admin middleware (Gates auto-registered from Permission enum)
- [x] Add group checks to feature gates (all permissions as Gates; admin gate kept)
- [x] Update route protection to use groups (granular can:permission on all admin routes)
- [x] Create Blade/React directives for permission checks (usePermission hook, PermissionGate component)

### Phase 7: Documentation & Patterns
- [x] Create recipe: "Add a new permission"
- [x] Create recipe: "Create a custom group"
- [x] Document permission model (patterns.md, features.md)
- [x] Add patterns to `docs/ai/patterns.md`
- [x] Update `docs/ai/context-loading.md`

---

## Phase 1: Database & Models

### Database Schema

```sql
-- user_groups table
CREATE TABLE user_groups (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,  -- System groups can't be deleted
    is_default BOOLEAN DEFAULT FALSE, -- Auto-assign new users
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- user_group_members pivot table
CREATE TABLE user_group_members (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    group_id BIGINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_group (user_id, group_id)
);

-- group_permissions table
CREATE TABLE group_permissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    group_id BIGINT NOT NULL,
    permission VARCHAR(100) NOT NULL,    -- e.g., 'users.view', 'widgets.configure'
    resource_type VARCHAR(50) NULL,      -- Optional: 'widget', 'setting', etc.
    resource_id BIGINT NULL,             -- Optional: specific resource ID
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    INDEX idx_permission (permission),
    INDEX idx_resource (resource_type, resource_id)
);
```

### Default Groups

```php
// database/seeders/UserGroupSeeder.php
class UserGroupSeeder extends Seeder
{
    public function run(): void
    {
        // Admin group - full access
        $admin = UserGroup::create([
            'name' => 'Administrators',
            'slug' => 'admin',
            'description' => 'Full system access',
            'is_system' => true,
            'is_default' => false,
        ]);

        // User group - standard access
        $user = UserGroup::create([
            'name' => 'Users',
            'slug' => 'user',
            'description' => 'Standard user access',
            'is_system' => true,
            'is_default' => true,  // New users get this group
        ]);

        // Assign admin permissions
        $this->assignAdminPermissions($admin);
        
        // Assign user permissions
        $this->assignUserPermissions($user);
        
        // User→group migration is handled by migration *_remove_is_admin_from_users_table
        // (ensureDefaultGroupsExist; attach admin/user group by prior is_admin; drop column).
    }
}
```

### UserGroup Model

```php
// backend/app/Models/UserGroup.php
class UserGroup extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'is_default',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_members', 'group_id', 'user_id')
            ->withTimestamps();
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(GroupPermission::class, 'group_id');
    }

    public function hasPermission(string $permission, ?string $resourceType = null, ?int $resourceId = null): bool
    {
        $query = $this->permissions()->where('permission', $permission);
        
        if ($resourceType) {
            $query->where(function ($q) use ($resourceType, $resourceId) {
                $q->whereNull('resource_type')  // Global permission
                  ->orWhere(function ($q) use ($resourceType, $resourceId) {
                      $q->where('resource_type', $resourceType);
                      if ($resourceId) {
                          $q->where(function ($q) use ($resourceId) {
                              $q->whereNull('resource_id')
                                ->orWhere('resource_id', $resourceId);
                          });
                      }
                  });
            });
        }
        
        return $query->exists();
    }
}
```

---

## Phase 2: Group Service & Permissions

### HasGroups Trait

```php
// backend/app/Traits/HasGroups.php
trait HasGroups
{
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_members', 'user_id', 'group_id')
            ->withTimestamps();
    }

    public function inGroup(string|UserGroup $group): bool
    {
        $slug = $group instanceof UserGroup ? $group->slug : $group;
        return $this->groups()->where('slug', $slug)->exists();
    }

    public function hasPermission(string $permission, ?string $resourceType = null, ?int $resourceId = null): bool
    {
        // Admin group has all permissions
        if ($this->inGroup('admin')) {
            return true;
        }

        return $this->groups->contains(function ($group) use ($permission, $resourceType, $resourceId) {
            return $group->hasPermission($permission, $resourceType, $resourceId);
        });
    }

    public function assignGroup(string|UserGroup $group): void
    {
        $groupId = $group instanceof UserGroup ? $group->id : UserGroup::where('slug', $group)->firstOrFail()->id;
        $this->groups()->syncWithoutDetaching([$groupId]);
    }

    public function removeFromGroup(string|UserGroup $group): void
    {
        $groupId = $group instanceof UserGroup ? $group->id : UserGroup::where('slug', $group)->firstOrFail()->id;
        $this->groups()->detach($groupId);
    }
}
```

### Permission Service

```php
// backend/app/Services/PermissionService.php
class PermissionService
{
    protected const CACHE_TTL = 3600; // 1 hour
    protected const CACHE_PREFIX = 'permissions:';

    public function check(User $user, string $permission, ?string $resourceType = null, ?int $resourceId = null): bool
    {
        $cacheKey = $this->getCacheKey($user, $permission, $resourceType, $resourceId);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $permission, $resourceType, $resourceId) {
            return $user->hasPermission($permission, $resourceType, $resourceId);
        });
    }

    public function clearUserCache(User $user): void
    {
        // Key-based clear (no tags) for SQLite/file cache compatibility
        Cache::forget(self::CACHE_PREFIX . $user->id);
    }

    public function clearGroupCache(UserGroup $group): void
    {
        $group->members->each(fn ($user) => $this->clearUserCache($user));
    }

    protected function getCacheKey(User $user, string $permission, ?string $resourceType, ?int $resourceId): string
    {
        return self::CACHE_PREFIX . implode(':', [
            $user->id,
            $permission,
            $resourceType ?? 'global',
            $resourceId ?? 'all',
        ]);
    }
}
```

### Group Middleware

```php
// backend/app/Http/Middleware/EnsureUserInGroup.php
class EnsureUserInGroup
{
    public function handle(Request $request, Closure $next, string ...$groups): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        foreach ($groups as $group) {
            if ($user->inGroup($group)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}
```

### Permission Middleware

```php
// backend/app/Http/Middleware/EnsureUserHasPermission.php
class EnsureUserHasPermission
{
    public function __construct(protected PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if (!$this->permissions->check($user, $permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
```

---

## Phase 3: API Endpoints

### Group Controller

```php
// backend/app/Http/Controllers/Api/GroupController.php
class GroupController extends Controller
{
    public function index()
    {
        return UserGroup::withCount('members')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:user_groups',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        $group = UserGroup::create($validated);
        
        return response()->json($group, 201);
    }

    public function update(Request $request, UserGroup $group)
    {
        if ($group->is_system && $request->has('slug')) {
            return response()->json(['error' => 'Cannot modify system group slug'], 422);
        }

        $validated = $request->validate([
            'name' => 'string|max:100',
            'slug' => 'string|max:100|unique:user_groups,slug,' . $group->id,
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        $group->update($validated);
        
        return response()->json($group);
    }

    public function destroy(UserGroup $group)
    {
        if ($group->is_system) {
            return response()->json(['error' => 'Cannot delete system group'], 422);
        }

        $group->delete();
        
        return response()->noContent();
    }

    public function members(UserGroup $group)
    {
        return $group->members()->paginate(20);
    }

    public function addMembers(Request $request, UserGroup $group)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $group->members()->syncWithoutDetaching($validated['user_ids']);
        
        return response()->json(['message' => 'Members added']);
    }

    public function removeMember(UserGroup $group, User $user)
    {
        $group->members()->detach($user->id);
        
        return response()->noContent();
    }

    public function permissions(UserGroup $group)
    {
        return $group->permissions;
    }

    public function updatePermissions(Request $request, UserGroup $group)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.permission' => 'required|string',
            'permissions.*.resource_type' => 'nullable|string',
            'permissions.*.resource_id' => 'nullable|integer',
        ]);

        // Replace all permissions
        $group->permissions()->delete();
        
        foreach ($validated['permissions'] as $perm) {
            $group->permissions()->create($perm);
        }

        // Clear permission cache for all group members
        app(PermissionService::class)->clearGroupCache($group);
        
        return response()->json(['message' => 'Permissions updated']);
    }
}
```

---

## Phase 4: Admin UI

### Group Management Page

Route: `/configuration/groups`

```tsx
// frontend/app/(dashboard)/configuration/groups/page.tsx
export default function GroupsPage() {
  const { data: groups, isLoading } = useGroups();
  const [editingGroup, setEditingGroup] = useState<UserGroup | null>(null);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">User Groups</h1>
          <p className="text-muted-foreground">
            Manage groups and their permissions
          </p>
        </div>
        <Button onClick={() => setEditingGroup({} as UserGroup)}>
          <Plus className="mr-2 h-4 w-4" />
          Create Group
        </Button>
      </div>

      <GroupList 
        groups={groups} 
        onEdit={setEditingGroup}
        isLoading={isLoading}
      />

      {editingGroup && (
        <GroupEditModal
          group={editingGroup}
          onClose={() => setEditingGroup(null)}
        />
      )}
    </div>
  );
}
```

### Permission Matrix

```tsx
// frontend/components/admin/permission-matrix.tsx
interface PermissionMatrixProps {
  group: UserGroup;
  permissions: Permission[];
  onUpdate: (permissions: Permission[]) => void;
}

export function PermissionMatrix({ group, permissions, onUpdate }: PermissionMatrixProps) {
  const categories = [
    { name: 'Users', permissions: ['users.view', 'users.create', 'users.edit', 'users.delete'] },
    { name: 'Settings', permissions: ['settings.view', 'settings.edit'] },
    { name: 'Backups', permissions: ['backups.view', 'backups.create', 'backups.restore', 'backups.delete'] },
    { name: 'Logs', permissions: ['logs.view', 'logs.export', 'audit.view'] },
    // ... more categories (see Permission enum)
  ];

  return (
    <div className="space-y-4">
      {categories.map((category) => (
        <Card key={category.name}>
          <CardHeader>
            <CardTitle className="text-sm">{category.name}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
              {category.permissions.map((perm) => (
                <PermissionToggle
                  key={perm}
                  permission={perm}
                  enabled={permissions.some(p => p.permission === perm)}
                  onToggle={(enabled) => handleToggle(perm, enabled)}
                />
              ))}
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
```

---

## Permission Definitions

### Core Permissions

| Permission | Description |
|------------|-------------|
| `users.view` | View user list and profiles |
| `users.create` | Create new users |
| `users.edit` | Edit user details |
| `users.delete` | Delete/disable users |
| `settings.view` | View system settings |
| `settings.edit` | Modify system settings |
| `groups.view` | View user groups |
| `groups.manage` | Create/edit/delete groups |
| `backups.view` | View backup list |
| `backups.create` | Create backups |
| `backups.restore` | Restore from backup |
| `backups.delete` | Delete backups |
| `logs.view` | View application logs |
| `logs.export` | Export logs |
| `audit.view` | View audit logs |

### Default Group Permissions

**Admin Group**: All permissions (implicit)

**User Group**:
- `users.view` (view user list; used for profile and basic access)

---

## Migration Strategy

### Transitioning from `is_admin` ✅ COMPLETE

Completed: `is_admin` column removed; admin status is determined solely by membership in the **admin** group. A migration data-migrates existing users from `is_admin` to group membership and drops the column. `User::isAdmin()` returns `$this->inGroup('admin')`; the API still returns a computed `is_admin` attribute for frontend compatibility.

---

## API Endpoints Summary

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| GET | `/api/groups` | List all groups | `groups.view` |
| POST | `/api/groups` | Create group | `groups.manage` |
| PUT | `/api/groups/{id}` | Update group | `groups.manage` |
| DELETE | `/api/groups/{id}` | Delete group | `groups.manage` |
| GET | `/api/groups/{id}/members` | List members | `groups.view` |
| POST | `/api/groups/{id}/members` | Add members | `groups.manage` |
| DELETE | `/api/groups/{id}/members/{userId}` | Remove member | `groups.manage` |
| GET | `/api/groups/{id}/permissions` | Get permissions | `groups.view` |
| PUT | `/api/groups/{id}/permissions` | Update permissions | `groups.manage` |

---

## Implementation Priority

| Phase | Priority | Effort | Dependencies |
|-------|----------|--------|--------------|
| 1. Database & Models | HIGH | Medium | None |
| 2. Service & Permissions | HIGH | Medium | Phase 1 |
| 3. API Endpoints | HIGH | Medium | Phase 2 |
| 4. Admin UI | MEDIUM | High | Phase 3 |
| 5. User Integration | MEDIUM | Low | Phase 3 |
| 6. Permission Integration | MEDIUM | Medium | Phase 2 |
| 7. Documentation | LOW | Low | Phase 1-6 |

---

## Files Reference

**Backend (to create)**:
- `backend/app/Models/UserGroup.php`
- `backend/app/Models/GroupPermission.php`
- `backend/app/Traits/HasGroups.php`
- `backend/app/Services/GroupService.php`
- `backend/app/Services/PermissionService.php`
- `backend/app/Http/Controllers/Api/GroupController.php`
- `backend/app/Http/Middleware/EnsureUserInGroup.php`
- `backend/app/Http/Middleware/EnsureUserHasPermission.php`
- `backend/database/migrations/*_create_user_groups_tables.php`
- `backend/database/seeders/UserGroupSeeder.php`

**Backend (to modify)**:
- `backend/app/Models/User.php` - Add HasGroups trait
- `backend/bootstrap/app.php` - Register middleware aliases

**Frontend (to create)**:
- `frontend/app/(dashboard)/configuration/groups/page.tsx`
- `frontend/components/admin/group-list.tsx`
- `frontend/components/admin/group-edit-modal.tsx`
- `frontend/components/admin/permission-matrix.tsx`
- `frontend/components/admin/member-manager.tsx`

---

## Related Roadmaps

- [Dashboard Improvements](dashboard-improvements-roadmap.md) - Uses groups for widget permissions
- [Admin Features](admin-features-roadmap.md) - User management integration
- [Security Compliance](security-compliance-roadmap.md) - RBAC requirements

## Implementation Journal

- [User Groups Phase 4: Admin UI](../journal/2026-01-30-user-groups-phase-4-admin-ui.md)
- [Remove is_admin, Admin Group Only](../journal/2026-01-30-remove-is-admin-group-only.md)
- [User Management Admin](../journal/2026-01-29-user-management-admin.md)
