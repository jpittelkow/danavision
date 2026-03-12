<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    use ApiResponseTrait;
    /**
     * Get all user settings.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = $request->user()
            ->settings()
            ->get()
            ->groupBy('group')
            ->map(fn ($group) => $group->pluck('value', 'key'));

        return $this->dataResponse([
            'settings' => $settings,
        ]);
    }

    /**
     * Get settings for a specific group.
     */
    public function show(Request $request, string $group): JsonResponse
    {
        $allowedGroups = config('user-settings-schema');
        if (!in_array($group, $allowedGroups, true)) {
            return $this->errorResponse("Unknown settings group: {$group}", 422);
        }

        $settings = $request->user()
            ->settings()
            ->where('group', $group)
            ->get()
            ->pluck('value', 'key');

        return $this->dataResponse([
            'group' => $group,
            'settings' => $settings,
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['required'],
            'settings.*.group' => ['sometimes', 'string'],
        ]);

        $allowedGroups = config('user-settings-schema');
        $user = $request->user();

        // Validate all groups upfront before writing to prevent partial updates
        foreach ($validated['settings'] as $setting) {
            $group = $setting['group'] ?? 'general';
            if (!in_array($group, $allowedGroups, true)) {
                return $this->errorResponse("Unknown settings group: {$group}", 422);
            }
        }

        DB::transaction(function () use ($validated, $user) {
            foreach ($validated['settings'] as $setting) {
                $group = $setting['group'] ?? 'general';
                $user->setSetting(
                    $group,
                    $setting['key'],
                    $setting['value']
                );
            }
        });

        return $this->successResponse('Settings updated successfully');
    }

    /**
     * Update settings for a specific group.
     */
    public function updateGroup(Request $request, string $group): JsonResponse
    {
        $allowedGroups = config('user-settings-schema');
        if (!in_array($group, $allowedGroups, true)) {
            return $this->errorResponse("Unknown settings group: {$group}", 422);
        }

        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $user = $request->user();

        foreach ($validated['settings'] as $key => $value) {
            $user->setSetting($group, $key, $value);
        }

        return $this->successResponse('Settings updated successfully', [
            'group' => $group,
        ]);
    }
}
