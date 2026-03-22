<?php

namespace App\Services\Sharing;

use App\Models\ListShare;
use App\Models\ShoppingList;
use App\Models\User;
use App\Notifications\ListShareInvitationNotification;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ListSharingService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Share a list with a target user.
     */
    public function shareList(
        ShoppingList $list,
        User $targetUser,
        string $permission = 'view',
        ?string $message = null,
        ?Carbon $expiresAt = null,
    ): ListShare {
        $share = ListShare::create([
            'shopping_list_id' => $list->id,
            'user_id' => $targetUser->id,
            'shared_by_user_id' => $list->user_id,
            'permission' => $permission,
            'message' => $message,
            'expires_at' => $expiresAt,
        ]);

        $this->auditService->log('list_share.created', $share, [], [
            'shopping_list_id' => $list->id,
            'target_user_id' => $targetUser->id,
            'permission' => $permission,
        ], $list->user_id);

        // Send share invitation notification to the target user
        try {
            $sharedByUser = User::find($list->user_id);
            (new ListShareInvitationNotification(
                $list->name,
                $sharedByUser?->name ?? 'Someone',
                $permission,
                $list->id,
                $share->id,
            ))->send($targetUser);
        } catch (\Exception $e) {
            Log::warning('ListSharingService: failed to send share notification', [
                'share_id' => $share->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $share;
    }

    /**
     * Accept a pending share invitation.
     */
    public function acceptShare(ListShare $share): ListShare
    {
        $share->update(['accepted_at' => now()]);

        $this->auditService->log('list_share.accepted', $share, [], [
            'shopping_list_id' => $share->shopping_list_id,
        ], $share->user_id);

        return $share;
    }

    /**
     * Decline a pending share invitation.
     */
    public function declineShare(ListShare $share): ListShare
    {
        $share->update(['declined_at' => now()]);

        $this->auditService->log('list_share.declined', $share, [], [
            'shopping_list_id' => $share->shopping_list_id,
        ], $share->user_id);

        return $share;
    }

    /**
     * Revoke (delete) an existing share.
     */
    public function revokeShare(ListShare $share): void
    {
        $this->auditService->log('list_share.revoked', $share, $share->getAttributes());

        $share->delete();
    }

    /**
     * Update the permission level on an existing share.
     */
    public function updatePermission(ListShare $share, string $permission): ListShare
    {
        $oldPermission = $share->permission;
        $share->update(['permission' => $permission]);

        $this->auditService->log('list_share.updated', $share, [
            'permission' => $oldPermission,
        ], [
            'permission' => $permission,
        ]);

        return $share;
    }

    /**
     * Get all lists a user owns or has accepted shares for.
     */
    public function getAccessibleLists(User $user): Collection
    {
        $ownedLists = ShoppingList::where('user_id', $user->id)->get();

        $sharedLists = ShoppingList::whereHas('shares', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereNotNull('accepted_at')
                ->whereNull('declined_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        })->get();

        return $ownedLists->merge($sharedLists);
    }

    /**
     * Get pending share invitations for a user.
     */
    public function getPendingShares(User $user): Collection
    {
        return ListShare::where('user_id', $user->id)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->with(['shoppingList', 'sharedBy'])
            ->get();
    }

    /**
     * Check if a user has the required permission level on a list.
     *
     * Returns true if the user is the list owner or has a share with
     * sufficient permission (admin > edit > view).
     */
    public function checkPermission(ShoppingList $list, User $user, string $requiredPermission): bool
    {
        // Owner always has full access
        if ($list->user_id === $user->id) {
            return true;
        }

        $share = ListShare::where('shopping_list_id', $list->id)
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->whereNull('declined_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $share) {
            return false;
        }

        return $share->hasPermission($requiredPermission);
    }
}
