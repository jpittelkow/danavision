<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannedDeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ai_job_id',
        'source_scan_id',
        'store_id',
        'store_name_raw',
        'product_name',
        'product_description',
        'deal_type',
        'discount_type',
        'discount_value',
        'sale_price',
        'original_price',
        'conditions',
        'valid_from',
        'valid_to',
        'status',
        'matched_list_item_id',
        'content_hash',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'discount_value' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'confidence' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiJob(): BelongsTo
    {
        return $this->belongsTo(AIJob::class, 'ai_job_id');
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DealScan::class, 'source_scan_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function matchedItem(): BelongsTo
    {
        return $this->belongsTo(ListItem::class, 'matched_list_item_id');
    }

    /**
     * Scope to active, non-expired deals.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now()->toDateString());
            });
    }

    /**
     * Scope to upcoming deals (active status but valid_from is in the future).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('valid_from')
            ->where('valid_from', '>', now()->toDateString());
    }

    /**
     * Scope to expired deals.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope to deals for a specific store.
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Check if this deal is currently in its valid date window.
     */
    public function isCurrentlyValid(): bool
    {
        $now = now()->toDateString();

        if ($this->valid_from && $this->valid_from->toDateString() > $now) {
            return false;
        }

        if ($this->valid_to && $this->valid_to->toDateString() < $now) {
            return false;
        }

        return true;
    }

    /**
     * Get a human-readable discount description.
     */
    public function getDiscountDescription(): string
    {
        return match ($this->discount_type) {
            'amount_off' => '$' . number_format((float) $this->discount_value, 2) . ' off',
            'percent_off' => number_format((float) $this->discount_value, 0) . '% off',
            'fixed_price' => '$' . number_format((float) $this->sale_price, 2),
            'bogo' => 'Buy One Get One',
            'buy_x_get_y' => 'Buy ' . ((int) ($this->conditions['buy_quantity'] ?? 2)) . ' Get ' . ((int) ($this->conditions['get_quantity'] ?? 1)) . ' Free',
            default => 'Deal',
        };
    }
}
