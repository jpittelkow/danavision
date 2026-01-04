<?php

namespace App\Services\AI;

use App\Models\AIRequestLog;
use App\Services\Search\LocalStoreService;
use App\Services\Search\WebSearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AIPriceSearchService
 * 
 * Searches for product prices using SERP API as the primary data source,
 * with AI used purely for intelligent aggregation and analysis.
 * 
 * IMPORTANT: This service does NOT fabricate prices. If SERP API returns
 * no results, no results are returned. AI is only used to process and
 * structure real data from SERP API.
 */
class AIPriceSearchService
{
    protected int $userId;
    protected MultiAIService $multiAI;
    protected WebSearchService $webSearch;
    protected LocalStoreService $localStore;
    protected ?int $aiJobId = null;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->multiAI = MultiAIService::forUser($userId);
        $this->webSearch = WebSearchService::forUser($userId);
        $this->localStore = LocalStoreService::forUser($userId);
    }

    /**
     * Create an instance for a specific user.
     */
    public static function forUser(int $userId): self
    {
        return new self($userId);
    }

    /**
     * Set the AI job ID for logging purposes.
     */
    public function setAIJobId(?int $aiJobId): self
    {
        $this->aiJobId = $aiJobId;
        return $this;
    }

    /**
     * Check if the service is available (has at least one active AI provider).
     */
    public function isAvailable(): bool
    {
        return $this->multiAI->isAvailable();
    }

    /**
     * Check if web search is available.
     */
    public function isWebSearchAvailable(): bool
    {
        return $this->webSearch->isAvailable();
    }

    /**
     * Get the number of active providers.
     */
    public function getProviderCount(): int
    {
        return $this->multiAI->getProviderCount();
    }

    /**
     * Search for product prices using SERP API + AI aggregation.
     * 
     * IMPORTANT: SERP API is the ONLY source of pricing data.
     * AI is used only to structure and analyze results, NOT to generate prices.
     *
     * @param string $query The search query
     * @param array $options Options including:
     *   - is_generic: bool - Whether this is a generic item (e.g., produce)
     *   - unit_of_measure: string|null - Unit of measure for generic items
     *   - shop_local: bool - Whether to prioritize local stores
     *   - zip_code: string|null - User's zip code for location-based search
     */
    public function search(string $query, array $options = []): AIPriceSearchResult
    {
        $isGeneric = $options['is_generic'] ?? false;
        $unitOfMeasure = $options['unit_of_measure'] ?? null;
        $shopLocal = $options['shop_local'] ?? false;
        $zipCode = $options['zip_code'] ?? $this->localStore->getHomeZipCode();

        // Check if SERP API is available - this is REQUIRED for price search
        if (!$this->webSearch->isAvailable()) {
            return new AIPriceSearchResult(
                query: $query,
                results: [],
                lowestPrice: null,
                highestPrice: null,
                searchedAt: now(),
                error: 'SERP API is not configured. Please set up a SerpAPI key in Settings to enable price search.',
                providersUsed: [],
            );
        }

        // Get local stores if shop_local is enabled
        $localStores = [];
        if ($shopLocal && $zipCode) {
            $localStores = $this->localStore->getLocalStores($zipCode);
        }

        // Get real-time pricing data from SERP API
        $webResults = $this->webSearch->searchPrices($query, [
            'shop_local' => $shopLocal,
            'zip_code' => $zipCode,
            'local_stores' => $localStores,
        ]);

        // Store raw SERP data for logging
        $rawSerpData = [
            'query' => $query,
            'results_count' => count($webResults),
            'results' => $webResults,
            'options' => $options,
        ];

        // If SERP API returned no results, return empty results (do NOT fabricate)
        if (empty($webResults)) {
            Log::info('AIPriceSearchService: SERP API returned no results', [
                'query' => $query,
                'options' => $options,
            ]);

            return new AIPriceSearchResult(
                query: $query,
                results: [],
                lowestPrice: null,
                highestPrice: null,
                searchedAt: now(),
                error: 'No products found matching your search. Try a different search term.',
                providersUsed: ['web_search'],
                serpData: $rawSerpData,
            );
        }

        // Process SERP results with AI aggregation
        return $this->processWebSearchResults($query, $webResults, $options, $localStores, $rawSerpData);
    }

    /**
     * Process web search results with AI analysis.
     * 
     * AI is used to structure, validate, and enhance the SERP results.
     * It will NOT add any prices not present in the original results.
     */
    protected function processWebSearchResults(
        string $query,
        array $webResults,
        array $options,
        array $localStores,
        array $rawSerpData
    ): AIPriceSearchResult {
        $isGeneric = $options['is_generic'] ?? false;
        $unitOfMeasure = $options['unit_of_measure'] ?? null;
        $shopLocal = $options['shop_local'] ?? false;

        // If AI is available, use it to structure and validate the results
        if ($this->isAvailable()) {
            $prompt = $this->buildAnalysisPrompt($query, $webResults, $isGeneric, $unitOfMeasure, $shopLocal, $localStores);
            
            try {
                // Use AILoggingService if a job ID is set
                $loggingService = $this->aiJobId 
                    ? AILoggingService::forUser($this->userId, $this->aiJobId)
                    : null;

                if ($loggingService) {
                    $response = $loggingService->complete($prompt, [], $rawSerpData);
                    $parsed = $this->parseSearchResponse($response);
                } else {
                    $result = $this->multiAI->processWithAllProviders($prompt);
                    
                    if ($result['error'] && !$result['aggregated_response']) {
                        throw new \RuntimeException($result['error']);
                    }
                    
                    $parsed = $this->parseSearchResponse($result['aggregated_response']);
                }

                // Validate AI output against original SERP data
                $validatedResults = $this->validateAgainstSerpData($parsed['results'], $webResults);
                
                if (!empty($validatedResults)) {
                    $prices = array_filter(array_column($validatedResults, 'price'));
                    
                    $providersUsed = ['web_search'];
                    if ($loggingService) {
                        $providersUsed[] = $loggingService->getProviderType();
                    }
                    
                    return new AIPriceSearchResult(
                        query: $query,
                        results: $validatedResults,
                        lowestPrice: !empty($prices) ? min($prices) : null,
                        highestPrice: !empty($prices) ? max($prices) : null,
                        searchedAt: now(),
                        error: null,
                        providersUsed: $providersUsed,
                        isGeneric: $parsed['is_generic'] ?? $isGeneric,
                        unitOfMeasure: $parsed['unit_of_measure'] ?? $unitOfMeasure,
                        serpData: $rawSerpData,
                    );
                }
            } catch (\Exception $e) {
                Log::warning('AI analysis of SERP results failed, using raw results', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Return SERP results directly if AI processing fails or is unavailable
        $prices = array_filter(array_column($webResults, 'price'));
        
        return new AIPriceSearchResult(
            query: $query,
            results: $webResults,
            lowestPrice: !empty($prices) ? min($prices) : null,
            highestPrice: !empty($prices) ? max($prices) : null,
            searchedAt: now(),
            error: null,
            providersUsed: ['web_search'],
            isGeneric: $isGeneric,
            unitOfMeasure: $unitOfMeasure,
            serpData: $rawSerpData,
        );
    }

    /**
     * Validate AI-processed results against original SERP data.
     * 
     * This ensures AI hasn't fabricated any prices - all prices must exist
     * in the original SERP API results.
     *
     * @param array $aiResults Results from AI processing
     * @param array $serpResults Original SERP API results
     * @return array Validated results
     */
    protected function validateAgainstSerpData(array $aiResults, array $serpResults): array
    {
        // Build a set of valid prices from SERP data
        $validPrices = [];
        foreach ($serpResults as $result) {
            if (isset($result['price']) && $result['price'] > 0) {
                // Allow small float variations
                $validPrices[] = round((float) $result['price'], 2);
            }
        }

        $validated = [];
        foreach ($aiResults as $result) {
            if (!isset($result['price']) || $result['price'] === null) {
                // Allow results without prices (informational)
                $validated[] = $result;
                continue;
            }

            $price = round((float) $result['price'], 2);
            
            // Check if this price exists in SERP data (with 1% tolerance)
            $priceFound = false;
            foreach ($validPrices as $validPrice) {
                if (abs($price - $validPrice) / max($validPrice, 0.01) < 0.01) {
                    $priceFound = true;
                    break;
                }
            }

            if ($priceFound) {
                $validated[] = $result;
            } else {
                Log::warning('AI returned price not in SERP data, excluding', [
                    'ai_price' => $price,
                    'valid_prices' => array_slice($validPrices, 0, 10),
                    'title' => $result['title'] ?? 'unknown',
                ]);
            }
        }

        return $validated;
        } catch (\Exception $e) {
            Log::error('AI Price Search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return new AIPriceSearchResult(
                query: $query,
                results: [],
                lowestPrice: null,
                highestPrice: null,
                searchedAt: now(),
                error: 'Search failed: ' . $e->getMessage(),
                providersUsed: [],
            );
        }
    }

    /**
     * Search with caching.
     */
    public function searchWithCache(string $query, array $options = [], int $ttl = 900): AIPriceSearchResult
    {
        $cacheKey = "ai_price_search:{$this->userId}:" . md5($query . json_encode($options));

        return Cache::remember($cacheKey, $ttl, function () use ($query, $options) {
            return $this->search($query, $options);
        });
    }

    /**
     * Search with individual provider tracking (for streaming).
     */
    public function searchWithProviderTracking(string $query, array $options = [], callable $onProviderStart = null, callable $onProviderComplete = null): AIPriceSearchResult
    {
        $isGeneric = $options['is_generic'] ?? false;
        $unitOfMeasure = $options['unit_of_measure'] ?? null;
        $shopLocal = $options['shop_local'] ?? false;
        $zipCode = $options['zip_code'] ?? $this->localStore->getHomeZipCode();

        // Get local stores if shop_local is enabled
        $localStores = [];
        if ($shopLocal && $zipCode) {
            $localStores = $this->localStore->getLocalStores($zipCode);
        }

        // Start with web search
        $allResults = [];
        $providersUsed = [];

        if ($this->webSearch->isAvailable()) {
            if ($onProviderStart) {
                $onProviderStart('web_search', 'SerpAPI');
            }

            $webResults = $this->webSearch->searchPrices($query, [
                'shop_local' => $shopLocal,
                'zip_code' => $zipCode,
                'local_stores' => $localStores,
            ]);

            if (!empty($webResults)) {
                foreach ($webResults as $result) {
                    $result['source_provider'] = 'web_search';
                    $allResults[] = $result;
                }
                $providersUsed[] = 'web_search';

                if ($onProviderComplete) {
                    $onProviderComplete('web_search', $webResults, null);
                }
            }
        }

        if (!$this->isAvailable()) {
            // Only web search results available
            $prices = array_filter(array_column($allResults, 'price'));
            
            return new AIPriceSearchResult(
                query: $query,
                results: $allResults,
                lowestPrice: !empty($prices) ? min($prices) : null,
                highestPrice: !empty($prices) ? max($prices) : null,
                searchedAt: now(),
                error: empty($allResults) ? 'No results found' : null,
                providersUsed: $providersUsed,
                isGeneric: $isGeneric,
                unitOfMeasure: $unitOfMeasure,
            );
        }

        // Build prompt based on whether we have web results
        $prompt = !empty($allResults)
            ? $this->buildAnalysisPrompt($query, $allResults, $isGeneric, $unitOfMeasure, $shopLocal, $localStores)
            : $this->buildSearchPrompt($query, $isGeneric, $unitOfMeasure, $shopLocal, $zipCode, $localStores);

        // Get all providers and query them
        $providers = $this->multiAI->getProviderStatus();

        foreach ($providers as $providerName => $providerInfo) {
            if (!$providerInfo['is_active'] || !$providerInfo['has_api_key']) {
                continue;
            }

            if ($onProviderStart) {
                $onProviderStart($providerName, $providerInfo['model']);
            }

            try {
                $aiService = AIService::forUser($this->userId);
                if ($aiService && $aiService->getProviderType() === $providerName) {
                    $response = $aiService->complete($prompt);
                    $parsed = $this->parseSearchResponse($response);

                    // Merge results
                    foreach ($parsed['results'] as $result) {
                        $result['source_provider'] = $providerName;
                        $allResults[] = $result;
                    }

                    $providersUsed[] = $providerName;

                    if ($onProviderComplete) {
                        $onProviderComplete($providerName, $parsed['results'], null);
                    }
                }
            } catch (\Exception $e) {
                if ($onProviderComplete) {
                    $onProviderComplete($providerName, [], $e->getMessage());
                }
            }
        }

        // Deduplicate results by URL
        $uniqueResults = $this->deduplicateResults($allResults);

        // Sort by price
        usort($uniqueResults, fn($a, $b) => ($a['price'] ?? PHP_INT_MAX) <=> ($b['price'] ?? PHP_INT_MAX));

        $prices = array_filter(array_column($uniqueResults, 'price'));

        return new AIPriceSearchResult(
            query: $query,
            results: $uniqueResults,
            lowestPrice: !empty($prices) ? min($prices) : null,
            highestPrice: !empty($prices) ? max($prices) : null,
            searchedAt: now(),
            error: empty($uniqueResults) ? 'No results found' : null,
            providersUsed: $providersUsed,
            isGeneric: $isGeneric,
            unitOfMeasure: $unitOfMeasure,
        );
    }

    /**
     * Build the search prompt for AI (when no web search results available).
     */
    protected function buildSearchPrompt(
        string $query,
        bool $isGeneric = false,
        ?string $unitOfMeasure = null,
        bool $shopLocal = false,
        ?string $zipCode = null,
        array $localStores = []
    ): string {
        $contextParts = [];

        if ($isGeneric && $unitOfMeasure) {
            $contextParts[] = "This is a generic item sold by {$unitOfMeasure}. Include prices per {$unitOfMeasure} where applicable.";
        }

        // Get full address from local store service if available
        $address = $this->localStore->getHomeAddress();
        $locationInfo = $address ?? $zipCode;

        if ($shopLocal && $locationInfo) {
            $contextParts[] = "*** CRITICAL: LOCAL SHOPPING ONLY ***";
            $contextParts[] = "The user is located at: {$locationInfo}";
            $contextParts[] = "ONLY return prices from stores that physically exist NEAR this location.";
            $contextParts[] = "Do NOT include online-only retailers like Amazon unless they have a nearby Whole Foods/Fresh store.";
            
            if (!empty($localStores)) {
                $storeDetails = [];
                foreach (array_slice($localStores, 0, 10) as $store) {
                    $storeName = $store['store_name'] ?? 'Unknown';
                    $storeAddr = $store['address'] ?? '';
                    $storeDetails[] = $storeAddr ? "{$storeName} ({$storeAddr})" : $storeName;
                }
                $contextParts[] = "";
                $contextParts[] = "VERIFIED local stores near the user:";
                $contextParts[] = implode("\n", array_map(fn($s) => "  - {$s}", $storeDetails));
                $contextParts[] = "";
                $contextParts[] = "PRIORITIZE prices from these stores. Only include other stores if you are confident they have a location near {$locationInfo}.";
            }
        }

        $contextSection = !empty($contextParts) ? "\n\n" . implode("\n", $contextParts) : '';

        return <<<PROMPT
Find current LOCAL prices for: "{$query}"{$contextSection}

Based on your knowledge of typical retail prices at LOCAL brick-and-mortar stores, provide realistic current prices. For each result, provide:
- The exact product title/name
- Current price (numeric, in USD)
- Store name (the actual store, not just the chain)
- Product URL (if known, or empty string)
- Product image URL (if known)
- UPC/barcode (if this is a packaged retail product)
- Stock availability

Return your findings as a JSON object in this exact format:
{
    "results": [
        {
            "title": "Product name as listed",
            "price": 29.99,
            "retailer": "Store Name",
            "url": "https://example.com/product",
            "image_url": "https://example.com/image.jpg",
            "upc": "123456789012 or null",
            "in_stock": true
        }
    ],
    "is_generic": false,
    "unit_of_measure": null,
    "search_summary": "Brief summary of local prices found"
}

Important guidelines:
- FOCUS ON LOCAL STORES - the user wants to shop in person, not online
- Include grocery stores like Walmart, Target, Kroger, Aldi, Publix, Safeway, etc.
- For produce/groceries, prioritize local grocery store prices
- Prices should be numeric (no $ symbol in the price field)
- If you don't know a URL, use an empty string
- If this is a generic item (like produce), set is_generic to true and unit_of_measure to the appropriate unit
- Prices may vary by location - use typical prices for the user's geographic area

UPC/Barcode Guidelines:
- Include the UPC (12-digit barcode) for packaged retail products when known
- Generic items (produce, bulk goods, deli items) do NOT have UPCs - use null

Return ONLY the JSON object, no other text.
PROMPT;
    }

    /**
     * Build prompt for AI to analyze and structure SERP API results.
     * 
     * IMPORTANT: AI must ONLY use prices from the search results provided.
     * It must NOT invent, estimate, or hallucinate any prices.
     */
    protected function buildAnalysisPrompt(
        string $query,
        array $webResults,
        bool $isGeneric = false,
        ?string $unitOfMeasure = null,
        bool $shopLocal = false,
        array $localStores = []
    ): string {
        // Format web results as JSON for the prompt (more precise than text)
        $resultsJson = json_encode(array_slice($webResults, 0, 20), JSON_PRETTY_PRINT);

        $contextParts = [];

        if ($isGeneric && $unitOfMeasure) {
            $contextParts[] = "This is a generic item sold by {$unitOfMeasure}.";
        }

        // Get full address from local store service
        $address = $this->localStore->getHomeAddress();
        $zipCode = $this->localStore->getHomeZipCode();
        $locationInfo = $address ?? $zipCode;

        if ($shopLocal && $locationInfo) {
            $contextParts[] = "*** LOCAL SHOPPING PRIORITY ***";
            $contextParts[] = "User location: {$locationInfo}";
            $contextParts[] = "Prioritize brick-and-mortar stores near this location.";
            $contextParts[] = "De-prioritize online-only retailers.";
            
            if (!empty($localStores)) {
                $storeNames = array_column(array_slice($localStores, 0, 8), 'store_name');
                $contextParts[] = "Verified local stores: " . implode(', ', $storeNames);
                $contextParts[] = "Results from these stores should appear FIRST.";
            }
        }

        $contextSection = !empty($contextParts) ? "\n\nContext:\n" . implode("\n", $contextParts) : '';

        return <<<PROMPT
You are analyzing REAL search results from Google Shopping API (SERP API).

*** CRITICAL INSTRUCTIONS ***
1. You MUST only use prices that appear in the search results below.
2. Do NOT invent, estimate, guess, or hallucinate ANY prices.
3. Every price in your output MUST match a price from the input data.
4. If you cannot find a price in the data, use null for that result.

Search Query: "{$query}"
{$contextSection}

=== SERP API SEARCH RESULTS (REAL DATA) ===
{$resultsJson}
=== END OF SEARCH RESULTS ===

Your task:
1. Parse and structure the search results above
2. Select the best 10 matches for the query
3. Prioritize LOCAL stores (Walmart, Target, Kroger, etc.) over online-only retailers
4. Sort by: local stores first, then by price (lowest first)
5. Preserve ALL original price values exactly as they appear

Return a JSON object in this exact format:
{
    "results": [
        {
            "title": "Exact product title from results",
            "price": 29.99,
            "retailer": "Store Name from results",
            "url": "URL from results",
            "image_url": "image URL from results or null",
            "upc": "UPC if known or null",
            "in_stock": true
        }
    ],
    "is_generic": false,
    "unit_of_measure": null,
    "search_summary": "Brief summary of prices found"
}

VALIDATION RULES:
- Every "price" value MUST exist in the input search results
- Do NOT round prices differently than they appear in the input
- If a result doesn't have a price, either exclude it or set price to null
- Do NOT add results that weren't in the input data

Return ONLY the JSON object, no other text.
PROMPT;
    }

    /**
     * Parse the AI search response.
     */
    protected function parseSearchResponse(string $response): array
    {
        $defaults = [
            'results' => [],
            'is_generic' => false,
            'unit_of_measure' => null,
            'search_summary' => null,
        ];

        // Try to extract JSON from the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Validate and clean results
                $results = [];
                foreach ($json['results'] ?? [] as $item) {
                    if (!empty($item['title']) && isset($item['price'])) {
                        $results[] = [
                            'title' => $item['title'] ?? '',
                            'price' => $this->parsePrice($item['price'] ?? 0),
                            'retailer' => $item['retailer'] ?? 'Unknown',
                            'url' => $item['url'] ?? '',
                            'image_url' => $item['image_url'] ?? null,
                            'upc' => $item['upc'] ?? null,
                            'in_stock' => $item['in_stock'] ?? true,
                        ];
                    }
                }

                return [
                    'results' => $results,
                    'is_generic' => $json['is_generic'] ?? false,
                    'unit_of_measure' => $json['unit_of_measure'] ?? null,
                    'search_summary' => $json['search_summary'] ?? null,
                ];
            }
        }

        return $defaults;
    }

    /**
     * Parse price from various formats.
     */
    protected function parsePrice(mixed $price): float
    {
        if (is_numeric($price)) {
            return (float) $price;
        }

        if (is_string($price)) {
            // Remove currency symbols and parse
            $cleaned = preg_replace('/[^0-9.]/', '', $price);
            return (float) $cleaned;
        }

        return 0.0;
    }

    /**
     * Deduplicate results by unique product, keeping the lowest price.
     * Groups results by normalized product title and stores other prices.
     * Preserves UPC codes when available from any result.
     */
    protected function deduplicateResults(array $results): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $key = $this->normalizeProductTitle($result['title'] ?? '');
            
            if (empty($key)) {
                continue;
            }

            $price = $result['price'] ?? PHP_INT_MAX;

            if (!isset($grouped[$key])) {
                // First occurrence of this product
                $result['other_prices'] = [];
                $grouped[$key] = $result;
            } elseif ($price < ($grouped[$key]['price'] ?? PHP_INT_MAX)) {
                // This price is lower, swap it
                $oldResult = $grouped[$key];
                
                // Keep existing other_prices and add the old result to it
                $otherPrices = $oldResult['other_prices'] ?? [];
                $otherPrices[] = [
                    'retailer' => $oldResult['retailer'] ?? 'Unknown',
                    'price' => $oldResult['price'] ?? 0,
                    'url' => $oldResult['url'] ?? '',
                ];
                
                // Assign to new result and replace
                $result['other_prices'] = $otherPrices;
                
                // Preserve UPC from old result if new one doesn't have it
                if (empty($result['upc']) && !empty($oldResult['upc'])) {
                    $result['upc'] = $oldResult['upc'];
                }
                
                $grouped[$key] = $result;
            } else {
                // Add to other_prices
                $grouped[$key]['other_prices'][] = [
                    'retailer' => $result['retailer'] ?? 'Unknown',
                    'price' => $result['price'] ?? 0,
                    'url' => $result['url'] ?? '',
                ];
                
                // Preserve UPC from this result if grouped one doesn't have it
                if (empty($grouped[$key]['upc']) && !empty($result['upc'])) {
                    $grouped[$key]['upc'] = $result['upc'];
                }
            }
        }

        return array_values($grouped);
    }

    /**
     * Normalize a product title for comparison.
     * Removes common variations to identify the same product.
     */
    protected function normalizeProductTitle(string $title): string
    {
        // Convert to lowercase
        $normalized = strtolower(trim($title));
        
        // Remove common suffixes that indicate retailer-specific listings
        $normalized = preg_replace('/\s*[-–]\s*(amazon|walmart|target|best buy|costco|ebay).*$/i', '', $normalized);
        
        // Remove size/quantity variations at the end (e.g., "- 1 pack", "/ 2 ct")
        $normalized = preg_replace('/\s*[-\/]\s*\d+\s*(pack|ct|count|pc|pcs|piece|pieces).*$/i', '', $normalized);
        
        // Remove common filler words
        $normalized = preg_replace('/\b(new|used|refurbished|renewed|certified)\b/i', '', $normalized);
        
        // Remove extra whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Trim again
        $normalized = trim($normalized);
        
        return $normalized;
    }
}

/**
 * Result object for AI price searches.
 * 
 * Contains both the processed results and the original SERP data
 * for transparency and debugging purposes.
 */
class AIPriceSearchResult implements \JsonSerializable
{
    public function __construct(
        public string $query,
        public array $results,
        public ?float $lowestPrice,
        public ?float $highestPrice,
        public \DateTimeInterface $searchedAt,
        public ?string $error = null,
        public array $providersUsed = [],
        public bool $isGeneric = false,
        public ?string $unitOfMeasure = null,
        public ?array $serpData = null,
    ) {}

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'results' => $this->results,
            'lowest_price' => $this->lowestPrice,
            'highest_price' => $this->highestPrice,
            'searched_at' => $this->searchedAt->format(\DateTimeInterface::ATOM),
            'error' => $this->error,
            'providers_used' => $this->providersUsed,
            'is_generic' => $this->isGeneric,
            'unit_of_measure' => $this->unitOfMeasure,
            'serp_data' => $this->serpData,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function hasResults(): bool
    {
        return !empty($this->results);
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Get a summary of the SERP data for display.
     */
    public function getSerpDataSummary(): ?array
    {
        if (empty($this->serpData)) {
            return null;
        }

        return [
            'query' => $this->serpData['query'] ?? $this->query,
            'results_count' => $this->serpData['results_count'] ?? count($this->serpData['results'] ?? []),
        ];
    }
}
