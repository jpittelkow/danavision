<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ListShare;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\Sharing\ListSharingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListShareController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ListSharingService $listSharingService
    ) {}

    /**
     * List all shares for a shopping list.
     */
    public function index(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeListOwner($request, $list);

        $shares = $list->shares()->with(['user:id,name,email', 'sharedBy:id,name,email'])->get();

        return response()->json(['data' => $shares]);
    }

    /**
     * Create a share invitation for a shopping list.
     *
     * Frontend sends { email, permission, message? } — we look up the user by email.
     */
    public function store(Request $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeListOwner($request, $list);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'permission' => ['required', 'string', 'in:view,edit,admin'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $targetUser = User::where('email', $validated['email'])->first();

        if (!$targetUser) {
            return $this->errorResponse('No user found with that email address.', 422);
        }

        if ($targetUser->id === $request->user()->id) {
            return $this->errorResponse('You cannot share a list with yourself.', 422);
        }

        // Check if already shared with this user
        $existingShare = $list->shares()
            ->where('user_id', $targetUser->id)
            ->whereNull('declined_at')
            ->first();

        if ($existingShare) {
            return $this->errorResponse('This list is already shared with that user.', 422);
        }

        $share = $this->listSharingService->shareList(
            $list,
            $targetUser,
            $validated['permission'],
            $validated['message'] ?? null,
        );

        $share->load(['user:id,name,email']);

        return $this->createdResponse('Share invitation sent', ['data' => $share]);
    }

    /**
     * Update share permission.
     */
    public function update(Request $request, ListShare $share): JsonResponse
    {
        $this->authorizeShareOwner($request, $share);

        $validated = $request->validate([
            'permission' => ['required', 'string', 'in:view,edit,admin'],
        ]);

        $share = $this->listSharingService->updatePermission($share, $validated['permission']);

        return $this->successResponse('Share permission updated', ['data' => $share]);
    }

    /**
     * Revoke a share.
     */
    public function destroy(Request $request, ListShare $share): JsonResponse
    {
        $this->authorizeShareOwner($request, $share);

        $this->listSharingService->revokeShare($share);

        return $this->deleteResponse('Share revoked successfully');
    }

    /**
     * Get pending share invitations for the current user.
     */
    public function pending(Request $request): JsonResponse
    {
        $pending = $this->listSharingService->getPendingShares($request->user());

        return response()->json(['data' => $pending]);
    }

    /**
     * Accept a share invitation.
     */
    public function accept(Request $request, ListShare $share): JsonResponse
    {
        $this->authorizeShareRecipient($request, $share);

        $share = $this->listSharingService->acceptShare($share);

        return $this->successResponse('Share invitation accepted', ['data' => $share]);
    }

    /**
     * Decline a share invitation.
     */
    public function decline(Request $request, ListShare $share): JsonResponse
    {
        $this->authorizeShareRecipient($request, $share);

        $this->listSharingService->declineShare($share);

        return $this->successResponse('Share invitation declined');
    }

    /**
     * Authorize that the current user owns the list associated with the share.
     */
    private function authorizeListOwner(Request $request, ShoppingList $list): void
    {
        if ($list->user_id !== $request->user()->id) {
            abort(403, 'Only the list owner can manage shares.');
        }
    }

    /**
     * Authorize that the current user owns the list that the share belongs to.
     */
    private function authorizeShareOwner(Request $request, ListShare $share): void
    {
        $list = $share->shoppingList;

        if ($list->user_id !== $request->user()->id) {
            abort(403, 'Only the list owner can manage shares.');
        }
    }

    /**
     * Authorize that the current user is the recipient of the share invitation.
     */
    private function authorizeShareRecipient(Request $request, ListShare $share): void
    {
        if ($share->user_id !== $request->user()->id) {
            abort(403, 'You are not the recipient of this share invitation.');
        }
    }
}
