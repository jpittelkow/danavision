<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function __construct(
        private SettingService $settingService
    ) {}

    /**
     * Handle an incoming request.
     * Only enforces verification when auth.email_verification_mode is 'required'.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = $this->settingService->get('auth', 'email_verification_mode', 'optional');
        if ($mode !== 'required') {
            return $next($request);
        }

        if (!$request->user() || !$request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email address is not verified.',
            ], 403);
        }

        return $next($request);
    }
}
