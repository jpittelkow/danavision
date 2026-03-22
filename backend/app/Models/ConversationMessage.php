<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'role',
        'content',
        'tool_name',
        'tool_input',
        'tool_output',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tool_input' => 'array',
            'tool_output' => 'array',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isToolCall(): bool
    {
        return $this->role === 'tool_call';
    }

    public function isToolResult(): bool
    {
        return $this->role === 'tool_result';
    }
}
