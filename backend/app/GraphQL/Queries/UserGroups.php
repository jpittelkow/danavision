<?php

namespace App\GraphQL\Queries;

use App\Models\UserGroup;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;

class UserGroups
{
    public function __invoke($root, array $args, $context): array
    {
        $user = Auth::guard('api-key')->user();

        // Defense in depth: verify authorization at resolver level
        if (!$user->can('groups.view')) {
            throw new Error('Unauthorized', extensions: ['code' => 'FORBIDDEN']);
        }

        return UserGroup::with('permissions')
            ->withCount('members')
            ->orderBy('name')
            ->get()
            ->map(function ($group) {
                return array_merge($group->toArray(), [
                    'memberCount' => $group->members_count,
                    'permissions' => $group->permissions->pluck('permission')->toArray(),
                ]);
            })
            ->toArray();
    }
}
