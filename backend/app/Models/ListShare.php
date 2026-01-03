<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ListShare extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'shopping_list_id',
        'user_id',
        'shared_by_user_id',
        'permission',
        'accepted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Get the shopping list.
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * Get the user this list is shared with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who shared the list.
     */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    /**
     * Scope for pending shares.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }

    /**
     * Scope for accepted shares.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Accept the share invitation.
     */
    public function accept(): void
    {
        $this->accepted_at = now();
        $this->save();
    }

    /**
     * Check if the share is pending.
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }

    /**
     * Check if the share is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
