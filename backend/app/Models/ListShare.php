<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ListShare extends Model
{
    use HasFactory;

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['status'];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'shopping_list_id',
        'user_id',
        'shared_by_user_id',
        'permission',
        'message',
        'accepted_at',
        'declined_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The shopping list being shared.
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * The user this list is shared with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who shared this list.
     */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    /**
     * Check if this share is pending (not accepted or declined).
     */
    public function isPending(): bool
    {
        return is_null($this->accepted_at) && is_null($this->declined_at);
    }

    /**
     * Check if this share has been accepted.
     */
    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    /**
     * Check if this share has been declined.
     */
    public function isDeclined(): bool
    {
        return ! is_null($this->declined_at);
    }

    /**
     * Check if this share has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the computed status for the share.
     *
     * The frontend expects a `status` field, but the backend uses timestamp
     * columns (accepted_at, declined_at, expires_at) to track state.
     */
    public function getStatusAttribute(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }
        if ($this->isDeclined()) {
            return 'declined';
        }
        if ($this->isAccepted()) {
            return 'accepted';
        }
        return 'pending';
    }

    /**
     * Check if this share has at least the given permission level.
     */
    public function hasPermission(string $level): bool
    {
        $levels = ['view' => 1, 'edit' => 2, 'admin' => 3];

        $currentLevel = $levels[$this->permission] ?? 0;
        $requiredLevel = $levels[$level] ?? 0;

        return $currentLevel >= $requiredLevel;
    }
}
