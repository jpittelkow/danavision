<?php

namespace App\Services\Crawler;

use App\Models\ListItem;
use App\Services\LLM\LLMOrchestrator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreDiscoveryService
{
    public function __construct(
        private readonly FirecrawlService $firecrawlService,
        private readonly LLMOrchestrator $llmOrchestrator,
    ) {}

    /**
     * Discover prices for a list item using a tiered approach.
     *
     * Tier 1: Store URL templates (fastest, cheapest)
     * Tier 2: Firecrawl Search API (moderate cost)
     * Tier 3: Agent-based search (most expensive, most thorough)
     *
     * @param ListItem $item The item to find prices for
     * @param array $stores Array of store objects with search_url_template fields
     * @return array Array of discovered prices from all tiers
     */
    public function discoverPrices(ListItem $item, array $stores): array
    {
        $results = [];

        // Tier 1: Try store URL templates first (fastest)
        $tier1Results = $this->tier1StoreTemplates($item, $stores);
        if (!empty($tier1Results)) {
            $results = array_merge($results, $tier1Results);
        }

        // Tier 2: Use Firecrawl if Tier 1 didn't find enough
        if (count($results) < 3 && $this->firecrawlService->isAvailable()) {
            $tier2Results = $this->tier2FirecrawlSearch($item);
            $results = array_merge($results, $tier2Results);
        }

        // Tier 3: Agent-based search as expensive fallback
        if (count($results) < 2 && $item->user) {
            $tier3Results = $this->tier3AgentSearch($item);
            $results = array_merge($results, $tier3Results);
        }

        return $results;
    }

    /**
     * Tier 1: Use store search URL templates to build direct search links.
     *
     * This is the fastest and cheapest approach. Each store can have a
     * search_url_template like "https://store.com/search?q={query}" that
     * we populate and scrape.
     *
     * @param ListItem $item The item to search for
     * @param array $stores Array of store objects with search_url_template
     * @return array Array of price results from template-based searches
     */
    public function tier1StoreTemplates(ListItem $item, array $stores): array
    {
        $results = [];
        $query = urlencode($item->name ?? $item->title ?? '');

        foreach ($stores as $store) {
            $template = $store['search_url_template'] ?? null;
            if (!$template) {
                continue;
            }

            $searchUrl = str_replace('{query}', $query, $template);

            try {
                $scraped = $this->firecrawlService->scrapeUrl($searchUrl);

                if (!empty($scraped['content'])) {
                    $results[] = [
                        'store' => $store['name'] ?? 'Unknown',
                        'store_id' => $store['id'] ?? null,
                        'search_url' => $searchUrl,
                        'content' => $scraped['content'],
                        'tier' => 1,
                        'metadata' => $scraped['metadata'] ?? [],
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('StoreDiscoveryService: tier 1 template scrape failed', [
                    'store' => $store['name'] ?? 'Unknown',
                    'url' => $searchUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Tier 2: Use Firecrawl Search API to find product pages.
     *
     * Moderate cost approach that searches the web for the product
     * and returns scraped content from matching pages.
     *
     * @param ListItem $item The item to search for
     * @return array Array of price results from Firecrawl search
     */
    public function tier2FirecrawlSearch(ListItem $item): array
    {
        $itemName = $item->name ?? $item->title ?? '';
        $brand = $item->brand ?? '';
        $query = trim("{$brand} {$itemName} price buy");

        $searchResults = $this->firecrawlService->searchProducts($query);

        $results = [];
        foreach ($searchResults as $searchResult) {
            $results[] = [
                'store' => $this->extractStoreFromUrl($searchResult['url'] ?? ''),
                'url' => $searchResult['url'] ?? '',
                'title' => $searchResult['title'] ?? '',
                'content' => $searchResult['content'] ?? '',
                'tier' => 2,
                'metadata' => $searchResult['metadata'] ?? [],
            ];
        }

        return $results;
    }

    /**
     * Tier 3: Agent-based search using LLM to interpret complex results.
     *
     * This is the most expensive approach and serves as a fallback when
     * Tiers 1 and 2 don't return sufficient results. Uses the LLM to
     * generate and interpret search strategies.
     *
     * @param ListItem $item The item to search for
     * @return array Array of price results from agent-based search
     */
    public function tier3AgentSearch(ListItem $item): array
    {
        $user = $item->user;
        if (!$user) {
            return [];
        }

        $itemName = $item->name ?? $item->title ?? '';
        $brand = $item->brand ?? '';
        $category = $item->category ?? '';

        $prompt = <<<PROMPT
        I need to find where to buy this product and at what price:
        Product: {$itemName}
        Brand: {$brand}
        Category: {$category}

        Based on your knowledge, provide a list of major retailers that are likely to carry this
        product, along with estimated prices. Consider:
        - Major online retailers (Amazon, Walmart, Target, etc.)
        - Category-specific retailers
        - Common price ranges for this type of product

        Return a JSON array of objects with these keys:
        - store (string): Retailer name
        - estimated_price (number|null): Estimated price in USD
        - url (string): Most likely product page URL (best guess)
        - confidence (string): "high", "medium", or "low"
        - notes (string|null): Any relevant notes

        Return ONLY the JSON array, no other text.
        PROMPT;

        $systemPrompt = 'You are a shopping assistant with knowledge of major retailers and product pricing. '
            . 'Return only valid JSON arrays. Do not include markdown formatting or code blocks.';

        try {
            $result = $this->llmOrchestrator->query(
                user: $user,
                prompt: $prompt,
                systemPrompt: $systemPrompt,
                mode: 'single',
            );

            if (!$result['success']) {
                Log::warning('StoreDiscoveryService: tier 3 agent search failed', [
                    'error' => $result['error'] ?? 'Unknown',
                ]);
                return [];
            }

            $parsed = json_decode($result['response'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('StoreDiscoveryService: failed to parse tier 3 response');
                return [];
            }

            return array_map(fn (array $entry) => array_merge($entry, ['tier' => 3]), $parsed);
        } catch (\Exception $e) {
            Log::error('StoreDiscoveryService: tier 3 agent search error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Extract a store/domain name from a URL.
     */
    private function extractStoreFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return 'Unknown';
        }

        // Remove common prefixes
        $host = preg_replace('/^(www\.|shop\.|store\.)/', '', $host);

        // Extract domain name without TLD
        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            return ucfirst($parts[0]);
        }

        return ucfirst($host);
    }
}
