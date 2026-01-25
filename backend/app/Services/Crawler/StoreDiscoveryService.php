<?php

namespace App\Services\Crawler;

use App\Models\Setting;
use App\Models\Store;
use App\Models\UserStorePreference;
use App\Services\AI\AIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * StoreDiscoveryService
 *
 * Implements tiered price discovery using the Store Registry system.
 * Uses Crawl4AI for local web scraping (free) with AI-based price extraction.
 *
 * Tiers:
 * 1. Tier 1: URL templates for known stores + Crawl4AI scraping (free scraping)
 * 2. Tier 2: Web search + Crawl4AI scraping for discovery (free scraping)
 *
 * Cost: Only LLM API calls for price extraction (~$0.002/extraction with gpt-4o-mini)
 *
 * @see docs/adr/016-crawl4ai-integration.md
 */
class StoreDiscoveryService
{
    protected Crawl4AIService $crawl4aiService;
    protected ?AIService $aiService = null;
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
        $this->crawl4aiService = new Crawl4AIService();
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
     * Crawl4AI runs locally in the container, so it's always available if the service is up.
     * Also requires an AI service for price extraction.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        // Crawl4AI must be running and AI service must be configured
        return $this->crawl4aiService->isAvailable() && $this->getAIService() !== null;
    }

    /**
     * Get the AI service for price extraction.
     *
     * @return AIService|null
     */
    protected function getAIService(): ?AIService
    {
        if ($this->aiService === null) {
            $this->aiService = AIService::forUser($this->userId);
        }
        return $this->aiService;
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
            Log::warning('StoreDiscoveryService: Service not available', ['user_id' => $this->userId]);
            return FirecrawlResult::error('Crawl4AI service not running or AI provider not configured');
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
     * Uses Crawl4AI for free scraping + AI for price extraction.
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

        Log::info('StoreDiscoveryService: Tier 1 scraping URLs with Crawl4AI', [
            'urls_count' => count($urls),
            'urls' => array_slice($urls, 0, 5),
        ]);

        // Use Crawl4AI for batch scraping (free - no API costs)
        $scrapedPages = $this->crawl4aiService->scrapeUrls($urls);

        // Extract prices from scraped pages using AI
        $attributedResults = [];
        foreach ($scrapedPages as $index => $page) {
            $url = $urls[$index] ?? null;
            if (!$url || !($page['success'] ?? false)) {
                Log::warning('StoreDiscoveryService: Scrape failed for URL', [
                    'url' => $url,
                    'error' => $page['error'] ?? 'Unknown error',
                ]);
                continue;
            }

            $markdown = $page['markdown'] ?? '';
            if (empty($markdown)) {
                continue;
            }

            // Find the store for this URL
            $store = $storeMap[$url] ?? null;
            $storeName = $store?->name ?? $this->extractStoreFromUrl($url);

            // Use AI to extract price from markdown
            $priceData = $this->extractPriceFromMarkdown($markdown, $query, $storeName);
            
            if ($priceData && isset($priceData['price']) && $priceData['price'] > 0) {
                $attributedResults[] = [
                    'store_name' => $storeName,
                    'item_name' => $priceData['item_name'] ?? $query,
                    'price' => (float) $priceData['price'],
                    'stock_status' => $priceData['stock_status'] ?? 'in_stock',
                    'unit_of_measure' => $priceData['unit_of_measure'] ?? null,
                    'product_url' => $url,
                ];
            }
        }

        Log::info('StoreDiscoveryService: Tier 1 extraction complete', [
            'urls_scraped' => count($scrapedPages),
            'prices_extracted' => count($attributedResults),
        ]);

        return FirecrawlResult::success($attributedResults, 'tier1_crawl4ai');
    }

    /**
     * Extract price data from markdown content using AI.
     *
     * @param string $markdown The page content as markdown
     * @param string $productName The product being searched for
     * @param string $storeName The store name for context
     * @return array|null Extracted price data or null if extraction fails
     */
    protected function extractPriceFromMarkdown(string $markdown, string $productName, string $storeName): ?array
    {
        $aiService = $this->getAIService();
        if (!$aiService) {
            return null;
        }

        // Truncate markdown to avoid token limits (keep first ~4000 chars)
        $truncatedMarkdown = mb_substr($markdown, 0, 4000);

        $prompt = <<<PROMPT
Extract price information for "{$productName}" from this {$storeName} page content.

Page content:
{$truncatedMarkdown}

Return ONLY a JSON object with these fields (no other text):
{
    "item_name": "The exact product name shown on the page",
    "price": 99.99,
    "stock_status": "in_stock" or "out_of_stock" or "limited_stock",
    "unit_of_measure": "lb" or "oz" or "each" or null
}

Rules:
- price must be a number (no currency symbols)
- If no price is found, return {"price": null}
- Only extract the price for the specific product, not related items
- Ignore shipping costs, only report item price
PROMPT;

        try {
            $response = $aiService->complete($prompt, ['max_tokens' => 200]);
            
            // Parse JSON from response
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['price'])) {
                    return $parsed;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('StoreDiscoveryService: AI extraction failed', [
                'product' => $productName,
                'store' => $storeName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract store name from a URL.
     *
     * @param string $url
     * @return string
     */
    protected function extractStoreFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return 'Unknown';
        }

        // Remove www. prefix
        $host = preg_replace('/^www\./', '', $host);

        // Map common domains to store names
        $storeMap = [
            'amazon.com' => 'Amazon',
            'walmart.com' => 'Walmart',
            'target.com' => 'Target',
            'bestbuy.com' => 'Best Buy',
            'costco.com' => 'Costco',
            'kroger.com' => 'Kroger',
            'publix.com' => 'Publix',
            'safeway.com' => 'Safeway',
            'wholefoodsmarket.com' => 'Whole Foods',
            'homedepot.com' => 'Home Depot',
            'lowes.com' => "Lowe's",
        ];

        foreach ($storeMap as $domain => $name) {
            if (str_contains($host, $domain)) {
                return $name;
            }
        }

        // Return the domain with first letter capitalized
        $parts = explode('.', $host);
        return ucfirst($parts[0]);
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
     * Tier 2: Search major online retailers directly.
     * Uses Crawl4AI to scrape search results from major retailers.
     *
     * @param string $query The search query
     * @param string $productName Original product name for logging
     * @return FirecrawlResult
     */
    protected function tier2SearchDiscovery(string $query, string $productName): FirecrawlResult
    {
        Log::info('StoreDiscoveryService: Starting Tier 2 discovery with major retailers', [
            'query' => $query,
            'product' => $productName,
        ]);

        // Major online retailers with search URL patterns
        $retailerSearchUrls = [
            'Amazon' => 'https://www.amazon.com/s?k=' . urlencode($query),
            'Walmart' => 'https://www.walmart.com/search?q=' . urlencode($query),
            'Target' => 'https://www.target.com/s?searchTerm=' . urlencode($query),
            'Best Buy' => 'https://www.bestbuy.com/site/searchpage.jsp?st=' . urlencode($query),
        ];

        $urls = array_values($retailerSearchUrls);
        $storeNames = array_keys($retailerSearchUrls);

        Log::info('StoreDiscoveryService: Tier 2 scraping major retailers', [
            'retailers' => $storeNames,
        ]);

        // Scrape all retailer search pages
        $scrapedPages = $this->crawl4aiService->scrapeUrls($urls);

        $results = [];
        foreach ($scrapedPages as $index => $page) {
            $storeName = $storeNames[$index] ?? 'Unknown';
            $url = $urls[$index] ?? null;

            if (!($page['success'] ?? false)) {
                Log::warning('StoreDiscoveryService: Tier 2 scrape failed', [
                    'store' => $storeName,
                    'error' => $page['error'] ?? 'Unknown error',
                ]);
                continue;
            }

            $markdown = $page['markdown'] ?? '';
            if (empty($markdown)) {
                continue;
            }

            // Extract price from search results page
            $priceData = $this->extractPriceFromSearchResults($markdown, $productName, $storeName);
            
            if ($priceData && isset($priceData['price']) && $priceData['price'] > 0) {
                $results[] = [
                    'store_name' => $storeName,
                    'item_name' => $priceData['item_name'] ?? $productName,
                    'price' => (float) $priceData['price'],
                    'stock_status' => $priceData['stock_status'] ?? 'in_stock',
                    'unit_of_measure' => $priceData['unit_of_measure'] ?? null,
                    'product_url' => $priceData['product_url'] ?? $url,
                ];
            }
        }

        Log::info('StoreDiscoveryService: Tier 2 discovery complete', [
            'retailers_scraped' => count($scrapedPages),
            'prices_found' => count($results),
        ]);

        return FirecrawlResult::success($results, 'tier2_crawl4ai');
    }

    /**
     * Extract the best matching price from search results using AI.
     *
     * @param string $markdown The search results page as markdown
     * @param string $productName The product being searched for
     * @param string $storeName The store name
     * @return array|null Extracted price data
     */
    protected function extractPriceFromSearchResults(string $markdown, string $productName, string $storeName): ?array
    {
        $aiService = $this->getAIService();
        if (!$aiService) {
            return null;
        }

        // Truncate markdown to avoid token limits
        $truncatedMarkdown = mb_substr($markdown, 0, 6000);

        $prompt = <<<PROMPT
Find the best matching product and price for "{$productName}" from these {$storeName} search results.

Search results:
{$truncatedMarkdown}

Return ONLY a JSON object with the best matching product (no other text):
{
    "item_name": "The exact product name",
    "price": 99.99,
    "stock_status": "in_stock" or "out_of_stock",
    "product_url": "URL to the product page if available, or null"
}

Rules:
- Find the product that best matches "{$productName}"
- price must be a number (no currency symbols)
- If no matching product found, return {"price": null}
- Only include items that are clearly the right product
- Prefer exact matches over similar products
PROMPT;

        try {
            $response = $aiService->complete($prompt, ['max_tokens' => 300]);
            
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['price'])) {
                    return $parsed;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('StoreDiscoveryService: Tier 2 AI extraction failed', [
                'product' => $productName,
                'store' => $storeName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
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
     * Uses Crawl4AI for free scraping + AI for price extraction.
     *
     * @param array $urls Known product URLs to scrape
     * @param string|null $productName Product name for context (optional)
     * @return FirecrawlResult
     */
    public function refreshPrices(array $urls, ?string $productName = null): FirecrawlResult
    {
        if (empty($urls)) {
            return FirecrawlResult::error('No URLs provided for refresh');
        }

        Log::info('StoreDiscoveryService: Refreshing prices with Crawl4AI', [
            'urls_count' => count($urls),
        ]);

        // Scrape all URLs with Crawl4AI
        $scrapedPages = $this->crawl4aiService->scrapeUrls($urls);

        $results = [];
        foreach ($scrapedPages as $index => $page) {
            $url = $urls[$index] ?? null;
            
            if (!$url || !($page['success'] ?? false)) {
                Log::warning('StoreDiscoveryService: Refresh scrape failed', [
                    'url' => $url,
                    'error' => $page['error'] ?? 'Unknown error',
                ]);
                continue;
            }

            $markdown = $page['markdown'] ?? '';
            if (empty($markdown)) {
                continue;
            }

            $storeName = $this->extractStoreFromUrl($url);
            $searchQuery = $productName ?? 'product';

            // Extract price from product page
            $priceData = $this->extractPriceFromMarkdown($markdown, $searchQuery, $storeName);
            
            if ($priceData && isset($priceData['price']) && $priceData['price'] > 0) {
                $results[] = [
                    'store_name' => $storeName,
                    'item_name' => $priceData['item_name'] ?? $searchQuery,
                    'price' => (float) $priceData['price'],
                    'stock_status' => $priceData['stock_status'] ?? 'in_stock',
                    'unit_of_measure' => $priceData['unit_of_measure'] ?? null,
                    'product_url' => $url,
                ];
            }
        }

        Log::info('StoreDiscoveryService: Price refresh complete', [
            'urls_scraped' => count($scrapedPages),
            'prices_extracted' => count($results),
        ]);

        if (empty($results)) {
            return FirecrawlResult::error('No prices could be extracted from the provided URLs');
        }

        return FirecrawlResult::success($results, 'refresh_crawl4ai');
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
