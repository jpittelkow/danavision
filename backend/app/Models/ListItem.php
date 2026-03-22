<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class ListItem extends Model
{
    use HasFactory, Searchable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'shopping_list_id',
        'added_by_user_id',
        'product_name',
        'product_query',
        'product_image_url',
        'product_url',
        'sku',
        'upc',
        'uploaded_image_path',
        'notes',
        'target_price',
        'current_price',
        'previous_price',
        'lowest_price',
        'highest_price',
        'current_retailer',
        'in_stock',
        'priority',
        'is_purchased',
        'shop_local',
        'is_generic',
        'unit_of_measure',
        'purchased_at',
        'purchased_price',
        'last_checked_at',
    ];

    /**
     * Appended attributes for frontend compatibility.
     */
    protected $appends = ['retailer', 'image_url', 'url'];

    public function getRetailerAttribute(): ?string
    {
        return $this->current_retailer;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->product_image_url;
    }

    public function getUrlAttribute(): ?string
    {
        return $this->product_url;
    }

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'in_stock' => 'boolean',
            'is_purchased' => 'boolean',
            'is_generic' => 'boolean',
            'shop_local' => 'boolean',
            'target_price' => 'decimal:2',
            'current_price' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'lowest_price' => 'decimal:2',
            'highest_price' => 'decimal:2',
            'purchased_price' => 'decimal:2',
            'purchased_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * The shopping list this item belongs to.
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * The user who added this item.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * Vendor prices for this item.
     */
    public function vendorPrices(): HasMany
    {
        return $this->hasMany(ItemVendorPrice::class);
    }

    /**
     * Price history entries for this item.
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    /**
     * Get the indexable data array for the model (Scout/Meilisearch).
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'upc' => $this->upc,
            'shopping_list_id' => $this->shopping_list_id,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'list_items';
    }
}
