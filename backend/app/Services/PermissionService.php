<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    private const CACHE_TTL = 3600;

    private const CACHE_PREFIX = 'permissions:';

    /**
     * Check if user has a permission. Results are cached per user (single key per user for SQLite compatibility).
     */
    public function check(User $user, string|Permission $permission, ?string $resourceType = null, ?int $resourceId = null): bool
    {
        $permValue = $permission instanceof Permission ? $permission->value : $permission;

        if ($resourceType !== null || $resourceId !== null) {
            return $user->hasPermission($permValue, $resourceType, $resourceId);
        }

        $cacheKey = self::CACHE_PREFIX . $user->id;
        $granted = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $this->computeGrantedPermissions($user);
        });

        return in_array($permValue, $granted, true);
    }

    /**
     * Clear all permission cache for a user (single key per user).
     */
    public function clearUserCache(User $user): void
    {
        Cache::forget(self::CACHE_PREFIX . $user->id);
    }

    /**
     * Clear permission cache for all members of a group.
     */
    public function clearGroupCache(UserGroup $group): void
    {
        $group->members->each(fn (User $user) => $this->clearUserCache($user));
    }

    /**
     * Compute the list of granted permission strings (global only) for a user.
     *
     * @return array<int, string>
     */
    private function computeGrantedPermissions(User $user): array
    {
        if ($user->inGroup('admin')) {
            return Permission::all();
        }

        $permissions = [];
        foreach ($user->groups as $group) {
            foreach ($group->permissions as $perm) {
                if ($perm->resource_type === null && $perm->resource_id === null) {
                    $permissions[$perm->permission] = true;
                }
            }
        }

        return array_keys($permissions);
    }
}
