<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private AuditService $auditService
    ) {}

    /**
     * Get 2FA status for current user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmed' => $user->two_factor_confirmed_at !== null,
        ]);
    }

    /**
     * Enable 2FA - generate secret and QR code.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled',
            ], 400);
        }

        $data = $this->twoFactorService->generateSecret($user);

        return response()->json([
            'message' => 'Two-factor authentication setup initiated',
            'secret' => $data['secret'],
            'qr_code' => $data['qr_code'],
        ]);
    }

    /**
     * Confirm 2FA setup with code verification.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled',
            ], 400);
        }

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Please initiate 2FA setup first',
            ], 400);
        }

        if (!$this->twoFactorService->verifyCode($user, $request->code)) {
            return response()->json([
                'message' => 'Invalid verification code',
            ], 400);
        }

        $recoveryCodes = $this->twoFactorService->confirmSetup($user);

        $this->auditService->logAuth('2fa_enabled', $user);

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        $this->twoFactorService->disable($user);

        $this->auditService->logAuth('2fa_disabled', $user);

        return response()->json([
            'message' => 'Two-factor authentication disabled',
        ]);
    }

    /**
     * Verify 2FA code during login.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'is_recovery_code' => ['sometimes', 'boolean'],
        ]);

        $userId = $request->session()->get('2fa:user_id');

        if (!$userId) {
            return response()->json([
                'message' => 'No pending two-factor authentication',
            ], 400);
        }

        $user = \App\Models\User::find($userId);

        if (!$user) {
            $request->session()->forget('2fa:user_id');
            return response()->json([
                'message' => 'User not found',
            ], 400);
        }

        try {
            $this->twoFactorService->completePendingVerification(
                $user,
                $request->code,
                $request->boolean('is_recovery_code', false)
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'Two-factor verification failed. Please try again or contact support.',
            ], 500);
        }

        // Clear 2FA session, set verified flag, and login user
        $request->session()->forget('2fa:user_id');
        $request->session()->put('2fa:verified', true);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->auditService->logAuth('login', $user);

        return response()->json([
            'message' => 'Two-factor authentication verified',
            'user' => $user,
        ]);
    }

    /**
     * Get recovery codes.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        return response()->json([
            'recovery_codes' => $user->two_factor_recovery_codes,
        ]);
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled',
            ], 400);
        }

        $recoveryCodes = $this->twoFactorService->regenerateRecoveryCodes($user);

        return response()->json([
            'message' => 'Recovery codes regenerated',
            'recovery_codes' => $recoveryCodes,
        ]);
    }
}
