<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;

class ShoppingListPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ShoppingList $list): bool
    {
        return $this->isOwnerOrSharedUser($user, $list);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ShoppingList $list): bool
    {
        return $this->isOwnerOrHasPermission($user, $list, 'edit');
    }

    public function delete(User $user, ShoppingList $list): bool
    {
        return $list->user_id === $user->id;
    }

    public function share(User $user, ShoppingList $list): bool
    {
        return $list->user_id === $user->id || $this->hasSharePermission($user, $list, 'admin');
    }

    public function manageItems(User $user, ShoppingList $list): bool
    {
        return $this->isOwnerOrHasPermission($user, $list, 'edit');
    }

    private function isOwnerOrSharedUser(User $user, ShoppingList $list): bool
    {
        if ($list->user_id === $user->id) return true;
        return $list->shares()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->whereNull('declined_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    private function isOwnerOrHasPermission(User $user, ShoppingList $list, string $permission): bool
    {
        if ($list->user_id === $user->id) return true;
        return $this->hasSharePermission($user, $list, $permission);
    }

    private function hasSharePermission(User $user, ShoppingList $list, string $requiredPermission): bool
    {
        $share = $list->shares()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->whereNull('declined_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if (!$share) return false;

        $levels = ['view' => 1, 'edit' => 2, 'admin' => 3];
        return ($levels[$share->permission] ?? 0) >= ($levels[$requiredPermission] ?? 0);
    }
}
