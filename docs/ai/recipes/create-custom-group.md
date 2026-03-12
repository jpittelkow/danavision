# Recipe: Create a Custom Group

Step-by-step guide to create a custom user group and assign permissions and users.

## When to Use

- Creating a role such as "Support" or "Auditors" with a specific set of permissions
- Onboarding a new team with limited access (e.g. view-only logs)

## Pattern Reference

See [Patterns: Permission Checking (User Groups)](../patterns/permission-checking.md) and [User Groups Roadmap](../../plans/user-groups-roadmap.md).

## Key Files

| Area | File |
|------|------|
| API | `backend/app/Http/Controllers/Api/GroupController.php` |
| Routes | `backend/routes/api.php` – `/groups`, `/groups/{id}/members`, `/groups/{id}/permissions` |
| Seeder | `backend/database/seeders/UserGroupSeeder.php` (for system/default groups only) |
| Frontend | `frontend/app/(dashboard)/configuration/groups/page.tsx`, `frontend/components/admin/group-dialog.tsx`, `frontend/components/admin/permission-matrix.tsx` |

## Ways to Create a Group

### 1. Via Admin UI (recommended)

1. Go to **Configuration > Groups** (requires `groups.view` and `groups.manage`).
2. Click **Create Group**.
3. Enter name, slug (unique), and optional description.
4. Save. Then use **Members** and **Permissions** to assign users and permissions.

Custom groups are non-system (`is_system = false`) and can be edited or deleted.

### 2. Via API

**Create group:**

```http
POST /api/groups
Content-Type: application/json

{
  "name": "Auditors",
  "slug": "auditors",
  "description": "View-only access to audit and access logs",
  "is_default": false
}
```

Requires `groups.manage`. Response: created group with `id`, `name`, `slug`, `description`, `is_system`, `is_default`.

**Assign permissions:**

```http
PUT /api/groups/{id}/permissions
Content-Type: application/json

{
  "permissions": [
    { "permission": "audit.view", "resource_type": null, "resource_id": null },
    { "permission": "logs.view", "resource_type": null, "resource_id": null }
  ]
}
```

**Add members:**

```http
POST /api/groups/{id}/members
Content-Type: application/json

{
  "user_ids": [2, 3, 4]
}
```

**Remove member:**

```http
DELETE /api/groups/{id}/members/{userId}
```

### 3. Via Seeder (for system or default groups)

Use only when the group must exist for every deployment (e.g. a built-in "Support" role). In [backend/database/seeders/UserGroupSeeder.php](backend/database/seeders/UserGroupSeeder.php):

- Create the group with `UserGroup::create([...])`.
- Set `is_system => true` if it must not be deletable by admins.
- Call a helper to assign permissions (e.g. create `GroupPermission` rows).
- Optionally attach users: `$user->groups()->attach($group->id)`.

Run: `php artisan db:seed --class=UserGroupSeeder` (or include in `DatabaseSeeder`).

## Assigning Permissions to the Group

- **UI:** Open the group, go to the Permissions tab/matrix, toggle permissions by category (Users, Settings, Backups, Logs, etc.), then save. Uses `PUT /api/groups/{id}/permissions`.
- **API:** Same as above; send the full list of permission objects. Only global permissions (null `resource_type` / `resource_id`) are used for the current permission model.

## Assigning Users to the Group

- **UI:** Configuration > Users > Edit user > Groups multi-select (UserGroupPicker); or Configuration > Groups > [Group] > Members > Add members.
- **API:** `POST /api/groups/{id}/members` with `user_ids`, or `PUT /users/{user}/groups` with `group_ids` for a single user.

## Checklist

- [ ] Group created via UI or API (or seeder for system groups)
- [ ] Permissions set via permission matrix or `PUT /groups/{id}/permissions`
- [ ] Users assigned via User Management or Groups > Members
- [ ] Permission cache is cleared when group permissions or members change (handled by `GroupController` and `UserController`)

## Implementation Journal

- [User Groups Phase 4: Admin UI (2026-01-30)](../../journal/2026-01-30-user-groups-phase-4-admin-ui.md)
