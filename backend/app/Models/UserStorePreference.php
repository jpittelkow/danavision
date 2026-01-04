<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserStorePreference Model
 *
 * Represents a user's preference for a specific store.
 * Users can enable/disable stores, set priority, and mark favorites.
 *
 * @property int $id
 * @property int $user_id
 * @property int $store_id
 * @property int $priority
 * @property bool $enabled
 * @property bool $is_favorite
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserStorePreference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'priority',
        'enabled',
        'is_favorite',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'enabled' => 'boolean',
            'is_favorite' => 'boolean',
        ];
    }

    /**
     * Get the user for this preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the store for this preference.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scope to enabled preferences.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to favorite preferences.
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope ordered by priority (highest first).
     */
    public function scopeOrderByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    /**
     * Get enabled stores for a user, ordered by priority.
     *
     * @param int $userId The user ID
     * @param bool $favoritesOnly Only return favorite stores
     * @return \Illuminate\Database\Eloquent\Collection<Store>
     */
    public static function getEnabledStoresForUser(int $userId, bool $favoritesOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::with('store')
            ->where('user_id', $userId)
            ->enabled()
            ->whereHas('store', fn ($q) => $q->active())
            ->orderByPriority();

        if ($favoritesOnly) {
            $query->favorites();
        }

        return $query->get()->pluck('store');
    }

    /**
     * Get all stores for a user (both with preferences and default stores).
     * Returns stores ordered by user priority (if set), then by default priority.
     *
     * @param int $userId The user ID
     * @param bool $includeDisabled Include stores the user has disabled
     * @return \Illuminate\Database\Eloquent\Collection<Store>
     */
    public static function getAllStoresForUser(int $userId, bool $includeDisabled = false): \Illuminate\Database\Eloquent\Collection
    {
        // Get all active stores
        $allStores = Store::active()->get();

        // Get user preferences
        $preferences = self::where('user_id', $userId)
            ->get()
            ->keyBy('store_id');

        // Filter and sort stores
        $stores = $allStores->filter(function ($store) use ($preferences, $includeDisabled) {
            $preference = $preferences->get($store->id);
            
            // If user has disabled this store and we're not including disabled, skip it
            if (!$includeDisabled && $preference && !$preference->enabled) {
                return false;
            }
            
            return true;
        });

        // Sort by effective priority
        return $stores->sortByDesc(function ($store) use ($preferences) {
            $preference = $preferences->get($store->id);
            
            // Favorites get a big boost
            $favoriteBoost = ($preference && $preference->is_favorite) ? 1000 : 0;
            
            // User priority or default priority
            $priority = ($preference && $preference->priority !== 0) 
                ? $preference->priority 
                : $store->default_priority;
            
            return $favoriteBoost + $priority;
        })->values();
    }

    /**
     * Set or update a user's preference for a store.
     *
     * @param int $userId
     * @param int $storeId
     * @param array $data
     * @return self
     */
    public static function setPreference(int $userId, int $storeId, array $data): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'store_id' => $storeId],
            $data
        );
    }

    /**
     * Toggle favorite status for a store.
     *
     * @param int $userId
     * @param int $storeId
     * @return self
     */
    public static function toggleFavorite(int $userId, int $storeId): self
    {
        $preference = self::firstOrNew(
            ['user_id' => $userId, 'store_id' => $storeId],
            ['enabled' => true, 'priority' => 0] // Defaults for new preferences
        );

        $preference->is_favorite = !$preference->is_favorite;
        $preference->save();

        return $preference;
    }

    /**
     * Bulk update priorities for a user's stores (for drag-and-drop reordering).
     *
     * @param int $userId
     * @param array $storeOrder Array of store IDs in priority order (first = highest priority)
     * @return void
     */
    public static function updatePriorities(int $userId, array $storeOrder): void
    {
        $priority = count($storeOrder) * 10; // Start high, decrement

        foreach ($storeOrder as $storeId) {
            self::updateOrCreate(
                ['user_id' => $userId, 'store_id' => $storeId],
                ['priority' => $priority]
            );
            $priority -= 10;
        }
    }
}
