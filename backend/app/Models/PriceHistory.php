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
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'list_item_id',
        'price',
        'retailer',
        'url',
        'in_stock',
        'captured_at',
        'source',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'in_stock' => 'boolean',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * Get the list item this history belongs to.
     */
    public function listItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class);
    }

    /**
     * Create a history entry from a list item.
     */
    public static function captureFromItem(ListItem $item, string $source = 'manual'): static
    {
        return static::create([
            'list_item_id' => $item->id,
            'price' => $item->current_price,
            'retailer' => $item->current_retailer,
            'url' => $item->product_url,
            'in_stock' => $item->in_stock,
            'captured_at' => now(),
            'source' => $source,
        ]);
    }
}
