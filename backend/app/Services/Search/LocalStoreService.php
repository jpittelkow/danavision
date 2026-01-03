<?php

namespace App\Services\Search;

use App\Models\LocalStoreCache;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class LocalStoreService
{
    protected int $userId;
    protected ?string $homeZipCode;
    protected ?string $homeAddress;
    protected ?float $homeLatitude;
    protected ?float $homeLongitude;
    protected WebSearchService $webSearch;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->homeZipCode = Setting::get(Setting::HOME_ZIP_CODE, $userId);
        $this->homeAddress = Setting::get(Setting::HOME_ADDRESS, $userId);
        $this->homeLatitude = Setting::get(Setting::HOME_LATITUDE, $userId) ? (float) Setting::get(Setting::HOME_LATITUDE, $userId) : null;
        $this->homeLongitude = Setting::get(Setting::HOME_LONGITUDE, $userId) ? (float) Setting::get(Setting::HOME_LONGITUDE, $userId) : null;
        $this->webSearch = WebSearchService::forUser($userId);
    }

    /**
     * Create an instance for a specific user.
     */
    public static function forUser(int $userId): self
    {
        return new self($userId);
    }

    /**
     * Check if local store service is available.
     * Service is available if we have either a zip code, address, or coordinates.
     */
    public function isAvailable(): bool
    {
        return !empty($this->homeZipCode) 
            || !empty($this->homeAddress) 
            || ($this->homeLatitude !== null && $this->homeLongitude !== null);
    }

    /**
     * Get the user's home zip code.
     */
    public function getHomeZipCode(): ?string
    {
        return $this->homeZipCode;
    }

    /**
     * Get the user's home address.
     */
    public function getHomeAddress(): ?string
    {
        return $this->homeAddress;
    }

    /**
     * Get the user's home coordinates.
     */
    public function getHomeCoordinates(): ?array
    {
        if ($this->homeLatitude !== null && $this->homeLongitude !== null) {
            return [
                'latitude' => $this->homeLatitude,
                'longitude' => $this->homeLongitude,
            ];
        }
        return null;
    }

    /**
     * Get a location string for search queries.
     * Uses address or coordinates if available, falls back to zip code.
     */
    public function getLocationString(): ?string
    {
        if ($this->homeAddress) {
            return $this->homeAddress;
        }
        
        if ($this->homeLatitude !== null && $this->homeLongitude !== null) {
            return "{$this->homeLatitude},{$this->homeLongitude}";
        }
        
        return $this->homeZipCode;
    }

    /**
     * Discover and cache local stores for the user.
     *
     * @param string|null $location Override location (zip code, address, or coordinates)
     * @param bool $forceRefresh Force refresh even if cached
     * @return array Array of discovered stores
     */
    public function discoverLocalStores(?string $location = null, bool $forceRefresh = false): array
    {
        // Use provided location or get from user settings
        $location = $location ?? $this->getLocationString();
        $cacheKey = $this->getCacheKey($location);

        if (!$location) {
            return [];
        }

        // Check if we have cached stores and don't need refresh
        if (!$forceRefresh) {
            $cached = $this->getCachedStores($cacheKey);
            if (!empty($cached)) {
                return $cached;
            }
        }

        // Discover stores via web search
        $storeTypes = ['grocery', 'pharmacy', 'electronics', 'retail'];
        $allStores = [];

        foreach ($storeTypes as $type) {
            $stores = $this->webSearch->searchLocalStoresWithCache($location, $type, 86400);
            foreach ($stores as $store) {
                $store['store_type'] = $store['store_type'] ?? $type;
                $allStores[] = $store;
            }
        }

        // Deduplicate by store name
        $uniqueStores = collect($allStores)
            ->unique('store_name')
            ->values()
            ->toArray();

        // Cache the discovered stores
        $this->cacheStores($cacheKey, $uniqueStores);

        return $uniqueStores;
    }

    /**
     * Get a cache key from a location string.
     * This normalizes the location for caching purposes.
     */
    protected function getCacheKey(?string $location): string
    {
        if (!$location) {
            return '';
        }
        
        // If it looks like coordinates, use them as-is
        if (preg_match('/^-?\d+\.?\d*,-?\d+\.?\d*$/', $location)) {
            return $location;
        }
        
        // For zip codes (5 digits), use directly
        if (preg_match('/^\d{5}(-\d{4})?$/', $location)) {
            return $location;
        }
        
        // For addresses, extract the zip code if possible, otherwise hash
        if (preg_match('/\b(\d{5}(-\d{4})?)\b/', $location, $matches)) {
            return $matches[1];
        }
        
        // Fallback: hash the location for cache key
        return md5($location);
    }

    /**
     * Get local stores for a user.
     *
     * @param string|null $location Override location (zip code, address, or coordinates)
     * @param string|null $storeType Filter by store type
     * @return array Array of local stores
     */
    public function getLocalStores(?string $location = null, ?string $storeType = null): array
    {
        // Use provided location or get from user settings
        $location = $location ?? $this->getLocationString();
        $cacheKey = $this->getCacheKey($location);

        if (!$location) {
            return [];
        }

        // First try to get from database cache
        $stores = $this->getCachedStores($cacheKey, $storeType);

        // If no cached stores, discover them
        if (empty($stores)) {
            $stores = $this->discoverLocalStores($location);
            
            // Filter by type if specified
            if ($storeType) {
                $stores = array_filter($stores, fn($s) => $s['store_type'] === $storeType);
                $stores = array_values($stores);
            }
        }

        return $stores;
    }

    /**
     * Get local grocery stores.
     */
    public function getLocalGroceryStores(?string $location = null): array
    {
        $stores = $this->getLocalStores($location);
        
        return array_values(array_filter($stores, function ($store) {
            $type = strtolower($store['store_type'] ?? '');
            return in_array($type, ['supermarket', 'grocery', 'warehouse', 'discount']);
        }));
    }

    /**
     * Get store names for use in search queries.
     */
    public function getLocalStoreNames(?string $location = null, int $limit = 5): array
    {
        $stores = $this->getLocalStores($location);
        
        return array_slice(
            array_column($stores, 'store_name'),
            0,
            $limit
        );
    }

    /**
     * Get cached stores from database.
     */
    protected function getCachedStores(string $locationKey, ?string $storeType = null): array
    {
        $query = LocalStoreCache::where('user_id', $this->userId)
            ->where('zip_code', $locationKey) // Using zip_code column for any location key
            ->where('discovered_at', '>=', now()->subDays(7)); // Cache valid for 7 days

        if ($storeType) {
            $query->where('store_type', $storeType);
        }

        return $query->get()
            ->map(fn($store) => [
                'store_name' => $store->store_name,
                'store_type' => $store->store_type,
                'address' => $store->address,
                'phone' => $store->phone,
                'website' => $store->website,
                'rating' => $store->rating,
            ])
            ->toArray();
    }

    /**
     * Cache discovered stores in database.
     */
    protected function cacheStores(string $locationKey, array $stores): void
    {
        // Delete old cache entries for this user/location
        LocalStoreCache::where('user_id', $this->userId)
            ->where('zip_code', $locationKey)
            ->delete();

        // Insert new stores
        foreach ($stores as $store) {
            try {
                LocalStoreCache::create([
                    'user_id' => $this->userId,
                    'zip_code' => $locationKey, // Using zip_code column for any location key
                    'store_name' => $store['store_name'] ?? 'Unknown',
                    'store_type' => $store['store_type'] ?? 'retail',
                    'address' => $store['address'] ?? null,
                    'phone' => $store['phone'] ?? null,
                    'website' => $store['website'] ?? null,
                    'rating' => $store['rating'] ?? null,
                    'discovered_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to cache local store', [
                    'store' => $store,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Refresh the local store cache.
     */
    public function refreshStoreCache(?string $location = null): array
    {
        return $this->discoverLocalStores($location, forceRefresh: true);
    }

    /**
     * Clear the local store cache.
     */
    public function clearCache(?string $location = null): void
    {
        $query = LocalStoreCache::where('user_id', $this->userId);
        
        if ($location) {
            $locationKey = $this->getCacheKey($location);
            $query->where('zip_code', $locationKey);
        }

        $query->delete();
    }

    /**
     * Get store suggestions based on product type.
     *
     * @param string $productType Type of product (e.g., 'produce', 'electronics', 'pharmacy')
     * @return array Relevant local stores
     */
    public function getStoresForProductType(string $productType): array
    {
        $stores = $this->getLocalStores();

        $relevantTypes = match (strtolower($productType)) {
            'produce', 'grocery', 'food', 'meat', 'dairy', 'bakery' => ['supermarket', 'grocery', 'warehouse'],
            'electronics', 'computer', 'phone', 'tv' => ['electronics', 'retail'],
            'pharmacy', 'medicine', 'health' => ['pharmacy'],
            'clothing', 'apparel' => ['retail'],
            default => ['supermarket', 'retail', 'warehouse'],
        };

        return array_values(array_filter($stores, function ($store) use ($relevantTypes) {
            return in_array(strtolower($store['store_type'] ?? ''), $relevantTypes);
        }));
    }
}
