<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Store Model
 *
 * Represents a retail store in the Store Registry system.
 * Each store has URL templates for efficient price discovery
 * without using the expensive Firecrawl Agent API.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $domain
 * @property string|null $search_url_template
 * @property string|null $product_url_pattern
 * @property array|null $scrape_instructions
 * @property bool $is_default
 * @property bool $is_local
 * @property bool $is_active
 * @property string|null $logo_url
 * @property string|null $category
 * @property int $default_priority
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Store extends Model
{
    use HasFactory;

    /**
     * Store categories for organization.
     */
    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_ELECTRONICS = 'electronics';
    public const CATEGORY_GROCERY = 'grocery';
    public const CATEGORY_HOME = 'home';
    public const CATEGORY_CLOTHING = 'clothing';
    public const CATEGORY_PHARMACY = 'pharmacy';
    public const CATEGORY_WAREHOUSE = 'warehouse';
    public const CATEGORY_SPECIALTY = 'specialty';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'search_url_template',
        'product_url_pattern',
        'scrape_instructions',
        'is_default',
        'is_local',
        'is_active',
        'logo_url',
        'category',
        'default_priority',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scrape_instructions' => 'array',
            'is_default' => 'boolean',
            'is_local' => 'boolean',
            'is_active' => 'boolean',
            'default_priority' => 'integer',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Store $store) {
            if (empty($store->slug)) {
                $store->slug = Str::slug($store->name);
            }
        });
    }

    /**
     * Get users who have preferences for this store.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_store_preferences')
            ->withPivot(['priority', 'enabled', 'is_favorite'])
            ->withTimestamps();
    }

    /**
     * Get user preferences for this store.
     */
    public function userPreferences(): HasMany
    {
        return $this->hasMany(UserStorePreference::class);
    }

    /**
     * Scope to only active stores.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only default (pre-populated) stores.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to only local stores.
     */
    public function scopeLocal($query)
    {
        return $query->where('is_local', true);
    }

    /**
     * Scope to stores by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Generate a search URL for the given product query.
     *
     * @param string $query The product search query
     * @return string|null The generated search URL or null if no template
     */
    public function generateSearchUrl(string $query): ?string
    {
        if (empty($this->search_url_template)) {
            return null;
        }

        // URL encode the query
        $encodedQuery = urlencode($query);

        // Replace placeholders in the template
        return str_replace(
            ['{query}', '{QUERY}'],
            [$encodedQuery, $encodedQuery],
            $this->search_url_template
        );
    }

    /**
     * Check if a URL matches this store's domain.
     *
     * @param string $url The URL to check
     * @return bool
     */
    public function matchesUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        // Remove www. prefix for comparison
        $host = preg_replace('/^www\./', '', strtolower($host));
        $domain = preg_replace('/^www\./', '', strtolower($this->domain));

        return str_contains($host, $domain);
    }

    /**
     * Check if a URL is a product page for this store.
     *
     * @param string $url The URL to check
     * @return bool
     */
    public function isProductPage(string $url): bool
    {
        if (!$this->matchesUrl($url)) {
            return false;
        }

        if (empty($this->product_url_pattern)) {
            // If no pattern defined, assume any URL on the domain could be a product
            return true;
        }

        return (bool) preg_match($this->product_url_pattern, $url);
    }

    /**
     * Find a store by domain.
     *
     * @param string $domain The domain to search for
     * @return Store|null
     */
    public static function findByDomain(string $domain): ?self
    {
        // Remove www. prefix
        $domain = preg_replace('/^www\./', '', strtolower($domain));

        return self::where('domain', 'like', "%{$domain}%")
            ->orWhere('domain', 'like', "%www.{$domain}%")
            ->first();
    }

    /**
     * Find a store by URL.
     *
     * @param string $url The URL to search for
     * @return Store|null
     */
    public static function findByUrl(string $url): ?self
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        return self::findByDomain($host);
    }

    /**
     * Get the effective priority for a user.
     * Returns user's custom priority if set, otherwise the default priority.
     *
     * @param int $userId The user ID
     * @return int
     */
    public function getEffectivePriority(int $userId): int
    {
        $preference = $this->userPreferences()
            ->where('user_id', $userId)
            ->first();

        if ($preference && $preference->priority !== 0) {
            return $preference->priority;
        }

        return $this->default_priority;
    }

    /**
     * Check if this store is enabled for a user.
     *
     * @param int $userId The user ID
     * @return bool
     */
    public function isEnabledForUser(int $userId): bool
    {
        $preference = $this->userPreferences()
            ->where('user_id', $userId)
            ->first();

        // If user has a preference, use it; otherwise store is enabled by default
        return $preference ? $preference->enabled : true;
    }
}
