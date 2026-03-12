# Remove is_admin, Admin Group Only — 2026-01-30

## Overview

The `is_admin` column was removed from the `users` table. Admin status is now determined solely by membership in the **admin** user group. The first registered user (email or SSO) is assigned to the admin group; subsequent users get the default (user) group. All backend admin checks use `inGroup('admin')` (or `User::isAdmin()` which delegates to it). The API still returns a computed `is_admin` attribute for frontend compatibility.

## Implementation Approach

- **GroupService::ensureDefaultGroupsExist()**: Idempotent creation of default groups (Administrators, Users) and their permissions so the first registration can succeed without running the seeder.
- **Migration**: Data-migrate existing users from `is_admin` to group membership (attach to admin or user group), then drop the `is_admin` column. Down migration re-adds the column and sets it from admin group membership.
- **User model**: Removed `is_admin` from fillable/casts; added `$appends = ['is_admin']` and `getIsAdminAttribute()` that returns `$this->inGroup('admin')` (using loaded relations when possible to avoid N+1). `isAdmin()` now returns only `$this->inGroup('admin')`. `toSearchableArray()` no longer includes `is_admin`.
- **Auth (register, SSO)**: When `User::count() === 0`, call `ensureDefaultGroupsExist()`, create user without `is_admin`, assign to admin group only. Otherwise assign default group.
- **UserController**: Create user accepts `admin` boolean (assigns admin or default group). Update user does not accept `is_admin`; admin role is changed via `PUT /users/{id}/groups`. Toggle admin adds/removes user from the admin group; last-admin check uses count of users in admin group.
- **HasGroups, AdminAuthorizationTrait, channels, SearchController, CheckSuspiciousActivityCommand, MakeUserAdmin**: All use admin group (e.g. `inGroup('admin')`, `whereHas('groups', ... where('slug', 'admin'))`).
- **Frontend**: User dialog uses group_ids for edit (Admin switch syncs with admin group in selectedGroupIds); create still sends `admin` boolean. Added `isAdminUser(user)` in auth.ts; user table and dialog use group-based admin for display and toggle.

## Challenges Encountered

- Tests and docs referenced `is_admin` in many places; all were updated to use group-based admin (factory `admin()` state, `createAdminUser()`, or no flag for non-admin).
- Frontend create payload: backend expects `admin` (not `is_admin`) for new users; dialog was updated to send `admin`.

## Observations

- Keeping computed `is_admin` in the API response avoids breaking existing frontend consumers and allows a phased switch to group-based checks (e.g. `isAdminUser(user)`).
- UserGroupSeeder no longer migrates users by `is_admin`; the dedicated migration handles that for existing installs.

## Trade-offs

- Single source of truth for admin is the admin group; the computed `is_admin` is derived and could be removed from the API later if the frontend uses `isAdminUser()` everywhere.

## Next Steps (Future Considerations)

- Optionally remove computed `is_admin` from API once all frontend code uses `isAdminUser(user)` or group-based checks.

## Testing Notes

- Backend: UserFactory `admin()` state, `createAdminUser()`, and all feature tests updated to use group-based admin. Run `php artisan test` (or equivalent in Docker).
- Frontend: User dialog create/edit and group picker; toggle admin in table; Admin badge and menu label use `isAdminUser(user)`.
