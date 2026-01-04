<?php

namespace App\Policies;

use App\Models\AIJob;
use App\Models\User;

/**
 * Policy for AIJob model.
 * 
 * Users can only access their own AI jobs.
 */
class AIJobPolicy
{
    /**
     * Determine whether the user can view any AI jobs.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the AI job.
     */
    public function view(User $user, AIJob $aiJob): bool
    {
        return $user->id === $aiJob->user_id;
    }

    /**
     * Determine whether the user can create AI jobs.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the AI job.
     */
    public function update(User $user, AIJob $aiJob): bool
    {
        return $user->id === $aiJob->user_id;
    }

    /**
     * Determine whether the user can delete the AI job.
     */
    public function delete(User $user, AIJob $aiJob): bool
    {
        return $user->id === $aiJob->user_id;
    }

    /**
     * Determine whether the user can cancel the AI job.
     */
    public function cancel(User $user, AIJob $aiJob): bool
    {
        return $user->id === $aiJob->user_id && $aiJob->canBeCancelled();
    }
}
