<?php

namespace App\Services\AI;

use App\Services\Search\LocalStoreService;
use App\Services\Search\WebSearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIPriceSearchService
{
    protected int $userId;
    protected MultiAIService $multiAI;
    protected WebSearchService $webSearch;
    protected LocalStoreService $localStore;

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
     * Search for product prices using web search + AI analysis.
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

        // Get local stores if shop_local is enabled
        $localStores = [];
        if ($shopLocal && $zipCode) {
            $localStores = $this->localStore->getLocalStores($zipCode);
        }

        // Try web search first for real-time pricing
        $webResults = [];
        if ($this->webSearch->isAvailable()) {
            $webResults = $this->webSearch->searchPrices($query, [
                'shop_local' => $shopLocal,
                'zip_code' => $zipCode,
                'local_stores' => $localStores,
            ]);
        }

        // If web search returned results, use AI to analyze and enhance them
        if (!empty($webResults)) {
            return $this->processWebSearchResults($query, $webResults, $options, $localStores);
        }

        // Fallback: If no web search available or no results, use AI-only approach
        if (!$this->isAvailable()) {
            return new AIPriceSearchResult(
                query: $query,
                results: [],
                lowestPrice: null,
                highestPrice: null,
                searchedAt: now(),
                error: 'No AI providers configured and web search unavailable. Please set up an AI provider or SerpAPI key in Settings.',
                providersUsed: [],
            );
        }

        return $this->searchWithAIOnly($query, $options, $localStores);
    }

    /**
     * Process web search results with AI analysis.
     */
    protected function processWebSearchResults(string $query, array $webResults, array $options, array $localStores): AIPriceSearchResult
    {
        $isGeneric = $options['is_generic'] ?? false;
        $unitOfMeasure = $options['unit_of_measure'] ?? null;
        $shopLocal = $options['shop_local'] ?? false;

        // If AI is available, use it to enhance/validate the results
        if ($this->isAvailable()) {
            $prompt = $this->buildAnalysisPrompt($query, $webResults, $isGeneric, $unitOfMeasure, $shopLocal, $localStores);
            
            try {
                $result = $this->multiAI->processWithAllProviders($prompt);
                
                if (!$result['error'] && $result['aggregated_response']) {
                    $parsed = $this->parseSearchResponse($result['aggregated_response']);
                    
                    if (!empty($parsed['results'])) {
                        $prices = array_filter(array_column($parsed['results'], 'price'));
                        
                        $providersUsed = collect($result['individual_responses'] ?? [])
                            ->filter(fn($r) => $r['error'] === null)
                            ->keys()
                            ->toArray();
                        $providersUsed[] = 'web_search';
                        
                        return new AIPriceSearchResult(
                            query: $query,
                            results: $parsed['results'],
                            lowestPrice: !empty($prices) ? min($prices) : null,
                            highestPrice: !empty($prices) ? max($prices) : null,
                            searchedAt: now(),
                            error: null,
                            providersUsed: $providersUsed,
                            isGeneric: $parsed['is_generic'] ?? $isGeneric,
                            unitOfMeasure: $parsed['unit_of_measure'] ?? $unitOfMeasure,
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::warning('AI analysis of web results failed, using raw results', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Return web search results directly if AI processing fails or is unavailable
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
        );
    }

    /**
     * Search using AI only (fallback when web search unavailable).
     */
    protected function searchWithAIOnly(string $query, array $options, array $localStores): AIPriceSearchResult
    {
        $isGeneric = $options['is_generic'] ?? false;
        $unitOfMeasure = $options['unit_of_measure'] ?? null;
        $shopLocal = $options['shop_local'] ?? false;
        $zipCode = $options['zip_code'] ?? null;

        $prompt = $this->buildSearchPrompt($query, $isGeneric, $unitOfMeasure, $shopLocal, $zipCode, $localStores);

        try {
            $result = $this->multiAI->processWithAllProviders($prompt);

            if ($result['error'] && !$result['aggregated_response']) {
                return new AIPriceSearchResult(
                    query: $query,
                    results: [],
                    lowestPrice: null,
                    highestPrice: null,
                    searchedAt: now(),
                    error: $result['error'],
                    providersUsed: array_keys($result['individual_responses'] ?? []),
                );
            }

            // Parse the aggregated response
            $parsed = $this->parseSearchResponse($result['aggregated_response']);

            // Extract providers that responded successfully
            $providersUsed = collect($result['individual_responses'] ?? [])
                ->filter(fn($r) => $r['error'] === null)
                ->keys()
                ->toArray();

            // Calculate price stats
            $prices = array_filter(array_column($parsed['results'], 'price'));

            return new AIPriceSearchResult(
                query: $query,
                results: $parsed['results'],
                lowestPrice: !empty($prices) ? min($prices) : null,
                highestPrice: !empty($prices) ? max($prices) : null,
                searchedAt: now(),
                error: null,
                providersUsed: $providersUsed,
                isGeneric: $parsed['is_generic'] ?? $isGeneric,
                unitOfMeasure: $parsed['unit_of_measure'] ?? $unitOfMeasure,
            );
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

        if ($shopLocal && $zipCode) {
            $contextParts[] = "IMPORTANT: The user wants to shop LOCAL. Their location is zip code {$zipCode}.";
            $contextParts[] = "Prioritize local stores and retailers in or near this area.";
            
            if (!empty($localStores)) {
                $storeNames = array_column(array_slice($localStores, 0, 8), 'store_name');
                $contextParts[] = "Local stores near the user include: " . implode(', ', $storeNames) . ".";
                $contextParts[] = "Focus on finding prices from these local stores when possible.";
            }
        }

        $contextSection = !empty($contextParts) ? "\n\n" . implode("\n", $contextParts) : '';

        return <<<PROMPT
Find current prices for: "{$query}"{$contextSection}

Based on your knowledge of typical retail prices, provide realistic current prices from up to 10 different retailers/sellers. For each result, provide:
- The exact product title/name
- Current price (numeric, in USD)
- Retailer/store name
- Product URL (if known)
- Product image URL (if known)
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
            "in_stock": true
        }
    ],
    "is_generic": false,
    "unit_of_measure": null,
    "search_summary": "Brief summary of what was found"
}

Important guidelines:
- Provide realistic CURRENT prices based on market knowledge
- Include major retailers like Amazon, Walmart, Best Buy, Target, etc.
- For generic items (produce, groceries), include grocery store prices
- Prices should be numeric (no $ symbol in the price field)
- If you don't know a URL, use an empty string
- If this is a generic item (like produce), set is_generic to true and unit_of_measure to the appropriate unit
- Note: This is a best-effort estimate. For accurate real-time prices, configure a SerpAPI key.

Return ONLY the JSON object, no other text.
PROMPT;
    }

    /**
     * Build prompt for AI to analyze web search results.
     */
    protected function buildAnalysisPrompt(
        string $query,
        array $webResults,
        bool $isGeneric = false,
        ?string $unitOfMeasure = null,
        bool $shopLocal = false,
        array $localStores = []
    ): string {
        // Format web results for the prompt
        $formattedResults = array_map(function ($r, $i) {
            return sprintf(
                "%d. %s - $%.2f at %s%s",
                $i + 1,
                $r['title'] ?? 'Unknown',
                $r['price'] ?? 0,
                $r['retailer'] ?? 'Unknown',
                ($r['in_stock'] ?? true) ? '' : ' (Out of Stock)'
            );
        }, $webResults, array_keys($webResults));

        $resultsText = implode("\n", array_slice($formattedResults, 0, 15));

        $contextParts = [];

        if ($isGeneric && $unitOfMeasure) {
            $contextParts[] = "This is a generic item sold by {$unitOfMeasure}.";
        }

        if ($shopLocal) {
            $contextParts[] = "The user prefers LOCAL shopping.";
            if (!empty($localStores)) {
                $storeNames = array_column(array_slice($localStores, 0, 5), 'store_name');
                $contextParts[] = "Their local stores include: " . implode(', ', $storeNames);
            }
        }

        $contextSection = !empty($contextParts) ? "\n\nContext:\n" . implode("\n", $contextParts) : '';

        return <<<PROMPT
Analyze and organize these real-time price search results for: "{$query}"

Search Results:
{$resultsText}
{$contextSection}

Your task:
1. Review the search results above (these are REAL current prices from web search)
2. Validate that prices seem reasonable
3. Identify the best deals
4. If shop local is requested, prioritize local store results
5. Structure the results in the required JSON format

Return a JSON object with the best 10 results in this format:
{
    "results": [
        {
            "title": "Product name",
            "price": 29.99,
            "retailer": "Store Name",
            "url": "product url",
            "image_url": "image url or null",
            "in_stock": true
        }
    ],
    "is_generic": false,
    "unit_of_measure": null,
    "search_summary": "Brief analysis of the prices found"
}

Guidelines:
- Use the ACTUAL prices from the search results above
- Sort by best value (price + availability)
- If shop local is requested, put local store results first
- Prices should be numeric (no $ symbol)
- Keep all valid results, remove obviously wrong or duplicate entries

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
     * Deduplicate results by URL.
     */
    protected function deduplicateResults(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $result) {
            $key = $result['url'] ?? $result['title'] . $result['retailer'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $result;
            }
        }

        return $unique;
    }
}

/**
 * Result object for AI price searches.
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
}
