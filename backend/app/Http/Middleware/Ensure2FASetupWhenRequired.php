<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Ensure2FASetupWhenRequired
{
    public function __construct(
        private SettingService $settingService
    ) {}

    /**
     * When auth.two_factor_mode is 'required', block access until user has set up 2FA.
     * Allows frontend to redirect to 2FA setup (auth prefix routes are not in this middleware group).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = $this->settingService->get('auth', 'two_factor_mode', 'optional');
        if ($mode !== 'required') {
            return $next($request);
        }

        $user = $request->user();
        if (!$user || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Two-factor authentication is required. Please set up 2FA in Security settings.',
            'requires_2fa_setup' => true,
        ], 403);
    }
}
