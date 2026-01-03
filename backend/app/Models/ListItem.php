<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * Valid units of measure for generic items.
     */
    public const UNITS_OF_MEASURE = [
        // Weight
        'lb' => 'pound',
        'oz' => 'ounce',
        'kg' => 'kilogram',
        'g' => 'gram',
        // Volume
        'gallon' => 'gallon',
        'liter' => 'liter',
        'quart' => 'quart',
        'pint' => 'pint',
        'fl_oz' => 'fluid ounce',
        // Count
        'each' => 'each',
        'dozen' => 'dozen',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_price' => 'decimal:2',
            'current_price' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'lowest_price' => 'decimal:2',
            'highest_price' => 'decimal:2',
            'purchased_price' => 'decimal:2',
            'in_stock' => 'boolean',
            'is_purchased' => 'boolean',
            'shop_local' => 'boolean',
            'is_generic' => 'boolean',
            'purchased_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Check if this is a generic item (sold by weight/volume).
     */
    public function isGeneric(): bool
    {
        return $this->is_generic ?? false;
    }

    /**
     * Get the formatted price with unit of measure for generic items.
     */
    public function getFormattedPrice(?float $price = null): string
    {
        $price = $price ?? $this->current_price;
        
        if ($price === null) {
            return '—';
        }

        $formatted = '$' . number_format((float) $price, 2);

        if ($this->isGeneric() && $this->unit_of_measure) {
            return $formatted . '/' . $this->unit_of_measure;
        }

        return $formatted;
    }

    /**
     * Get the shopping list this item belongs to.
     */
    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    /**
     * Get the user who added this item.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * Get the price history for this item.
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    /**
     * Get the vendor prices for this item.
     */
    public function vendorPrices(): HasMany
    {
        return $this->hasMany(ItemVendorPrice::class);
    }

    /**
     * Get the best (lowest) current price across all vendors.
     */
    public function getBestPrice(): ?ItemVendorPrice
    {
        return $this->vendorPrices()
            ->whereNotNull('current_price')
            ->where('in_stock', true)
            ->orderBy('current_price', 'asc')
            ->first();
    }

    /**
     * Get the best price value.
     */
    public function getBestPriceValue(): ?float
    {
        return $this->getBestPrice()?->current_price;
    }

    /**
     * Get vendor prices sorted by price (lowest first).
     */
    public function getVendorPricesSorted(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->vendorPrices()
            ->whereNotNull('current_price')
            ->orderBy('current_price', 'asc')
            ->get();
    }

    /**
     * Check if any vendor has a sale.
     */
    public function hasAnySale(): bool
    {
        return $this->vendorPrices()->where('on_sale', true)->exists();
    }

    /**
     * Get the display image URL (prefer uploaded, fallback to product image URL).
     */
    public function getDisplayImageUrl(): ?string
    {
        if ($this->uploaded_image_path) {
            return asset('storage/' . $this->uploaded_image_path);
        }

        return $this->product_image_url;
    }

    /**
     * Get the price change from previous price.
     */
    public function priceChange(): ?float
    {
        if ($this->current_price === null || $this->previous_price === null) {
            return null;
        }

        return (float) $this->current_price - (float) $this->previous_price;
    }

    /**
     * Get the percentage price change.
     */
    public function priceChangePercent(): ?float
    {
        if ($this->previous_price === null || $this->previous_price == 0) {
            return null;
        }

        $change = $this->priceChange();
        if ($change === null) {
            return null;
        }

        return ($change / (float) $this->previous_price) * 100;
    }

    /**
     * Check if current price is at all-time low.
     */
    public function isAtAllTimeLow(): bool
    {
        if ($this->current_price === null || $this->lowest_price === null) {
            return false;
        }

        return (float) $this->current_price === (float) $this->lowest_price;
    }

    /**
     * Check if price is below target.
     */
    public function isBelowTarget(): bool
    {
        if ($this->current_price === null || $this->target_price === null) {
            return false;
        }

        return (float) $this->current_price <= (float) $this->target_price;
    }

    /**
     * Check if shop local is enabled for this item.
     * Item-level setting takes precedence, otherwise inherits from list.
     */
    public function shouldShopLocal(): bool
    {
        // If item has explicit setting, use it
        if ($this->shop_local !== null) {
            return $this->shop_local;
        }

        // Otherwise, inherit from list
        return $this->shoppingList?->shop_local ?? false;
    }

    /**
     * Update price tracking fields with a new price.
     */
    public function updatePrice(float $newPrice, string $retailer, ?string $url = null): void
    {
        $this->previous_price = $this->current_price;
        $this->current_price = $newPrice;
        $this->current_retailer = $retailer;
        
        if ($url) {
            $this->product_url = $url;
        }

        // Update all-time high/low
        if ($this->lowest_price === null || $newPrice < (float) $this->lowest_price) {
            $this->lowest_price = $newPrice;
        }

        if ($this->highest_price === null || $newPrice > (float) $this->highest_price) {
            $this->highest_price = $newPrice;
        }

        $this->last_checked_at = now();
        $this->save();
    }
}
