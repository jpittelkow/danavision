<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIPrompt extends Model
{
    use HasFactory;

    protected $table = 'ai_prompts';

    protected $fillable = [
        'user_id',
        'prompt_type',
        'prompt_text',
    ];

    // Prompt types
    public const TYPE_PRODUCT_IDENTIFICATION = 'product_identification';
    public const TYPE_PRICE_RECOMMENDATION = 'price_recommendation';
    public const TYPE_AGGREGATION = 'aggregation';

    /**
     * Get all prompt types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_PRODUCT_IDENTIFICATION,
            self::TYPE_PRICE_RECOMMENDATION,
            self::TYPE_AGGREGATION,
        ];
    }

    /**
     * Get the user this prompt belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a prompt for a user, falling back to default if not customized.
     */
    public static function getPrompt(string $type, ?int $userId = null): string
    {
        if ($userId) {
            $customPrompt = static::where('user_id', $userId)
                ->where('prompt_type', $type)
                ->first();

            if ($customPrompt) {
                return $customPrompt->prompt_text;
            }
        }

        return DefaultPrompts::get($type);
    }

    /**
     * Set a custom prompt for a user.
     */
    public static function setPrompt(string $type, string $text, int $userId): static
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'prompt_type' => $type],
            ['prompt_text' => $text]
        );
    }

    /**
     * Reset a prompt to default for a user.
     */
    public static function resetPrompt(string $type, int $userId): bool
    {
        return static::where('user_id', $userId)
            ->where('prompt_type', $type)
            ->delete() > 0;
    }

    /**
     * Get all prompts for a user (including defaults).
     */
    public static function getAllForUser(?int $userId = null): array
    {
        $prompts = [];
        $customPrompts = $userId
            ? static::where('user_id', $userId)->get()->keyBy('prompt_type')
            : collect();

        foreach (self::getTypes() as $type) {
            $custom = $customPrompts->get($type);
            $prompts[$type] = [
                'type' => $type,
                'name' => DefaultPrompts::getName($type),
                'description' => DefaultPrompts::getDescription($type),
                'prompt_text' => $custom?->prompt_text ?? DefaultPrompts::get($type),
                'is_customized' => $custom !== null,
                'default_text' => DefaultPrompts::get($type),
            ];
        }

        return $prompts;
    }
}
