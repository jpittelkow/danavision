<?php

namespace App\Policies;

use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ListItemPolicy
{
    /**
     * Determine whether the user can view the list item.
     */
    public function view(User $user, ListItem $listItem): bool
    {
        return Gate::allows('view', $listItem->shoppingList);
    }

    /**
     * Determine whether the user can create list items.
     */
    public function create(User $user, ShoppingList $shoppingList): bool
    {
        return Gate::allows('update', $shoppingList);
    }

    /**
     * Determine whether the user can update the list item.
     */
    public function update(User $user, ListItem $listItem): bool
    {
        return Gate::allows('update', $listItem->shoppingList);
    }

    /**
     * Determine whether the user can delete the list item.
     */
    public function delete(User $user, ListItem $listItem): bool
    {
        return Gate::allows('update', $listItem->shoppingList);
    }
}
