<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Log;

class GroupService
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Create a new group.
     */
    public function create(array $data): UserGroup
    {
        $group = UserGroup::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_default' => $data['is_default'] ?? false,
        ]);

        Log::info('Group created', ['group_id' => $group->id, 'slug' => $group->slug]);

        return $group;
    }

    /**
     * Update a group. System group slug cannot be changed.
     */
    public function update(UserGroup $group, array $data): UserGroup
    {
        if ($group->is_system && isset($data['slug'])) {
            unset($data['slug']);
        }

        $group->update($data);

        Log::info('Group updated', ['group_id' => $group->id]);

        return $group->fresh();
    }

    /**
     * Delete a group. System groups cannot be deleted.
     */
    public function delete(UserGroup $group): bool
    {
        if ($group->is_system) {
            return false;
        }

        $this->permissionService->clearGroupCache($group);
        $group->delete();

        Log::info('Group deleted', ['group_id' => $group->id]);

        return true;
    }

    /**
     * Add users to a group.
     */
    public function addMembers(UserGroup $group, array $userIds): void
    {
        $group->members()->syncWithoutDetaching($userIds);

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $this->permissionService->clearUserCache($user);
            }
        }
    }

    /**
     * Remove users from a group.
     */
    public function removeMembers(UserGroup $group, array $userIds): void
    {
        $group->members()->detach($userIds);

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $this->permissionService->clearUserCache($user);
            }
        }
    }

    /**
     * Replace all permissions for a group.
     *
     * @param array<int, array{permission: string, resource_type?: string|null, resource_id?: int|null}> $permissions
     */
    public function setPermissions(UserGroup $group, array $permissions): void
    {
        $group->permissions()->delete();

        foreach ($permissions as $perm) {
            $group->permissions()->create([
                'permission' => $perm['permission'],
                'resource_type' => $perm['resource_type'] ?? null,
                'resource_id' => $perm['resource_id'] ?? null,
            ]);
        }

        $this->permissionService->clearGroupCache($group);
    }

    /**
     * Get the default group for new users.
     */
    public function getDefaultGroup(): ?UserGroup
    {
        return UserGroup::where('is_default', true)->first();
    }

    /**
     * Assign the default group to a user (e.g. on registration).
     */
    public function assignDefaultGroupToUser(User $user): void
    {
        $defaultGroup = $this->getDefaultGroup();
        if ($defaultGroup) {
            $user->assignGroup($defaultGroup);
        }
    }

    /**
     * Ensure default groups (Administrators, Users) exist. Idempotent.
     * Call before creating the first user so registration works without running the seeder.
     */
    public function ensureDefaultGroupsExist(): void
    {
        if (UserGroup::where('slug', 'admin')->exists()) {
            return;
        }

        $admin = UserGroup::create([
            'name' => 'Administrators',
            'slug' => 'admin',
            'description' => 'Full system access',
            'is_system' => true,
            'is_default' => false,
        ]);

        $userGroup = UserGroup::create([
            'name' => 'Users',
            'slug' => 'user',
            'description' => 'Standard user access',
            'is_system' => true,
            'is_default' => true,
        ]);

        $this->assignAdminPermissionsToGroup($admin);
        $this->assignUserPermissionsToGroup($userGroup);

        Log::info('Default groups created', ['admin_id' => $admin->id, 'user_group_id' => $userGroup->id]);
    }

    private function assignAdminPermissionsToGroup(UserGroup $admin): void
    {
        foreach (Permission::all() as $permission) {
            $admin->permissions()->create([
                'permission' => $permission,
                'resource_type' => null,
                'resource_id' => null,
            ]);
        }
    }

    private function assignUserPermissionsToGroup(UserGroup $userGroup): void
    {
        $basicPermissions = [
            Permission::USERS_VIEW->value,
        ];

        foreach ($basicPermissions as $permission) {
            $userGroup->permissions()->create([
                'permission' => $permission,
                'resource_type' => null,
                'resource_id' => null,
            ]);
        }
    }
}
