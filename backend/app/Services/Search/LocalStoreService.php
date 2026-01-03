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
    protected WebSearchService $webSearch;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->homeZipCode = Setting::get(Setting::HOME_ZIP_CODE, $userId);
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
     */
    public function isAvailable(): bool
    {
        return !empty($this->homeZipCode);
    }

    /**
     * Get the user's home zip code.
     */
    public function getHomeZipCode(): ?string
    {
        return $this->homeZipCode;
    }

    /**
     * Discover and cache local stores for the user.
     *
     * @param string|null $zipCode Override zip code (defaults to user's home_zip_code)
     * @param bool $forceRefresh Force refresh even if cached
     * @return array Array of discovered stores
     */
    public function discoverLocalStores(?string $zipCode = null, bool $forceRefresh = false): array
    {
        $zipCode = $zipCode ?? $this->homeZipCode;

        if (!$zipCode) {
            return [];
        }

        // Check if we have cached stores and don't need refresh
        if (!$forceRefresh) {
            $cached = $this->getCachedStores($zipCode);
            if (!empty($cached)) {
                return $cached;
            }
        }

        // Discover stores via web search
        $storeTypes = ['grocery', 'pharmacy', 'electronics', 'retail'];
        $allStores = [];

        foreach ($storeTypes as $type) {
            $stores = $this->webSearch->searchLocalStoresWithCache($zipCode, $type, 86400);
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
        $this->cacheStores($zipCode, $uniqueStores);

        return $uniqueStores;
    }

    /**
     * Get local stores for a user.
     *
     * @param string|null $zipCode Override zip code
     * @param string|null $storeType Filter by store type
     * @return array Array of local stores
     */
    public function getLocalStores(?string $zipCode = null, ?string $storeType = null): array
    {
        $zipCode = $zipCode ?? $this->homeZipCode;

        if (!$zipCode) {
            return [];
        }

        // First try to get from database cache
        $stores = $this->getCachedStores($zipCode, $storeType);

        // If no cached stores, discover them
        if (empty($stores)) {
            $stores = $this->discoverLocalStores($zipCode);
            
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
    public function getLocalGroceryStores(?string $zipCode = null): array
    {
        $stores = $this->getLocalStores($zipCode);
        
        return array_values(array_filter($stores, function ($store) {
            $type = strtolower($store['store_type'] ?? '');
            return in_array($type, ['supermarket', 'grocery', 'warehouse', 'discount']);
        }));
    }

    /**
     * Get store names for use in search queries.
     */
    public function getLocalStoreNames(?string $zipCode = null, int $limit = 5): array
    {
        $stores = $this->getLocalStores($zipCode);
        
        return array_slice(
            array_column($stores, 'store_name'),
            0,
            $limit
        );
    }

    /**
     * Get cached stores from database.
     */
    protected function getCachedStores(string $zipCode, ?string $storeType = null): array
    {
        $query = LocalStoreCache::where('user_id', $this->userId)
            ->where('zip_code', $zipCode)
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
    protected function cacheStores(string $zipCode, array $stores): void
    {
        // Delete old cache entries for this user/zip
        LocalStoreCache::where('user_id', $this->userId)
            ->where('zip_code', $zipCode)
            ->delete();

        // Insert new stores
        foreach ($stores as $store) {
            try {
                LocalStoreCache::create([
                    'user_id' => $this->userId,
                    'zip_code' => $zipCode,
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
    public function refreshStoreCache(?string $zipCode = null): array
    {
        return $this->discoverLocalStores($zipCode, forceRefresh: true);
    }

    /**
     * Clear the local store cache.
     */
    public function clearCache(?string $zipCode = null): void
    {
        $query = LocalStoreCache::where('user_id', $this->userId);
        
        if ($zipCode) {
            $query->where('zip_code', $zipCode);
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
