<?php

namespace App\Services\Crawler;

use App\Models\Setting;
use App\Models\Store;
use App\Models\UserStorePreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * StoreDiscoveryService
 *
 * Implements tiered price discovery using the Store Registry system.
 * This service significantly reduces Firecrawl API costs by:
 * 
 * 1. Tier 1: Using pre-defined URL templates for known stores (cheapest)
 * 2. Tier 2: Using Firecrawl Search API for discovery (medium cost)
 * 3. Tier 3: Falling back to Agent API only when necessary (most expensive)
 *
 * Cost comparison:
 * - Agent API: ~50-100 credits per search
 * - Search API: ~5-10 credits per search
 * - Scrape API: ~1 credit per URL
 */
class StoreDiscoveryService
{
    protected FirecrawlService $firecrawlService;
    protected int $userId;

    /**
     * Minimum number of results before triggering additional discovery.
     */
    protected int $minResultsThreshold = 3;

    /**
     * Maximum number of stores to search in Tier 1.
     */
    protected int $maxStoresPerSearch = 10;

    /**
     * Create a new StoreDiscoveryService instance.
     *
     * @param int $userId The user ID
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->firecrawlService = FirecrawlService::forUser($userId);
    }

    /**
     * Create an instance for a specific user.
     *
     * @param int $userId
     * @return self
     */
    public static function forUser(int $userId): self
    {
        return new self($userId);
    }

    /**
     * Check if the service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->firecrawlService->isAvailable();
    }

    /**
     * Discover product prices using the tiered approach.
     *
     * @param string $productName The product name to search for
     * @param array $options Options including:
     *   - shop_local: bool - Prefer local stores
     *   - upc: string|null - Product UPC for more accurate matching
     *   - brand: string|null - Product brand
     *   - is_generic: bool - Whether this is a generic item
     *   - unit_of_measure: string|null - Unit of measure
     *   - skip_discovery: bool - Skip Tier 2 discovery even if few results
     *   - stores: array|null - Specific store IDs to search (overrides user preferences)
     *   - max_stores: int - Maximum number of stores to search
     * @return FirecrawlResult
     */
    public function discoverPrices(string $productName, array $options = []): FirecrawlResult
    {
        if (!$this->isAvailable()) {
            Log::warning('StoreDiscoveryService: Firecrawl not available', ['user_id' => $this->userId]);
            return FirecrawlResult::error('Firecrawl API key not configured');
        }

        $shopLocal = $options['shop_local'] ?? false;
        $skipDiscovery = $options['skip_discovery'] ?? false;
        $maxStores = $options['max_stores'] ?? $this->maxStoresPerSearch;

        Log::info('StoreDiscoveryService: Starting tiered discovery', [
            'user_id' => $this->userId,
            'product' => $productName,
            'shop_local' => $shopLocal,
            'skip_discovery' => $skipDiscovery,
        ]);

        // Build the search query
        $searchQuery = $this->buildSearchQuery($productName, $options);

        // Get stores to search
        $stores = $this->getStoresToSearch($options, $shopLocal, $maxStores);

        Log::info('StoreDiscoveryService: Stores selected for Tier 1', [
            'store_count' => $stores->count(),
            'stores' => $stores->pluck('name')->toArray(),
        ]);

        // ===== TIER 1: Use URL templates for known stores =====
        $tier1Results = $this->tier1TemplateSearch($searchQuery, $stores);

        Log::info('StoreDiscoveryService: Tier 1 completed', [
            'results_count' => $tier1Results->count(),
            'stores_found' => $tier1Results->hasResults() 
                ? array_unique(array_column($tier1Results->results, 'store_name'))
                : [],
        ]);

        // If we have enough results or discovery is disabled, return
        if ($tier1Results->count() >= $this->minResultsThreshold || $skipDiscovery) {
            return $tier1Results;
        }

        // ===== TIER 2: Use Search API for discovery =====
        Log::info('StoreDiscoveryService: Proceeding to Tier 2 discovery', [
            'tier1_count' => $tier1Results->count(),
            'threshold' => $this->minResultsThreshold,
        ]);

        $tier2Results = $this->tier2SearchDiscovery($searchQuery, $productName);

        // Merge results from both tiers
        $mergedResults = $this->mergeResults($tier1Results, $tier2Results);

        Log::info('StoreDiscoveryService: Tier 2 completed', [
            'tier2_count' => $tier2Results->count(),
            'merged_count' => $mergedResults->count(),
        ]);

        // Learn new stores from Tier 2 results
        if ($tier2Results->hasResults()) {
            $this->learnNewStores($tier2Results->results);
        }

        return $mergedResults;
    }

    /**
     * Build a search query from product name and options.
     *
     * @param string $productName
     * @param array $options
     * @return string
     */
    protected function buildSearchQuery(string $productName, array $options): string
    {
        $query = $productName;

        // Add brand if not already in the name
        $brand = $options['brand'] ?? null;
        if ($brand && !str_contains(strtolower($productName), strtolower($brand))) {
            $query = "{$brand} {$productName}";
        }

        return $query;
    }

    /**
     * Get the stores to search based on user preferences and options.
     *
     * @param array $options
     * @param bool $shopLocal
     * @param int $maxStores
     * @return Collection
     */
    protected function getStoresToSearch(array $options, bool $shopLocal, int $maxStores): Collection
    {
        // If specific stores are requested, use those
        if (!empty($options['stores'])) {
            return Store::whereIn('id', $options['stores'])
                ->active()
                ->take($maxStores)
                ->get();
        }

        // Get user's enabled stores, sorted by priority
        $stores = UserStorePreference::getAllStoresForUser($this->userId);

        // Filter by local if shop_local is enabled
        if ($shopLocal) {
            $localStores = $stores->filter(fn ($store) => $store->is_local);
            
            // If we have local stores, prioritize them but include some national
            if ($localStores->count() >= 3) {
                $stores = $localStores->merge(
                    $stores->filter(fn ($store) => !$store->is_local)->take(3)
                );
            }
        }

        // Filter to stores that have search URL templates
        $stores = $stores->filter(fn ($store) => !empty($store->search_url_template));

        return $stores->take($maxStores);
    }

    /**
     * Tier 1: Search using URL templates from known stores.
     * This is the most cost-effective method.
     *
     * @param string $query The search query
     * @param Collection $stores The stores to search
     * @return FirecrawlResult
     */
    protected function tier1TemplateSearch(string $query, Collection $stores): FirecrawlResult
    {
        if ($stores->isEmpty()) {
            Log::info('StoreDiscoveryService: No stores available for Tier 1');
            return FirecrawlResult::success([], 'tier1_empty');
        }

        // Get user's location settings for location-aware search URLs
        $userLocationContext = $this->getUserLocationContext();

        // Get user's store preferences (for store-specific location IDs)
        $storePreferences = UserStorePreference::where('user_id', $this->userId)
            ->whereNotNull('location_id')
            ->get()
            ->keyBy('store_id');

        // Generate search URLs from templates
        $urls = [];
        $storeMap = []; // Map URLs to stores for result attribution

        foreach ($stores as $store) {
            // Build location context for this specific store
            $context = $userLocationContext;
            
            // Add store-specific location ID if the user has set one
            $preference = $storePreferences->get($store->id);
            if ($preference && $preference->location_id) {
                $context['store_id'] = $preference->location_id;
            }

            $url = $store->generateSearchUrl($query, $context);
            if ($url) {
                $urls[] = $url;
                $storeMap[$url] = $store;
            }
        }

        if (empty($urls)) {
            Log::warning('StoreDiscoveryService: No URLs generated from templates');
            return FirecrawlResult::success([], 'tier1_no_urls');
        }

        Log::info('StoreDiscoveryService: Tier 1 scraping URLs', [
            'urls_count' => count($urls),
            'urls' => array_slice($urls, 0, 5),
        ]);

        // Use batch scraping for efficiency
        $result = $this->firecrawlService->scrapeUrlsBatch($urls);

        // Attribute results to stores (copy array since FirecrawlResult::$results is readonly)
        $attributedResults = [];
        if ($result->hasResults()) {
            foreach ($result->results as $priceResult) {
                $url = $priceResult['product_url'] ?? null;
                if ($url) {
                    // Try to match URL to a store
                    foreach ($storeMap as $searchUrl => $store) {
                        if ($store->matchesUrl($url)) {
                            $priceResult['store_name'] = $store->name;
                            break;
                        }
                    }
                }
                $attributedResults[] = $priceResult;
            }
        }

        return FirecrawlResult::success($attributedResults, 'tier1_template');
    }

    /**
     * Get user's location context for URL template substitution.
     *
     * @return array Location context with zip, lat, lng
     */
    protected function getUserLocationContext(): array
    {
        return [
            'zip' => Setting::get(Setting::HOME_ZIP_CODE, $this->userId) ?? '',
            'lat' => Setting::get(Setting::HOME_LATITUDE, $this->userId) ?? '',
            'lng' => Setting::get(Setting::HOME_LONGITUDE, $this->userId) ?? '',
        ];
    }

    /**
     * Tier 2: Use Firecrawl Search API for discovery.
     * More expensive than Tier 1 but still much cheaper than Agent.
     *
     * @param string $query The search query
     * @param string $productName Original product name for logging
     * @return FirecrawlResult
     */
    protected function tier2SearchDiscovery(string $query, string $productName): FirecrawlResult
    {
        Log::info('StoreDiscoveryService: Starting Tier 2 search discovery', [
            'query' => $query,
            'product' => $productName,
        ]);

        return $this->firecrawlService->searchProducts($query, 10, true);
    }

    /**
     * Merge results from multiple tiers, removing duplicates.
     *
     * @param FirecrawlResult ...$results
     * @return FirecrawlResult
     */
    protected function mergeResults(FirecrawlResult ...$results): FirecrawlResult
    {
        $allResults = [];
        $seenStores = [];

        foreach ($results as $result) {
            if (!$result->hasResults()) {
                continue;
            }

            foreach ($result->results as $priceResult) {
                $storeName = strtolower($priceResult['store_name'] ?? 'unknown');
                
                // Skip duplicates from the same store (keep the first/best price)
                if (isset($seenStores[$storeName])) {
                    continue;
                }

                $seenStores[$storeName] = true;
                $allResults[] = $priceResult;
            }
        }

        // Sort by price ascending
        usort($allResults, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));

        return FirecrawlResult::success($allResults, 'merged');
    }

    /**
     * Learn new stores from discovery results and add them to the registry.
     *
     * @param array $results Price results that may contain new stores
     * @return void
     */
    protected function learnNewStores(array $results): void
    {
        foreach ($results as $result) {
            $url = $result['product_url'] ?? null;
            if (!$url) {
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST);
            if (!$host) {
                continue;
            }

            // Check if we already have this store
            $existingStore = Store::findByDomain($host);
            if ($existingStore) {
                continue;
            }

            // Clean up the domain
            $domain = preg_replace('/^www\./', '', strtolower($host));
            $storeName = $result['store_name'] ?? ucfirst(explode('.', $domain)[0]);

            // Create a new store entry (without URL template - can be added later)
            try {
                Store::create([
                    'name' => $storeName,
                    'domain' => $domain,
                    'search_url_template' => null, // Will need manual configuration
                    'is_default' => false,
                    'is_local' => false,
                    'is_active' => true,
                    'category' => Store::CATEGORY_SPECIALTY,
                    'default_priority' => 30, // Low priority until configured
                ]);

                Log::info('StoreDiscoveryService: Learned new store', [
                    'name' => $storeName,
                    'domain' => $domain,
                ]);
            } catch (\Exception $e) {
                // Store might already exist (race condition) - ignore
                Log::debug('StoreDiscoveryService: Could not add store', [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Refresh prices for a product using only known store URLs.
     * Used for daily price updates - most cost-effective.
     *
     * @param array $urls Known product URLs to scrape
     * @return FirecrawlResult
     */
    public function refreshPrices(array $urls): FirecrawlResult
    {
        if (empty($urls)) {
            return FirecrawlResult::error('No URLs provided for refresh');
        }

        Log::info('StoreDiscoveryService: Refreshing prices from known URLs', [
            'urls_count' => count($urls),
        ]);

        return $this->firecrawlService->scrapeProductUrls($urls);
    }

    /**
     * Set the minimum results threshold for triggering Tier 2 discovery.
     *
     * @param int $threshold
     * @return self
     */
    public function setMinResultsThreshold(int $threshold): self
    {
        $this->minResultsThreshold = $threshold;
        return $this;
    }

    /**
     * Set the maximum number of stores to search in Tier 1.
     *
     * @param int $maxStores
     * @return self
     */
    public function setMaxStoresPerSearch(int $maxStores): self
    {
        $this->maxStoresPerSearch = $maxStores;
        return $this;
    }

    /**
     * Get statistics about the store registry.
     *
     * @return array
     */
    public function getStoreStats(): array
    {
        $totalStores = Store::count();
        $activeStores = Store::active()->count();
        $defaultStores = Store::default()->count();
        $storesWithTemplates = Store::whereNotNull('search_url_template')->count();
        $userPreferences = UserStorePreference::where('user_id', $this->userId)->count();

        return [
            'total_stores' => $totalStores,
            'active_stores' => $activeStores,
            'default_stores' => $defaultStores,
            'stores_with_templates' => $storesWithTemplates,
            'user_preferences' => $userPreferences,
        ];
    }
}
