<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    /**
     * Get user personal preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'theme' => $user->getSetting('appearance', 'theme', 'system'),
            'color_theme' => $user->getSetting('appearance', 'color_theme'),
            'default_llm_mode' => $user->getSetting('defaults', 'llm_mode', 'single'),
            'notification_channels' => $user->getSetting('notifications', 'preferences', []),
            'timezone' => $user->getSetting('general', 'timezone'),
            'effective_timezone' => $user->getTimezone(),
            'home_address' => $user->getSetting('shopping', 'home_address'),
            'home_zip_code' => $user->getSetting('shopping', 'home_zip_code'),
            'home_latitude' => $user->getSetting('shopping', 'home_latitude'),
            'home_longitude' => $user->getSetting('shopping', 'home_longitude'),
        ]);
    }

    /**
     * Update user personal preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $validTimezones = \DateTimeZone::listIdentifiers();

        // Validate only the fields that are present in the request
        $validated = $request->validate([
            'theme' => ['sometimes', 'nullable', 'string', 'in:light,dark,system'],
            'color_theme' => ['sometimes', 'nullable', 'string', 'max:50'],
            'default_llm_mode' => ['sometimes', 'nullable', 'string', 'in:single,aggregation,council'],
            'notification_channels' => ['sometimes', 'nullable', 'array'],
            'timezone' => ['sometimes', 'nullable', 'string', 'in:' . implode(',', $validTimezones)],
            'home_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'home_zip_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'home_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'home_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();

        $settingMap = [
            'theme'                 => ['group' => 'appearance',    'key' => 'theme'],
            'color_theme'           => ['group' => 'appearance',    'key' => 'color_theme',  'clearable' => true],
            'default_llm_mode'      => ['group' => 'defaults',      'key' => 'llm_mode'],
            'notification_channels' => ['group' => 'notifications', 'key' => 'preferences'],
            'timezone'              => ['group' => 'general',       'key' => 'timezone',     'clearable' => true],
            'home_address'          => ['group' => 'shopping',      'key' => 'home_address', 'clearable' => true],
            'home_zip_code'         => ['group' => 'shopping',      'key' => 'home_zip_code', 'clearable' => true],
            'home_latitude'         => ['group' => 'shopping',      'key' => 'home_latitude', 'clearable' => true],
            'home_longitude'        => ['group' => 'shopping',      'key' => 'home_longitude', 'clearable' => true],
        ];

        foreach ($settingMap as $field => $config) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($validated[$field] !== null) {
                $user->setSetting($config['group'], $config['key'], $validated[$field]);
            } elseif (! empty($config['clearable'])) {
                $user->settings()
                    ->where('group', $config['group'])
                    ->where('key', $config['key'])
                    ->delete();
            }
        }

        // Clear cached provider location IDs when home address/coordinates change
        $locationFields = ['home_address', 'home_zip_code', 'home_latitude', 'home_longitude'];
        if (array_intersect_key($validated, array_flip($locationFields))) {
            app(\App\Services\PriceSearch\LocationOptionsResolver::class)->clearCachedLocationIds($user);
        }

        return response()->json([
            'message' => 'Preferences updated successfully',
            'preferences' => [
                'theme' => $user->getSetting('appearance', 'theme', 'system'),
                'color_theme' => $user->getSetting('appearance', 'color_theme'),
                'default_llm_mode' => $user->getSetting('defaults', 'llm_mode', 'single'),
                'notification_channels' => $user->getSetting('notifications', 'preferences', []),
                'timezone' => $user->getSetting('general', 'timezone'),
                'effective_timezone' => $user->getTimezone(),
                'home_address' => $user->getSetting('shopping', 'home_address'),
                'home_zip_code' => $user->getSetting('shopping', 'home_zip_code'),
                'home_latitude' => $user->getSetting('shopping', 'home_latitude'),
                'home_longitude' => $user->getSetting('shopping', 'home_longitude'),
            ],
        ]);
    }

    /**
     * Auto-detect timezone from browser.
     *
     * Only sets the timezone if the user hasn't explicitly chosen one,
     * to avoid overwriting manual choices on every login.
     */
    public function detectTimezone(Request $request): JsonResponse
    {
        $validTimezones = \DateTimeZone::listIdentifiers();

        $validated = $request->validate([
            'timezone' => ['required', 'string', 'in:' . implode(',', $validTimezones)],
        ]);

        $user = $request->user();
        $current = $user->getSetting('general', 'timezone');

        if ($current === null) {
            $user->setSetting('general', 'timezone', $validated['timezone']);
        }

        return response()->json([
            'timezone' => $user->getSetting('general', 'timezone') ?? $validated['timezone'],
            'effective_timezone' => $user->getTimezone(),
            'was_set' => $current === null,
        ]);
    }
}
