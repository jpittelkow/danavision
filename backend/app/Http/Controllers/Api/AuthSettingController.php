<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\AuditService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuthSettingController extends Controller
{
    use ApiResponseTrait;

    private const GROUP = 'auth';

    private const MODES = ['disabled', 'optional', 'required'];

    public function __construct(
        private SettingService $settingService,
        private AuditService $auditService
    ) {}

    /**
     * Get auth settings.
     */
    public function show(): JsonResponse
    {
        $settings = $this->settingService->getGroup(self::GROUP);

        return $this->dataResponse([
            'settings' => $settings,
        ]);
    }

    /**
     * Update auth settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_verification_mode' => ['sometimes', 'string', 'in:' . implode(',', self::MODES)],
            'password_reset_enabled' => ['sometimes', 'boolean'],
            'two_factor_mode' => ['sometimes', 'string', 'in:' . implode(',', self::MODES)],
            'passkey_mode' => ['sometimes', 'string', 'in:' . implode(',', self::MODES)],
        ]);

        $userId = $request->user()->id;
        $oldSettings = $this->settingService->getGroup(self::GROUP);

        foreach ($validated as $key => $value) {
            $this->settingService->set(self::GROUP, $key, $value, $userId);
        }
        $this->auditService->logSettings(self::GROUP, $oldSettings, $validated, $userId);

        Cache::forget('system_settings_public');

        return $this->successResponse('Auth settings updated successfully');
    }
}
