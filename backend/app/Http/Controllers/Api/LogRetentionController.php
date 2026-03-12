<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogRetentionController extends Controller
{
    public const GROUP = 'logging';

    public const ACCESS_MIN_DAYS = 2190; // HIPAA 6 years

    public function __construct(
        private SettingService $settingService,
        private AuditService $auditService
    ) {}

    /**
     * Get log retention settings.
     */
    public function show(): JsonResponse
    {
        $settings = $this->settingService->getGroup(self::GROUP);
        $schema = config('settings-schema.logging', []);
        $defaults = [
            'app_retention_days' => $schema['app_retention_days']['default'] ?? 90,
            'audit_retention_days' => $schema['audit_retention_days']['default'] ?? 365,
            'access_retention_days' => $schema['access_retention_days']['default'] ?? self::ACCESS_MIN_DAYS,
            'hipaa_access_logging_enabled' => $schema['hipaa_access_logging_enabled']['default'] ?? true,
        ];
        $settings = array_merge($defaults, $settings);
        $settings['hipaa_access_logging_enabled'] = filter_var(
            $settings['hipaa_access_logging_enabled'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        return response()->json([
            'settings' => $settings,
            'access_min_days' => self::ACCESS_MIN_DAYS,
        ]);
    }

    /**
     * Update log retention settings. Access logs cannot be set below 6 years (HIPAA).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_retention_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'audit_retention_days' => ['sometimes', 'integer', 'min:30', 'max:730'],
            'access_retention_days' => ['sometimes', 'integer', 'min:' . self::ACCESS_MIN_DAYS, 'max:10000'],
            'hipaa_access_logging_enabled' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $oldSettings = $this->settingService->getGroup(self::GROUP);
        foreach ($validated as $key => $value) {
            if ($key === 'hipaa_access_logging_enabled') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $this->settingService->set(self::GROUP, $key, $value, $user->id);
        }

        $this->auditService->logSettings('log_retention', $oldSettings, $validated, $user->id);

        return response()->json([
            'message' => 'Log retention settings updated.',
        ]);
    }
}
