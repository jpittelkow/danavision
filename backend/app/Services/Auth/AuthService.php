<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    /**
     * Attempt credential-based login.
     *
     * @return array{authenticated: bool, user?: User, requires_2fa?: bool, disabled?: bool}
     */
    public function attemptLogin(string $email, string $password, bool $remember = false): array
    {
        if (!Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            return ['authenticated' => false];
        }

        $user = Auth::user();

        if ($user->isDisabled()) {
            return ['authenticated' => false, 'user' => $user, 'disabled' => true];
        }

        if ($user->hasTwoFactorEnabled()) {
            return ['authenticated' => false, 'user' => $user, 'requires_2fa' => true];
        }

        return ['authenticated' => true, 'user' => $user];
    }
}
