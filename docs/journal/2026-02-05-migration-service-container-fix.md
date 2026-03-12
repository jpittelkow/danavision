# Migration Service Container Fix - 2026-02-05

## Overview

Fixed a database migration that was causing the Docker container to crash-loop on fresh installs. The migration `2026_01_30_000020_remove_is_admin_from_users_table` used Laravel's service container to resolve `GroupService`, which failed during the migration bootstrap process.

## Problem

When running `docker-compose up -d --build` on a fresh database (after `docker-compose down -v`), the container would start, attempt to run migrations, and fail with:

```
RuntimeException: Default groups (admin, user) must exist. Run user_groups migration and GroupService::ensureDefaultGroupsExist first.
```

The migration called `app(GroupService::class)->ensureDefaultGroupsExist()` to create default user groups. This approach has several issues during migrations:

1. **Service container resolution**: The service container may not be fully bootstrapped during migrations
2. **Constructor dependencies**: `GroupService` depends on `PermissionService`, adding complexity
3. **Eloquent model usage**: The service uses Eloquent models which may have boot issues during migrations
4. **Silent failures**: If the service call fails, it may not throw an exception, causing the subsequent check to fail

## Solution

Made the migration self-contained by replacing the service call with direct SQL inserts:

```php
// Before (problematic)
app(GroupService::class)->ensureDefaultGroupsExist();

// After (reliable)
$this->ensureDefaultGroupsExist(); // Private method using DB::table()
```

The private method:
- Uses `DB::table()->insertGetId()` for group creation
- Uses `DB::table()->insert()` for permissions
- Is idempotent (checks if groups exist before creating)
- Has no external dependencies

## Files Changed

- `backend/database/migrations/2026_01_30_000020_remove_is_admin_from_users_table.php`
  - Removed `use App\Services\GroupService` import
  - Added `PERMISSIONS` constant (synced with `App\Enums\Permission`)
  - Added private `ensureDefaultGroupsExist()` method
  - Changed service call to method call

## Key Learning

**Migrations should be self-contained and not depend on application services or Eloquent models.**

During migrations:
- The application may not be fully bootstrapped
- Service providers may not have run
- Models may have boot methods that fail
- Service constructors may fail to resolve dependencies

Use raw `DB::table()` queries in migrations for reliability.

## Testing

1. `docker-compose down -v` (remove all volumes including database)
2. `docker-compose up -d --build` (fresh build and start)
3. Container should reach "healthy" status
4. Migrations should complete without errors

## Related

- [Anti-pattern: Don't use service classes in migrations](../ai/anti-patterns.md#dont-use-service-classes-in-migrations)
- Migration file: `2026_01_30_000020_remove_is_admin_from_users_table.php`
- Related journal: `2026-01-30-remove-is-admin-group-only.md`
