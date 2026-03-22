<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get messages suitable for sending to the LLM (user + assistant only).
     * Tool messages are excluded so they don't consume the context window —
     * they are only relevant within the current turn's tool-use loop.
     */
    public function llmMessages(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $this->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
