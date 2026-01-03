<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShoppingList extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_active',
        'notify_on_any_drop',
        'notify_on_threshold',
        'threshold_percent',
        'shop_local',
        'last_refreshed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notify_on_any_drop' => 'boolean',
            'notify_on_threshold' => 'boolean',
            'threshold_percent' => 'integer',
            'shop_local' => 'boolean',
            'last_refreshed_at' => 'datetime',
        ];
    }

    /**
     * Get the owner of the list.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items in the list.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class);
    }

    /**
     * Get the shares for this list.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ListShare::class);
    }

    /**
     * Get the users this list is shared with.
     */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'list_shares')
            ->withPivot(['permission', 'accepted_at', 'shared_by_user_id'])
            ->withTimestamps();
    }

    /**
     * Check if a user can view this list.
     */
    public function isViewableBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->shares()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Check if a user can edit this list.
     */
    public function isEditableBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->shares()
            ->where('user_id', $user->id)
            ->whereIn('permission', ['edit', 'admin'])
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Get the permission level for a user.
     */
    public function getPermissionFor(User $user): ?string
    {
        if ($this->user_id === $user->id) {
            return 'owner';
        }

        $share = $this->shares()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->first();

        return $share?->permission;
    }

    /**
     * Get items with price drops.
     */
    public function itemsWithDrops(): HasMany
    {
        return $this->items()
            ->whereColumn('current_price', '<', 'previous_price');
    }

    /**
     * Get items at all-time low.
     */
    public function itemsAtAllTimeLow(): HasMany
    {
        return $this->items()
            ->whereColumn('current_price', '=', 'lowest_price')
            ->whereNotNull('lowest_price');
    }
}
