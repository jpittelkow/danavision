<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class AIProvider extends Model
{
    use HasFactory;

    protected $table = 'ai_providers';

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'model',
        'base_url',
        'is_active',
        'is_primary',
        'last_tested_at',
        'test_status',
        'test_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * Provider types.
     */
    public const PROVIDER_CLAUDE = 'claude';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_GEMINI = 'gemini';
    public const PROVIDER_LOCAL = 'local';

    /**
     * Test statuses.
     */
    public const STATUS_UNTESTED = 'untested';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    /**
     * Available providers with their display info.
     */
    public static array $providers = [
        self::PROVIDER_CLAUDE => [
            'name' => 'Claude',
            'company' => 'Anthropic',
            'icon' => 'anthropic',
            'models' => [
                'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                'claude-3-opus-20240229' => 'Claude 3 Opus',
                'claude-3-haiku-20240307' => 'Claude 3 Haiku',
            ],
            'default_model' => 'claude-sonnet-4-20250514',
            'requires_api_key' => true,
        ],
        self::PROVIDER_OPENAI => [
            'name' => 'OpenAI',
            'company' => 'OpenAI',
            'icon' => 'openai',
            'models' => [
                'gpt-4o' => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o Mini',
                'gpt-4-turbo' => 'GPT-4 Turbo',
                'gpt-4' => 'GPT-4',
            ],
            'default_model' => 'gpt-4o',
            'requires_api_key' => true,
        ],
        self::PROVIDER_GEMINI => [
            'name' => 'Gemini',
            'company' => 'Google',
            'icon' => 'google',
            'models' => [
                'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                'gemini-1.5-flash' => 'Gemini 1.5 Flash',
                'gemini-pro' => 'Gemini Pro',
            ],
            'default_model' => 'gemini-1.5-pro',
            'requires_api_key' => true,
        ],
        self::PROVIDER_LOCAL => [
            'name' => 'Local (Ollama)',
            'company' => 'Self-hosted',
            'icon' => 'server',
            'models' => [], // Dynamically fetched from Ollama
            'default_model' => 'llama3.2',
            'default_base_url' => 'http://localhost:11434',
            'requires_api_key' => false,
        ],
    ];

    /**
     * Get the user that owns this provider.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Set the API key (automatically encrypt).
     */
    public function setApiKeyAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['api_key'] = null;
        } else {
            $this->attributes['api_key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Get the decrypted API key.
     */
    public function getDecryptedApiKey(): ?string
    {
        if ($this->api_key === null) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if this provider has a valid API key.
     */
    public function hasApiKey(): bool
    {
        return $this->getDecryptedApiKey() !== null;
    }

    /**
     * Get provider info.
     */
    public function getProviderInfo(): array
    {
        return self::$providers[$this->provider] ?? [];
    }

    /**
     * Get available models for this provider.
     */
    public function getAvailableModels(): array
    {
        $info = $this->getProviderInfo();
        return $info['models'] ?? [];
    }

    /**
     * Get the default model for this provider.
     */
    public function getDefaultModel(): string
    {
        $info = $this->getProviderInfo();
        return $info['default_model'] ?? '';
    }

    /**
     * Get display name for the provider.
     */
    public function getDisplayName(): string
    {
        $info = $this->getProviderInfo();
        return $info['name'] ?? ucfirst($this->provider);
    }

    /**
     * Mark this provider as tested.
     */
    public function markAsTested(bool $success, ?string $error = null): void
    {
        $this->update([
            'last_tested_at' => now(),
            'test_status' => $success ? self::STATUS_SUCCESS : self::STATUS_FAILED,
            'test_error' => $success ? null : $error,
        ]);
    }

    /**
     * Set this provider as the primary for the user.
     */
    public function setAsPrimary(): void
    {
        // Remove primary from other providers
        self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        // Set this as primary
        $this->update(['is_primary' => true]);
    }

    /**
     * Get all active providers for a user.
     */
    public static function getActiveForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get the primary provider for a user.
     */
    public static function getPrimaryForUser(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('is_primary', true)
            ->first();
    }

    /**
     * Scope to active providers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to providers with successful tests.
     */
    public function scopeTested($query)
    {
        return $query->where('test_status', self::STATUS_SUCCESS);
    }

    /**
     * Get masked API key for display (shows last 4 chars).
     */
    public function getMaskedApiKey(): ?string
    {
        $key = $this->getDecryptedApiKey();
        if ($key === null) {
            return null;
        }

        $length = strlen($key);
        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', $length - 4) . substr($key, -4);
    }
}
