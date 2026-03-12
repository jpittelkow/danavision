<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\SystemSetting;
use App\Services\Notifications\NotificationChannelMetadata;
use App\Services\Notifications\NotificationOrchestrator;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationChannelConfigController extends Controller
{
    use ApiResponseTrait;
    use NotificationChannelMetadata;
    private const GROUP = 'notifications';

    public function __construct(
        private NotificationOrchestrator $orchestrator,
        private SettingService $settingService
    ) {}

    /**
     * List all notification channels with provider-configured and available status.
     * Admin-only: used by Configuration > Notifications.
     */
    public function index(): JsonResponse
    {
        $channelConfig = config('notifications.channels');
        $smsProvider = SystemSetting::get('sms_provider', null, self::GROUP);

        $channels = collect($channelConfig)->map(function (array $config, string $id) use ($smsProvider) {
            // User-configurable channels (Slack, Discord) don't need global credentials
            // Users provide their own webhooks, so these are always "provider configured"
            $providerConfigured = $this->isUserConfigurableChannel($id)
                ? true
                : (bool) ($config['enabled'] ?? false);
            $alwaysAvailable = $this->isAlwaysAvailableChannel($id);
            $available = $alwaysAvailable
                ? true
                : filter_var(
                    SystemSetting::get("channel_{$id}_available", false, self::GROUP),
                    FILTER_VALIDATE_BOOLEAN
                );

            return [
                'id' => $id,
                'name' => $this->getChannelName($id),
                'description' => $this->getChannelDescription($id),
                'provider_configured' => $providerConfigured,
                'available' => $available,
                'admin_toggle' => !$alwaysAvailable,
                'sms_provider' => in_array($id, ['twilio', 'vonage', 'sns'], true) ? $id === $smsProvider : null,
            ];
        })->values();

        $smsProvidersConfigured = [];
        foreach (['twilio', 'vonage', 'sns'] as $id) {
            if ((bool) (config("notifications.channels.{$id}.enabled") ?? false)) {
                $smsProvidersConfigured[] = $id;
            }
        }

        return response()->json([
            'channels' => $channels,
            'sms_provider' => $smsProvider,
            'sms_providers_configured' => $smsProvidersConfigured,
        ]);
    }

    /**
     * Update channel availability and SMS preferred provider.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channels' => ['sometimes', 'array'],
            'channels.*.id' => ['required_with:channels', 'string'],
            'channels.*.available' => ['required_with:channels', 'boolean'],
            'sms_provider' => ['sometimes', 'nullable', 'string', 'in:twilio,vonage,sns'],
        ]);

        $user = $request->user();
        $updatedBy = $user?->id;

        if (isset($validated['channels'])) {
            foreach ($validated['channels'] as $entry) {
                $id = $entry['id'];
                if (!NotificationOrchestrator::isKnownChannel($id) || $this->isAlwaysAvailableChannel($id)) {
                    continue;
                }
                SystemSetting::set("channel_{$id}_available", $entry['available'], self::GROUP, $updatedBy);
            }
        }

        if (array_key_exists('sms_provider', $validated)) {
            SystemSetting::set('sms_provider', $validated['sms_provider'], self::GROUP, $updatedBy);
        }

        return response()->json(['message' => 'Notification channel config updated']);
    }

    /**
     * Test all enabled notification channels and return per-channel results.
     */
    public function testAll(Request $request): JsonResponse
    {
        $user = $request->user();

        // Apply latest settings from DB so tests use current credentials
        $settings = $this->settingService->getGroup(self::GROUP);
        $this->applyNotificationsConfigForRequest($settings);

        $channelConfig = config('notifications.channels');
        $results = [];

        foreach ($channelConfig as $id => $config) {
            $enabled = (bool) ($config['enabled'] ?? false);
            $available = $this->isAlwaysAvailableChannel($id) || filter_var(
                SystemSetting::get("channel_{$id}_available", false, self::GROUP),
                FILTER_VALIDATE_BOOLEAN
            );

            if (!$enabled && !$this->isUserConfigurableChannel($id)) {
                $results[$id] = ['status' => 'skipped', 'reason' => 'not_configured'];
                continue;
            }

            if (!$available) {
                $results[$id] = ['status' => 'skipped', 'reason' => 'not_available'];
                continue;
            }

            try {
                $this->orchestrator->sendTestNotification($user, $id);
                $results[$id] = ['status' => 'success'];
            } catch (\Exception $e) {
                $results[$id] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $this->dataResponse(['results' => $results]);
    }

    /**
     * Verify configuration status of all notification channels (without sending).
     */
    public function verify(): JsonResponse
    {
        $channelConfig = config('notifications.channels');
        $results = [];

        foreach ($channelConfig as $id => $config) {
            $enabled = (bool) ($config['enabled'] ?? false);
            $available = $this->isAlwaysAvailableChannel($id) || filter_var(
                SystemSetting::get("channel_{$id}_available", false, self::GROUP),
                FILTER_VALIDATE_BOOLEAN
            );

            $results[$id] = [
                'name' => $this->getChannelName($id),
                'provider_configured' => $this->isUserConfigurableChannel($id) ? true : $enabled,
                'available_to_users' => $available,
                'always_available' => $this->isAlwaysAvailableChannel($id),
            ];
        }

        return $this->dataResponse(['channels' => $results]);
    }

}
