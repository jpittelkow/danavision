<?php

namespace App\Models\Traits;

use App\Models\Setting;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasSettings
{
    /**
     * User settings
     */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    /**
     * Get a specific setting value.
     *
     * Supports two signatures:
     * - getSetting('group', 'key', 'default') - group-based (new)
     * - getSetting('key', 'default') - legacy (backward compatible)
     *
     * @param string $groupOrKey The setting group (new) or key (legacy)
     * @param mixed $keyOrDefault The setting key (new) or default value (legacy)
     * @param mixed $default Default value if setting doesn't exist (new signature only)
     * @return mixed
     */
    public function getSetting(string $groupOrKey, mixed $keyOrDefault = null, mixed $default = null): mixed
    {
        // New signature: getSetting('group', 'key', 'default')
        // Check if we have 3 args and second is a string (indicating it's a key, not a default)
        if (func_num_args() >= 2 && is_string($keyOrDefault)) {
            $group = $groupOrKey;
            $key = $keyOrDefault;
            $setting = $this->settings()
                ->where('group', $group)
                ->where('key', $key)
                ->first();
            return $setting ? $setting->value : ($default ?? null);
        }

        // Legacy signature: getSetting('key', 'default')
        $key = $groupOrKey;
        $defaultValue = $keyOrDefault;
        $setting = $this->settings()->where('key', $key)->first();
        return $setting ? $setting->value : $defaultValue;
    }

    /**
     * Set a setting value.
     *
     * @param string $group The setting group (e.g. 'general', 'notifications')
     * @param string $key The setting key
     * @param mixed $value The value to set
     * @return Setting
     */
    public function setSetting(string $group, string $key, mixed $value): Setting
    {
        return $this->settings()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get the user's effective timezone.
     *
     * Fallback chain: user setting -> admin system default -> APP_TIMEZONE -> UTC
     */
    public function getTimezone(): string
    {
        return $this->getSetting('general', 'timezone')
            ?? SystemSetting::get('default_timezone', null, 'general')
            ?? config('app.timezone', 'UTC');
    }
}
