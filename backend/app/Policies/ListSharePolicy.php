<?php

namespace App\Policies;

use App\Models\ListShare;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ListSharePolicy
{
    /**
     * Determine whether the user can view the share.
     */
    public function view(User $user, ListShare $listShare): bool
    {
        return Gate::allows('view', $listShare->shoppingList);
    }

    /**
     * Determine whether the user can update the share.
     */
    public function update(User $user, ListShare $listShare): bool
    {
        return Gate::allows('share', $listShare->shoppingList);
    }

    /**
     * Determine whether the user can delete the share.
     */
    public function delete(User $user, ListShare $listShare): bool
    {
        // List owner can remove any share
        if ($user->id === $listShare->shoppingList->user_id) {
            return true;
        }

        // The person who created the share can remove it
        if ($user->id === $listShare->shared_by_user_id) {
            return true;
        }

        // The shared user can remove themselves
        return $user->id === $listShare->user_id;
    }
}
