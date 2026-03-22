<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'list_item_id',
        'price',
        'retailer',
        'url',
        'image_url',
        'upc',
        'in_stock',
        'source',
        'captured_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'in_stock' => 'boolean',
            'price' => 'decimal:2',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * The list item this price history entry belongs to.
     */
    public function listItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class);
    }
}
