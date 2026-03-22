<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Store extends Model
{
    use HasFactory, Searchable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'parent_store_id',
        'google_place_id',
        'latitude',
        'longitude',
        'address',
        'phone',
        'search_url_template',
        'product_url_pattern',
        'scrape_instructions',
        'is_default',
        'is_local',
        'is_active',
        'auto_configured',
        'local_stock',
        'local_price',
        'logo_url',
        'category',
        'default_priority',
        'last_crawled_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'scrape_instructions' => 'array',
            'is_default' => 'boolean',
            'is_local' => 'boolean',
            'is_active' => 'boolean',
            'auto_configured' => 'boolean',
            'local_stock' => 'boolean',
            'local_price' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'last_crawled_at' => 'datetime',
        ];
    }

    /**
     * The parent store (for subsidiaries).
     */
    public function parentStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'parent_store_id');
    }

    /**
     * Subsidiary stores of this store.
     */
    public function subsidiaries(): HasMany
    {
        return $this->hasMany(Store::class, 'parent_store_id');
    }

    /**
     * User preferences for this store.
     */
    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserStorePreference::class);
    }

    /**
     * Get the indexable data array for the model (Scout/Meilisearch).
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'category' => $this->category,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'stores';
    }

    /**
     * Build a search URL for the given query and optional zip code.
     */
    public function buildSearchUrl(string $query, ?string $zip = null): ?string
    {
        if (! $this->search_url_template) {
            return null;
        }

        $url = str_replace('{query}', urlencode($query), $this->search_url_template);

        if ($zip) {
            $url = str_replace('{zip}', urlencode($zip), $url);
        }

        return $url;
    }
}
