# Recipe: Assign User to Groups

Step-by-step guide to add or update user group assignment in the admin UI.

## When to Use

- Adding group assignment to a user edit form
- Adding a group filter to a list (e.g. users by group)
- Displaying group memberships (read-only)

## Pattern Reference

See [Patterns: User Group Assignment (Frontend)](../patterns/permission-checking.md).

## Key Files

| Area | File |
|------|------|
| Hook | `frontend/lib/use-groups.ts` – `useGroups()` |
| Picker | `frontend/components/admin/user-group-picker.tsx` |
| Backend | `backend/app/Http/Controllers/Api/UserController.php` – `updateGroups()` |
| Route | `backend/routes/api.php` – `PUT /users/{user}/groups` |

## Steps

### 1. Use the shared hook for group list

Do **not** fetch groups inline in multiple components. Use `useGroups()`:

```tsx
import { useGroups } from "@/lib/use-groups";

const { groups, isLoading, error, refetch } = useGroups();
```

Use `groups` for filter dropdowns or when building a custom picker.

### 2. User edit: use UserGroupPicker

For assigning groups to a user in the user edit dialog:

- Add state: `selectedGroupIds: number[]`
- When opening with a user, set `selectedGroupIds` from `user.groups?.map(g => g.id) ?? []`
- Render `<UserGroupPicker selectedGroupIds={...} onChange={...} currentUserId={...} editedUserId={user?.id} />`
- On save, after updating the user, call `PUT /users/{id}/groups` with `{ group_ids: selectedGroupIds }`

Pass `currentUserId` (logged-in user id) so the picker can prevent removing self from the admin group. Admin status is determined only by membership in the `admin` group; there is no separate `is_admin` column.

### 3. Backend: load groups and expose update

- **List/show users**: Eager-load `groups:id,name,slug` in `UserController::index()` and `show()`
- **Update groups**: Implement `UserController::updateGroups()` – validate `group_ids`, sync membership, audit, clear permission cache
- **Filter by group**: In `index()`, support `?group=slug` with `whereHas('groups', ...)`

### 4. Profile (read-only)

Auth response from `GET /auth/user` includes `user.groups`. Ensure the auth store’s `User` type has `groups?: { id, name, slug }[]` and display badges on the profile page.

## Checklist

- [ ] Use `useGroups()` for group list (no inline `api.get("/groups")` in multiple places)
- [ ] Use `UserGroupPicker` in user edit (or follow its pattern)
- [ ] Pass `currentUserId` and `editedUserId` for admin-group safety
- [ ] Backend: load groups on user list/show; audit and clear permission cache on update
- [ ] Frontend errors use `errorLogger`, not `console.error`

## Implementation Journal

- [User Groups Phase 4: Admin UI (2026-01-30)](../../journal/2026-01-30-user-groups-phase-4-admin-ui.md)
- [Remove is_admin, Group Only (2026-01-30)](../../journal/2026-01-30-remove-is-admin-group-only.md)
