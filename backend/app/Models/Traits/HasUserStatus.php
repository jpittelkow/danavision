<?php

namespace App\Models\Traits;

trait HasUserStatus
{
    /**
     * Check if user account is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    /**
     * Check if user has two-factor authentication enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_confirmed_at !== null;
    }
}
