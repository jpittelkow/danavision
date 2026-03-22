<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartAddQueueItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'ai_job_id',
        'status',
        'product_data',
        'source',
        'source_query',
        'source_image_path',
        'shopping_list_id',
        'added_item_id',
        'selected_index',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'product_data' => 'array',
        ];
    }

    /**
     * The user who owns this queue item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The AI job associated with this queue item.
     */
    public function aiJob(): BelongsTo
    {
        return $this->belongsTo(AIJob::class, 'ai_job_id');
    }

    /**
     * The shopping list this item is destined for.
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * The list item that was added from this queue item.
     */
    public function addedItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class, 'added_item_id');
    }
}
