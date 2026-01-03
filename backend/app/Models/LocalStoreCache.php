<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalStoreCache extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'local_store_caches';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'zip_code',
        'store_name',
        'store_type',
        'address',
        'phone',
        'website',
        'rating',
        'discovered_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'discovered_at' => 'datetime',
        ];
    }

    /**
     * Get the user this cache entry belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by zip code.
     */
    public function scopeForZipCode($query, string $zipCode)
    {
        return $query->where('zip_code', $zipCode);
    }

    /**
     * Scope to filter by store type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('store_type', $type);
    }

    /**
     * Scope to get only fresh cache entries (within last 7 days).
     */
    public function scopeFresh($query, int $days = 7)
    {
        return $query->where('discovered_at', '>=', now()->subDays($days));
    }

    /**
     * Check if this cache entry is stale.
     */
    public function isStale(int $days = 7): bool
    {
        return $this->discovered_at->lt(now()->subDays($days));
    }
}
