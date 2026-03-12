<?php

namespace App\Traits;

use App\Enums\Permission;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasGroups
{
    /**
     * Groups this user belongs to.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_members', 'user_id', 'group_id')
            ->withTimestamps();
    }

    /**
     * Check if user is in a group (by slug or UserGroup instance).
     * Uses loaded relation when available to avoid N+1 queries.
     */
    public function inGroup(string|UserGroup $group): bool
    {
        $slug = $group instanceof UserGroup ? $group->slug : $group;

        if (array_key_exists('groups', $this->relations) && $this->relationLoaded('groups')) {
            return $this->groups->contains('slug', $slug);
        }

        return $this->groups()->where('slug', $slug)->exists();
    }

    /**
     * Check if user has a permission (from any of their groups).
     * Users in the admin group have all permissions.
     */
    public function hasPermission(string|Permission $permission, ?string $resourceType = null, ?int $resourceId = null): bool
    {
        $permValue = $permission instanceof Permission ? $permission->value : $permission;

        if ($this->inGroup('admin')) {
            return true;
        }

        $groups = $this->groups;
        if ($groups->isEmpty()) {
            return false;
        }

        return $groups->contains(fn (UserGroup $group) => $group->hasPermission($permValue, $resourceType, $resourceId));
    }

    /**
     * Assign user to a group (by slug or UserGroup instance).
     */
    public function assignGroup(string|UserGroup $group): void
    {
        $groupId = $group instanceof UserGroup ? $group->id : UserGroup::where('slug', $group)->firstOrFail()->id;
        $this->groups()->syncWithoutDetaching([$groupId]);
    }

    /**
     * Remove user from a group (by slug or UserGroup instance).
     */
    public function removeFromGroup(string|UserGroup $group): void
    {
        $groupId = $group instanceof UserGroup ? $group->id : UserGroup::where('slug', $group)->firstOrFail()->id;
        $this->groups()->detach($groupId);
    }
}
