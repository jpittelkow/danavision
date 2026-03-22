<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIPrompt extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_prompts';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'prompt_type',
        'prompt_text',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The user who owns this prompt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
