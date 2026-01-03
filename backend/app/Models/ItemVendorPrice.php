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
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'list_item_id',
        'vendor',
        'vendor_sku',
        'product_url',
        'current_price',
        'previous_price',
        'lowest_price',
        'highest_price',
        'on_sale',
        'sale_percent_off',
        'in_stock',
        'last_checked_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'lowest_price' => 'decimal:2',
            'highest_price' => 'decimal:2',
            'sale_percent_off' => 'decimal:2',
            'on_sale' => 'boolean',
            'in_stock' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Common vendor names for normalization.
     */
    public const VENDOR_AMAZON = 'Amazon';
    public const VENDOR_WALMART = 'Walmart';
    public const VENDOR_TARGET = 'Target';
    public const VENDOR_BEST_BUY = 'Best Buy';
    public const VENDOR_COSTCO = 'Costco';
    public const VENDOR_EBAY = 'eBay';
    public const VENDOR_NEWEGG = 'Newegg';
    public const VENDOR_HOME_DEPOT = 'Home Depot';
    public const VENDOR_LOWES = "Lowe's";
    public const VENDOR_OTHER = 'Other';

    /**
     * Get the list item this price belongs to.
     */
    public function listItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class);
    }

    /**
     * Update the price for this vendor.
     */
    public function updatePrice(float $newPrice, ?string $url = null, bool $inStock = true): void
    {
        $this->previous_price = $this->current_price;
        $this->current_price = $newPrice;

        if ($url) {
            $this->product_url = $url;
        }

        $this->in_stock = $inStock;
        $this->last_checked_at = now();

        // Update all-time low/high
        if ($this->lowest_price === null || $newPrice < (float) $this->lowest_price) {
            $this->lowest_price = $newPrice;
        }

        if ($this->highest_price === null || $newPrice > (float) $this->highest_price) {
            $this->highest_price = $newPrice;
        }

        // Calculate sale status
        if ($this->highest_price && $newPrice < (float) $this->highest_price) {
            $this->on_sale = true;
            $this->sale_percent_off = (((float) $this->highest_price - $newPrice) / (float) $this->highest_price) * 100;
        } else {
            $this->on_sale = false;
            $this->sale_percent_off = null;
        }

        $this->save();
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
     * Normalize vendor name for consistency.
     */
    public static function normalizeVendor(string $vendor): string
    {
        $vendorLower = strtolower(trim($vendor));

        $mappings = [
            'amazon' => self::VENDOR_AMAZON,
            'amazon.com' => self::VENDOR_AMAZON,
            'walmart' => self::VENDOR_WALMART,
            'walmart.com' => self::VENDOR_WALMART,
            'target' => self::VENDOR_TARGET,
            'target.com' => self::VENDOR_TARGET,
            'best buy' => self::VENDOR_BEST_BUY,
            'bestbuy' => self::VENDOR_BEST_BUY,
            'bestbuy.com' => self::VENDOR_BEST_BUY,
            'costco' => self::VENDOR_COSTCO,
            'costco.com' => self::VENDOR_COSTCO,
            'ebay' => self::VENDOR_EBAY,
            'ebay.com' => self::VENDOR_EBAY,
            'newegg' => self::VENDOR_NEWEGG,
            'newegg.com' => self::VENDOR_NEWEGG,
            'home depot' => self::VENDOR_HOME_DEPOT,
            'homedepot' => self::VENDOR_HOME_DEPOT,
            'homedepot.com' => self::VENDOR_HOME_DEPOT,
            "lowe's" => self::VENDOR_LOWES,
            'lowes' => self::VENDOR_LOWES,
            'lowes.com' => self::VENDOR_LOWES,
        ];

        return $mappings[$vendorLower] ?? ucwords($vendor);
    }
}
