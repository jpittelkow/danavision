<?php

namespace App\Policies;

use App\Models\AIRequestLog;
use App\Models\User;

/**
 * Policy for AIRequestLog model.
 * 
 * Users can only access their own AI request logs.
 */
class AIRequestLogPolicy
{
    /**
     * Determine whether the user can view any AI request logs.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the AI request log.
     */
    public function view(User $user, AIRequestLog $log): bool
    {
        return $user->id === $log->user_id;
    }

    /**
     * Determine whether the user can delete the AI request log.
     */
    public function delete(User $user, AIRequestLog $log): bool
    {
        return $user->id === $log->user_id;
    }

    /**
     * Determine whether the user can delete all their AI request logs.
     */
    public function deleteAll(User $user): bool
    {
        return true;
    }
}
