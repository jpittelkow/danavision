<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'is_encrypted',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Setting keys.
     */
    // AI Settings
    public const AI_PROVIDER = 'ai_provider';
    public const AI_MODEL = 'ai_model';
    public const ANTHROPIC_API_KEY = 'anthropic_api_key';
    public const OPENAI_API_KEY = 'openai_api_key';
    public const GEMINI_API_KEY = 'gemini_api_key';

    // Price API Settings
    public const PRICE_API_PROVIDER = 'price_api_provider';
    public const SERPAPI_KEY = 'serpapi_key';
    public const RAINFOREST_KEY = 'rainforest_key';

    // Firecrawl Web Crawler Settings
    public const FIRECRAWL_API_KEY = 'firecrawl_api_key';

    // Google Places API Settings (for nearby store discovery)
    public const GOOGLE_PLACES_API_KEY = 'google_places_api_key';

    // Email Settings
    public const MAIL_DRIVER = 'mail_driver';
    public const MAIL_HOST = 'mail_host';
    public const MAIL_PORT = 'mail_port';
    public const MAIL_USERNAME = 'mail_username';
    public const MAIL_PASSWORD = 'mail_password';
    public const MAIL_FROM_ADDRESS = 'mail_from_address';
    public const MAIL_FROM_NAME = 'mail_from_name';
    public const MAIL_ENCRYPTION = 'mail_encryption';

    // Location Settings
    public const HOME_ZIP_CODE = 'home_zip_code';
    public const HOME_ADDRESS = 'home_address';
    public const HOME_LATITUDE = 'home_latitude';
    public const HOME_LONGITUDE = 'home_longitude';

    // Vendor Settings
    public const SUPPRESSED_VENDORS = 'suppressed_vendors';

    // Notification Preferences
    public const NOTIFY_PRICE_DROPS = 'notify_price_drops';
    public const NOTIFY_DAILY_SUMMARY = 'notify_daily_summary';
    public const DAILY_SUMMARY_TIME = 'daily_summary_time';

    // Price Check Schedule
    public const PRICE_CHECK_TIME = 'price_check_time'; // e.g., "03:00"

    /**
     * Keys that should be encrypted.
     */
    protected static array $encryptedKeys = [
        self::ANTHROPIC_API_KEY,
        self::OPENAI_API_KEY,
        self::GEMINI_API_KEY,
        self::SERPAPI_KEY,
        self::RAINFOREST_KEY,
        self::FIRECRAWL_API_KEY,
        self::GOOGLE_PLACES_API_KEY,
        self::MAIL_PASSWORD,
    ];

    /**
     * Get the user this setting belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the decrypted value.
     */
    public function getDecryptedValue(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if ($this->is_encrypted) {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $this->value;
    }

    /**
     * Get a setting value for a user.
     */
    public static function get(string $key, ?int $userId = null, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)
            ->where('user_id', $userId)
            ->first();

        if (!$setting) {
            return $default;
        }

        return $setting->getDecryptedValue() ?? $default;
    }

    /**
     * Set a setting value for a user.
     */
    public static function set(string $key, mixed $value, ?int $userId = null, ?bool $encrypted = null): static
    {
        $shouldEncrypt = $encrypted ?? in_array($key, static::$encryptedKeys);

        $storedValue = $value;
        if ($shouldEncrypt && $value !== null) {
            $storedValue = Crypt::encryptString($value);
        }

        return static::updateOrCreate(
            ['key' => $key, 'user_id' => $userId],
            ['value' => $storedValue, 'is_encrypted' => $shouldEncrypt]
        );
    }

    /**
     * Get multiple settings for a user.
     */
    public static function getMany(array $keys, ?int $userId = null): array
    {
        $settings = static::whereIn('key', $keys)
            ->where('user_id', $userId)
            ->get();

        $result = [];
        foreach ($keys as $key) {
            $setting = $settings->firstWhere('key', $key);
            $result[$key] = $setting?->getDecryptedValue();
        }

        return $result;
    }

    /**
     * Delete a setting.
     */
    public static function remove(string $key, ?int $userId = null): bool
    {
        return static::where('key', $key)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    /**
     * Check if a key should be encrypted.
     */
    public static function shouldBeEncrypted(string $key): bool
    {
        return in_array($key, static::$encryptedKeys);
    }
}
