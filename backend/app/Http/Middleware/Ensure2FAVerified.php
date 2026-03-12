<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Ensure2FAVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasTwoFactorEnabled()) {
            // Check if 2FA has been verified for this session
            if (!$request->session()->has('2fa:verified')) {
                return response()->json([
                    'message' => 'Two-factor authentication required.',
                    'requires_2fa' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}
