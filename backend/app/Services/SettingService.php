<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    private const CACHE_KEY = 'system_settings:all';

    private const CACHE_TTL = 3600;

    private ?array $memoryCache = null;

    /**
     * Get a single setting with env fallback.
     */
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        $value = $all[$group][$key] ?? null;

        return $value !== null ? $value : $default;
    }

    /**
     * Get all settings for a group (with env fallback).
     */
    public function getGroup(string $group): array
    {
        $all = $this->all();

        return $all[$group] ?? [];
    }

    /**
     * Set a setting (auto-encrypts secrets per schema).
     */
    public function set(string $group, string $key, mixed $value, ?int $userId = null): void
    {
        $schema = config('settings-schema', []);
        $groupSchema = $schema[$group] ?? [];
        $keySchema = $groupSchema[$key] ?? [];
        $shouldEncrypt = !empty($keySchema['encrypted']);
        $isPublic = !empty($keySchema['public']);

        $storedValue = $value;
        if ($shouldEncrypt && $value !== null && $value !== '') {
            $storedValue = encrypt($value);
        }

        SystemSetting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $storedValue,
                'is_encrypted' => $shouldEncrypt,
                'is_public' => $isPublic,
                'updated_by' => $userId,
            ]
        );

        $this->clearCache();
    }

    /**
     * Check if setting exists in database (vs env fallback).
     */
    public function isOverridden(string $group, string $key): bool
    {
        return SystemSetting::where('group', $group)->where('key', $key)->exists();
    }

    /**
     * Reset setting to env default (delete from DB).
     */
    public function reset(string $group, string $key): void
    {
        SystemSetting::where('group', $group)->where('key', $key)->delete();
        $this->clearCache();
    }

    /**
     * Get all settings (for boot-time config injection). Cached.
     */
    public function all(): array
    {
        if ($this->memoryCache !== null) {
            return $this->memoryCache;
        }

        $this->memoryCache = Cache::store('file')->remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => $this->loadAllFromDatabase()
        );

        return $this->memoryCache;
    }

    /**
     * Load all settings from database and merge with env/schema defaults.
     */
    private function loadAllFromDatabase(): array
    {
        $schema = config('settings-schema', []);
        $result = [];

        foreach (array_keys($schema) as $group) {
            $result[$group] = $this->resolveGroup($group, $schema[$group]);
        }

        return $result;
    }

    /**
     * Resolve one group: DB values with env/schema fallback.
     */
    private function resolveGroup(string $group, array $groupSchema): array
    {
        $dbSettings = SystemSetting::where('group', $group)->get()->keyBy('key');
        $resolved = [];

        foreach ($groupSchema as $key => $keySchema) {
            $envKey = $keySchema['env'] ?? null;
            $default = $keySchema['default'] ?? null;

            $record = $dbSettings->get($key);
            if ($record !== null) {
                $resolved[$key] = $record->value;
                continue;
            }

            $envValue = $envKey !== null ? env($envKey) : null;
            $resolved[$key] = $envValue !== null && $envValue !== '' ? $envValue : $default;
        }

        return $resolved;
    }

    /**
     * Warm the settings cache (e.g. after boot).
     */
    public function warmCache(): void
    {
        $this->memoryCache = null;
        Cache::store('file')->forget(self::CACHE_KEY);
        $this->all();
    }

    /**
     * Clear the settings cache.
     */
    public function clearCache(): void
    {
        $this->memoryCache = null;
        Cache::store('file')->forget(self::CACHE_KEY);
    }
}
