<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;

class ShoppingListPolicy
{
    /**
     * Determine whether the user can view the shopping list.
     */
    public function view(User $user, ShoppingList $shoppingList): bool
    {
        // Owner can always view
        if ($user->id === $shoppingList->user_id) {
            return true;
        }

        // Shared users with accepted invitation can view
        return $shoppingList->shares()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Determine whether the user can create shopping lists.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the shopping list.
     */
    public function update(User $user, ShoppingList $shoppingList): bool
    {
        // Owner can always update
        if ($user->id === $shoppingList->user_id) {
            return true;
        }

        // Shared users with edit or admin permission can update
        return $shoppingList->shares()
            ->where('user_id', $user->id)
            ->whereIn('permission', ['edit', 'admin'])
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Determine whether the user can delete the shopping list.
     */
    public function delete(User $user, ShoppingList $shoppingList): bool
    {
        // Only owner can delete
        return $user->id === $shoppingList->user_id;
    }

    /**
     * Determine whether the user can share the shopping list.
     */
    public function share(User $user, ShoppingList $shoppingList): bool
    {
        // Owner can always share
        if ($user->id === $shoppingList->user_id) {
            return true;
        }

        // Admin shared users can share
        return $shoppingList->shares()
            ->where('user_id', $user->id)
            ->where('permission', 'admin')
            ->whereNotNull('accepted_at')
            ->exists();
    }
}
