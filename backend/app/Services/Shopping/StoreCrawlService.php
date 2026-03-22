<?php

namespace App\Services\Shopping;

use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\Store;
use App\Services\Crawler\CrawlAIService;
use App\Services\LLM\LLMOrchestrator;
use App\Services\PriceSearch\UnitPriceNormalizer;
use App\Services\SettingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class StoreCrawlService
{
    public function __construct(
        private readonly CrawlAIService $crawlAIService,
        private readonly LLMOrchestrator $llmOrchestrator,
        private readonly UnitPriceNormalizer $unitPriceNormalizer,
        private readonly SettingService $settingService,
    ) {}

    /**
     * Crawl a store for all tracked products and update vendor prices.
     *
     * @return array{products_checked: int, prices_updated: int, errors: int}
     */
    public function crawlStore(Store $store): array
    {
        $maxProducts = (int) $this->settingService->get('price_search', 'store_crawl_max_products_per_store', 50);
        $products = $this->getProductsToCrawl($store->id, $maxProducts);

        $stats = ['products_checked' => 0, 'prices_updated' => 0, 'errors' => 0];

        // Group vendor prices by search query to avoid duplicate crawls
        $grouped = $products->groupBy(function (ItemVendorPrice $vp) {
            $item = $vp->listItem;

            return $item->product_query ?? $item->product_name ?? '';
        })->filter(fn ($group, $query) => $query !== '');

        foreach ($grouped as $query => $vendorPrices) {
            $stats['products_checked']++;

            try {
                $results = $this->scrapeAndExtract($store, $query);

                if (empty($results)) {
                    continue;
                }

                foreach ($vendorPrices as $vendorPrice) {
                    $updated = $this->updateVendorPriceFromCrawl($store, $vendorPrice, $results);
                    $stats['prices_updated'] += $updated;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                Log::warning('StoreCrawlService: scrape failed', [
                    'store' => $store->name,
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }

            // Rate limit: 500ms between requests
            usleep(500_000);
        }

        $store->update(['last_crawled_at' => now()]);

        return $stats;
    }

    /**
     * Get distinct un-purchased items tracked at this store, oldest-checked first.
     */
    public function getProductsToCrawl(int $storeId, int $limit): Collection
    {
        return ItemVendorPrice::where('store_id', $storeId)
            ->whereHas('listItem', fn ($q) => $q->where('is_purchased', false))
            ->with('listItem')
            ->orderBy('last_checked_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get()
            ->unique('list_item_id');
    }

    /**
     * Scrape a store's search page for a query. Uses CSS extraction if
     * scrape_instructions are available, otherwise falls back to LLM extraction.
     */
    public function scrapeAndExtract(Store $store, string $query): array
    {
        $url = $store->buildSearchUrl($query);
        if (! $url) {
            return [];
        }

        $scrapeInstructions = $store->scrape_instructions;

        // CSS extraction path (LLM-free)
        if ($scrapeInstructions && ! empty($scrapeInstructions['price_selector'])) {
            $scraped = $this->crawlAIService->scrapeWithCssExtraction($url, $scrapeInstructions);

            // If CSS extraction returned content, use LLM to structure it
            if (! empty($scraped['content'])) {
                return $this->extractPricesFromContent($scraped['content'], $query, $store->name, $url);
            }

            // CSS selectors may be stale — fall through to basic scrape + LLM
            Log::info('StoreCrawlService: CSS extraction empty, falling back to LLM', [
                'store' => $store->name,
                'query' => $query,
            ]);
        }

        // Basic scrape + LLM extraction fallback
        $scraped = $this->crawlAIService->scrapeUrl($url);

        if (empty($scraped['content'])) {
            return [];
        }

        return $this->extractPricesFromContent($scraped['content'], $query, $store->name, $url);
    }

    /**
     * Use LLM to extract structured price data from scraped content.
     * Mirrors CrawlAIProvider::extractPricesFromContent.
     */
    private function extractPricesFromContent(string $content, string $query, string $storeName, string $url): array
    {
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

            if (! $result['success']) {
                Log::warning('StoreCrawlService: LLM extraction failed', [
                    'store' => $storeName,
                    'error' => $result['error'] ?? 'Unknown',
                ]);

                return [];
            }

            $parsed = json_decode($result['response'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (preg_match('/```(?:json)?\s*(\[.*?\])\s*```/s', $result['response'], $matches)) {
                    $parsed = json_decode($matches[1], true);
                }
            }

            if (! is_array($parsed)) {
                return [];
            }

            return array_map(fn (array $item) => [
                'product_name' => $item['product_name'] ?? $query,
                'price' => isset($item['price']) ? (float) $item['price'] : null,
                'retailer' => $storeName,
                'url' => $item['url'] ?? $url,
                'in_stock' => $item['in_stock'] ?? true,
                'image_url' => $item['image_url'] ?? '',
                'package_size' => $item['package_size'] ?? null,
            ], $parsed);
        } catch (\Exception $e) {
            Log::error('StoreCrawlService: price extraction error', [
                'store' => $storeName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Update a single ItemVendorPrice record from crawl results.
     * Returns 1 if updated, 0 otherwise.
     */
    private function updateVendorPriceFromCrawl(Store $store, ItemVendorPrice $vendorPrice, array $results): int
    {
        // Find the best matching result for this vendor
        $bestResult = $this->findBestResult($results, $store->name);

        if (! $bestResult) {
            return 0;
        }

        $price = $bestResult['price'] ?? null;
        if ($price === null) {
            return 0;
        }

        $productName = $bestResult['product_name'] ?? ($vendorPrice->listItem->product_name ?? '');
        $packageSize = $bestResult['package_size'] ?? null;
        $unitData = $this->unitPriceNormalizer->normalize($productName, $price, $packageSize);

        if ($vendorPrice->exists) {
            $previousPrice = $vendorPrice->current_price;
            $lowestPrice = $vendorPrice->lowest_price;
            $highestPrice = $vendorPrice->highest_price;

            if ($lowestPrice === null || $price < (float) $lowestPrice) {
                $lowestPrice = $price;
            }
            if ($highestPrice === null || $price > (float) $highestPrice) {
                $highestPrice = $price;
            }

            $onSale = $previousPrice !== null && $price < (float) $previousPrice;
            $salePercent = $onSale && (float) $previousPrice > 0
                ? round((((float) $previousPrice - $price) / (float) $previousPrice) * 100, 2)
                : null;

            $vendorPrice->fill([
                'store_id' => $store->id,
                'previous_price' => $previousPrice,
                'current_price' => $price,
                'unit_price' => $unitData['unit_price'],
                'unit_quantity' => $unitData['unit_quantity'],
                'unit_type' => $unitData['unit_type'],
                'package_size' => $unitData['package_size'] ?? $vendorPrice->package_size,
                'lowest_price' => $lowestPrice,
                'highest_price' => $highestPrice,
                'on_sale' => $onSale,
                'sale_percent_off' => $salePercent,
                'in_stock' => $bestResult['in_stock'] ?? true,
                'product_url' => $bestResult['url'] ?? $vendorPrice->product_url,
                'last_checked_at' => now(),
                'last_firecrawl_at' => now(),
                'firecrawl_source' => 'scheduled_crawl',
            ]);
        } else {
            $vendorPrice->fill([
                'store_id' => $store->id,
                'current_price' => $price,
                'unit_price' => $unitData['unit_price'],
                'unit_quantity' => $unitData['unit_quantity'],
                'unit_type' => $unitData['unit_type'],
                'package_size' => $unitData['package_size'],
                'lowest_price' => $price,
                'highest_price' => $price,
                'in_stock' => $bestResult['in_stock'] ?? true,
                'product_url' => $bestResult['url'] ?? null,
                'last_checked_at' => now(),
                'last_firecrawl_at' => now(),
                'firecrawl_source' => 'scheduled_crawl',
            ]);
        }

        $vendorPrice->save();

        return 1;
    }

    /**
     * Find the best matching result from the scrape for a given store.
     */
    private function findBestResult(array $results, string $storeName): ?array
    {
        // Filter to results from this store and with a price
        $matching = array_filter($results, function (array $r) use ($storeName) {
            return ($r['retailer'] ?? '') === $storeName && ($r['price'] ?? null) !== null;
        });

        if (empty($matching)) {
            // Fall back to first result with a price
            $matching = array_filter($results, fn (array $r) => ($r['price'] ?? null) !== null);
        }

        if (empty($matching)) {
            return null;
        }

        // Return cheapest in-stock result
        usort($matching, fn ($a, $b) => ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX));

        return $matching[0];
    }
}
