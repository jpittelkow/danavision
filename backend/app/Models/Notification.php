<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'data',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Notification types.
     */
    public const TYPE_PRICE_DROP = 'price_drop';
    public const TYPE_LIST_SHARED = 'list_shared';
    public const TYPE_DAILY_SUMMARY = 'daily_summary';
    public const TYPE_ALL_TIME_LOW = 'all_time_low';

    /**
     * Get the user this notification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->read_at = now();
        $this->save();
    }

    /**
     * Check if notification is unread.
     */
    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * Create a price drop notification.
     */
    public static function priceDropAlert(User $user, ListItem $item, float $previousPrice, float $newPrice): static
    {
        return static::create([
            'user_id' => $user->id,
            'type' => self::TYPE_PRICE_DROP,
            'data' => [
                'list_item_id' => $item->id,
                'product_name' => $item->product_name,
                'previous_price' => $previousPrice,
                'new_price' => $newPrice,
                'change_percent' => $previousPrice > 0 
                    ? round((($previousPrice - $newPrice) / $previousPrice) * 100, 2) 
                    : 0,
            ],
        ]);
    }

    /**
     * Create a list shared notification.
     */
    public static function listSharedAlert(User $user, ShoppingList $list, User $sharedBy): static
    {
        return static::create([
            'user_id' => $user->id,
            'type' => self::TYPE_LIST_SHARED,
            'data' => [
                'shopping_list_id' => $list->id,
                'list_name' => $list->name,
                'shared_by_name' => $sharedBy->name,
                'shared_by_email' => $sharedBy->email,
            ],
        ]);
    }

    /**
     * Create an all-time low notification.
     */
    public static function allTimeLowAlert(User $user, ListItem $item): static
    {
        return static::create([
            'user_id' => $user->id,
            'type' => self::TYPE_ALL_TIME_LOW,
            'data' => [
                'list_item_id' => $item->id,
                'product_name' => $item->product_name,
                'current_price' => $item->current_price,
            ],
        ]);
    }
}
