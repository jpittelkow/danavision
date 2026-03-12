<?php

namespace App\Http\Traits;

use App\Models\User;
use Illuminate\Http\JsonResponse;

trait AdminAuthorizationTrait
{
    /**
     * Ensure the user is not the last admin (last user in admin group). Returns an error response if so, null otherwise.
     */
    protected function ensureNotLastAdmin(User $user, string $action = 'modify'): ?JsonResponse
    {
        $adminGroupCount = User::whereHas('groups', fn ($q) => $q->where('slug', 'admin'))->count();
        if ($user->inGroup('admin') && $adminGroupCount === 1) {
            return response()->json([
                'message' => "Cannot {$action} the last admin account",
            ], 400);
        }

        return null;
    }
}
