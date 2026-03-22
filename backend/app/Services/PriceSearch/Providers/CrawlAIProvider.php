<?php

namespace App\Services\PriceSearch\Providers;

use App\Models\Store;
use App\Services\Crawler\CrawlAIService;
use App\Services\LLM\LLMOrchestrator;
use Illuminate\Support\Facades\Log;

class CrawlAIProvider implements PriceProviderInterface
{
    public function __construct(
        private readonly CrawlAIService $crawlAIService,
        private readonly LLMOrchestrator $llmOrchestrator,
    ) {}

    public function search(string $query, array $options = []): array
    {
        $stores = $options['stores'] ?? $this->getDefaultStores();
        $results = [];

        foreach ($stores as $store) {
            if (empty($store['search_url_template'])) {
                continue;
            }

            $searchUrl = str_replace('{query}', urlencode($query), $store['search_url_template']);
            $zip = $options['zip'] ?? null;
            if ($zip) {
                $searchUrl = str_replace('{zip}', urlencode($zip), $searchUrl);
            }

            $storeResults = $this->scrapeStoreSearchPage($searchUrl, $query, $store);
            $results = array_merge($results, $storeResults);
        }

        return $results;
    }

    public function getName(): string
    {
        return 'crawl4ai';
    }

    public function isAvailable(): bool
    {
        return $this->crawlAIService->isAvailable();
    }

    /**
     * Scrape a store search page and extract product/price results.
     */
    private function scrapeStoreSearchPage(string $url, string $query, array $store): array
    {
        $scrapeInstructions = $store['scrape_instructions'] ?? null;

        // If the store has CSS selectors defined, use LLM-free extraction
        if ($scrapeInstructions && !empty($scrapeInstructions['price_selector'])) {
            $scraped = $this->crawlAIService->scrapeWithCssExtraction($url, $scrapeInstructions);
        } else {
            $scraped = $this->crawlAIService->scrapeUrl($url);
        }

        if (empty($scraped['content'])) {
            return [];
        }

        return $this->extractPricesFromContent(
            $scraped['content'],
            $query,
            $store['name'] ?? 'Unknown',
            $url,
        );
    }

    /**
     * Use LLM to extract structured price data from scraped markdown content.
     */
    private function extractPricesFromContent(string $content, string $query, string $storeName, string $url): array
    {
        // Truncate content to avoid token limits
        $content = mb_substr($content, 0, 8000);

        $prompt = <<<PROMPT
        Extract product prices from this store search results page for the query "{$query}" at {$storeName}.

        Page content:
        {$content}

        Return a JSON array of products found. For each product include:
        - product_name (string): The full product name
        - price (number|null): The price in USD (null if not found)
        - in_stock (boolean): Whether it appears to be in stock
        - image_url (string): Product image URL if visible
        - url (string): Product page URL if available, otherwise use "{$url}"
        - package_size (string|null): Package size if mentioned (e.g., "1 lb", "16 oz", "2 pack")

        Only include products that are a reasonable match for "{$query}".
        Return at most 5 results, ordered by relevance.
        Return ONLY the JSON array, no other text.
        PROMPT;

        try {
            $result = $this->llmOrchestrator->query(
                user: null,
                prompt: $prompt,
                systemPrompt: 'You are a product price extraction assistant. Return only valid JSON arrays.',
                mode: 'single',
            );

            if (!$result['success']) {
                Log::warning('CrawlAIProvider: LLM extraction failed', [
                    'store' => $storeName,
                    'error' => $result['error'] ?? 'Unknown',
                ]);

                return [];
            }

            $parsed = json_decode($result['response'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to extract JSON from markdown code block
                if (preg_match('/```(?:json)?\s*(\[.*?\])\s*```/s', $result['response'], $matches)) {
                    $parsed = json_decode($matches[1], true);
                }
            }

            if (!is_array($parsed)) {
                return [];
            }

            // Normalize results to match PriceProviderInterface format
            return array_map(fn (array $item) => [
                'product_name' => $item['product_name'] ?? $query,
                'price' => isset($item['price']) ? (float) $item['price'] : null,
                'retailer' => $storeName,
                'url' => $item['url'] ?? $url,
                'in_stock' => $item['in_stock'] ?? true,
                'image_url' => $item['image_url'] ?? '',
                'package_size' => $item['package_size'] ?? null,
                'provider' => 'crawl4ai',
            ], $parsed);
        } catch (\Exception $e) {
            Log::error('CrawlAIProvider: Price extraction error', [
                'store' => $storeName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get default stores with search URL templates for crawling.
     */
    private function getDefaultStores(): array
    {
        return Store::where('is_active', true)
            ->whereNotNull('search_url_template')
            ->get(['id', 'name', 'slug', 'domain', 'search_url_template', 'scrape_instructions', 'category'])
            ->toArray();
    }
}
