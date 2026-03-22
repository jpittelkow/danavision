<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemVendorPrice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'list_item_id',
        'store_id',
        'vendor',
        'vendor_sku',
        'product_url',
        'current_price',
        'unit_price',
        'unit_quantity',
        'unit_type',
        'package_size',
        'previous_price',
        'lowest_price',
        'highest_price',
        'on_sale',
        'sale_percent_off',
        'in_stock',
        'last_checked_at',
        'last_firecrawl_at',
        'firecrawl_source',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'on_sale' => 'boolean',
            'in_stock' => 'boolean',
            'current_price' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'unit_quantity' => 'decimal:4',
            'previous_price' => 'decimal:2',
            'lowest_price' => 'decimal:2',
            'highest_price' => 'decimal:2',
            'sale_percent_off' => 'decimal:2',
            'last_checked_at' => 'datetime',
            'last_firecrawl_at' => 'datetime',
        ];
    }

    /**
     * The list item this vendor price belongs to.
     */
    public function listItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class);
    }

    /**
     * The store this vendor price is associated with.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
