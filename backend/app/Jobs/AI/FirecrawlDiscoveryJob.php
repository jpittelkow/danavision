<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Services\Crawler\FirecrawlResult;
use App\Services\Crawler\StoreDiscoveryService;
use Illuminate\Support\Facades\Log;

/**
 * FirecrawlDiscoveryJob (now powered by Crawl4AI)
 *
 * Background job for discovering product prices using the Store Registry system.
 * Uses Crawl4AI for free local web scraping + AI for price extraction.
 *
 * Tiers:
 * - Tier 1: URL templates for known stores (free scraping)
 * - Tier 2: Major retailer search pages (free scraping)
 *
 * Cost: Only LLM API calls for price extraction (~$0.002/extraction)
 *
 * This job:
 * 1. Uses StoreDiscoveryService to find prices across stores
 * 2. For local products, prioritizes local store searches
 * 3. Saves results to item_vendor_prices table
 * 4. Optionally sends results to AI for analysis
 *
 * @see docs/adr/016-crawl4ai-integration.md
 */
class FirecrawlDiscoveryJob extends BaseAIJob
{
    /**
     * The number of seconds the job can run before timing out.
     * Reduced from 10 minutes since tiered discovery is faster.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Process the Firecrawl discovery job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $productName = $inputData['product_name'] ?? null;
        $itemId = $aiJob->related_item_id;
        $logs = [];

        if (!$productName) {
            throw new \RuntimeException('No product name provided for price discovery.');
        }

        $logs[] = "Starting price discovery for: {$productName}";
        $this->updateProgress($aiJob, 10, $logs);

        // Create StoreDiscoveryService (uses Crawl4AI + AI extraction)
        $discoveryService = StoreDiscoveryService::forUser($this->userId);

        // Check if service is available (Crawl4AI + AI provider)
        if (!$discoveryService->isAvailable()) {
            throw new \RuntimeException('Price discovery not available. Please ensure AI provider is configured in Settings.');
        }

        $logs[] = "Store Discovery service initialized (Crawl4AI mode)";
        $this->updateProgress($aiJob, 20, $logs);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Perform tiered price discovery
        $shopLocal = $inputData['shop_local'] ?? false;

        $logs[] = "Using Crawl4AI for free web scraping";
        $logs[] = $shopLocal ? "Prioritizing local stores" : "Searching all online retailers";
        $this->updateProgress($aiJob, 30, $logs);

        Log::info('FirecrawlDiscoveryJob: Starting Crawl4AI discovery', [
            'ai_job_id' => $aiJob->id,
            'product_name' => $productName,
            'item_id' => $itemId,
            'shop_local' => $shopLocal,
        ]);

        // Use the StoreDiscoveryService for tiered discovery
        $result = $discoveryService->discoverPrices($productName, [
            'shop_local' => $shopLocal,
            'upc' => $inputData['upc'] ?? null,
            'brand' => $inputData['brand'] ?? null,
            'is_generic' => $inputData['is_generic'] ?? false,
            'unit_of_measure' => $inputData['unit_of_measure'] ?? null,
        ]);

        $logs[] = "Discovery completed (source: {$result->source})";
        $this->updateProgress($aiJob, 50, $logs);

        $logs[] = "Processing discovery results";
        $this->updateProgress($aiJob, 60, $logs);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        if (!$result->isSuccess()) {
            $logs[] = "ERROR: " . ($result->error ?? 'Firecrawl discovery failed');
            Log::error('FirecrawlDiscoveryJob: Discovery failed', [
                'ai_job_id' => $aiJob->id,
                'product_name' => $productName,
                'error' => $result->error,
            ]);
            throw new \RuntimeException($result->error ?? 'Firecrawl discovery failed');
        }

        $logs[] = "Found {$result->count()} price results";
        
        if ($result->hasResults()) {
            $logs[] = "Price range: \${$result->getLowestPrice()} - \${$result->getHighestPrice()}";
            
            // List stores found
            $stores = array_unique(array_column($result->results, 'store_name'));
            $logs[] = "Stores found: " . implode(', ', array_slice($stores, 0, 5));
            if (count($stores) > 5) {
                $logs[] = "...and " . (count($stores) - 5) . " more stores";
            }
        } else {
            $logs[] = "No prices found for this product";
        }

        $this->updateProgress($aiJob, 70, $logs);

        // If we have a related item, update its prices
        if ($itemId && $result->hasResults()) {
            $logs[] = "Saving prices to database...";
            $this->updateProgress($aiJob, 75, $logs);
            
            $this->updateItemPrices($itemId, $result);
            
            $logs[] = "Prices saved successfully";
        }

        $this->updateProgress($aiJob, 80, $logs);

        // Optionally analyze results with AI
        $analysis = null;
        if ($result->hasResults() && $itemId) {
            $logs[] = "Analyzing price data...";
            $this->updateProgress($aiJob, 85, $logs);
            
            $analysis = $this->analyzeResultsWithAI($result, $aiJob);
            
            if ($analysis) {
                $logs[] = "Price analysis complete";
                if (isset($analysis['best_deal'])) {
                    $logs[] = "Best deal: {$analysis['best_deal']['store']} at \${$analysis['best_deal']['price']}";
                }
            }
        }

        $this->updateProgress($aiJob, 95, $logs);

        $logs[] = "Discovery completed successfully";
        
        Log::info('FirecrawlDiscoveryJob: Completed', [
            'ai_job_id' => $aiJob->id,
            'product_name' => $productName,
            'results_count' => $result->count(),
            'lowest_price' => $result->getLowestPrice(),
        ]);

        return [
            'product_name' => $productName,
            'results' => $result->results,
            'results_count' => $result->count(),
            'lowest_price' => $result->getLowestPrice(),
            'highest_price' => $result->getHighestPrice(),
            'source' => $result->source,
            'analysis' => $analysis,
            'logs' => $logs,
        ];
    }

    /**
     * Update the related item with Firecrawl price results.
     *
     * @param int $itemId The list item ID
     * @param FirecrawlResult $result The Firecrawl result
     */
    protected function updateItemPrices(int $itemId, FirecrawlResult $result): void
    {
        $item = ListItem::find($itemId);

        if (!$item) {
            Log::warning('FirecrawlDiscoveryJob: Item not found', ['item_id' => $itemId]);
            return;
        }

        $lowestPrice = null;
        $lowestVendor = null;
        $lowestUrl = null;

        foreach ($result->results as $priceResult) {
            $vendor = ItemVendorPrice::normalizeVendor($priceResult['store_name'] ?? 'Unknown');
            $price = (float) ($priceResult['price'] ?? 0);

            if ($price <= 0) {
                continue;
            }

            // Determine stock status
            $inStock = ($priceResult['stock_status'] ?? 'in_stock') !== 'out_of_stock';

            // Find or create vendor price entry
            $vendorPrice = $item->vendorPrices()
                ->where('vendor', $vendor)
                ->first();

            if ($vendorPrice) {
                // Update existing vendor price
                $vendorPrice->updatePrice($price, $priceResult['product_url'] ?? null, $inStock);
                $vendorPrice->update([
                    'last_firecrawl_at' => now(),
                    'firecrawl_source' => $result->source,
                ]);
            } else {
                // Create new vendor price entry
                $item->vendorPrices()->create([
                    'vendor' => $vendor,
                    'vendor_sku' => null,
                    'product_url' => $priceResult['product_url'] ?? null,
                    'current_price' => $price,
                    'lowest_price' => $price,
                    'highest_price' => $price,
                    'in_stock' => $inStock,
                    'last_checked_at' => now(),
                    'last_firecrawl_at' => now(),
                    'firecrawl_source' => $result->source,
                ]);
            }

            // Track lowest price
            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
                $lowestVendor = $vendor;
                $lowestUrl = $priceResult['product_url'] ?? null;
            }
        }

        // Update the main item with best price
        if ($lowestPrice !== null) {
            $item->updatePrice($lowestPrice, $lowestVendor);

            // Update product URL if we found a better one
            if ($lowestUrl && empty($item->product_url)) {
                $item->update(['product_url' => $lowestUrl]);
            }

            // Capture price history
            PriceHistory::captureFromItem($item, 'firecrawl_discovery');
        }

        Log::info('FirecrawlDiscoveryJob: Updated item prices', [
            'item_id' => $itemId,
            'lowest_price' => $lowestPrice,
            'lowest_vendor' => $lowestVendor,
            'results_count' => count($result->results),
        ]);
    }

    /**
     * Optionally analyze Firecrawl results with AI for price insights.
     *
     * @param FirecrawlResult $result The Firecrawl result
     * @param AIJob $aiJob The AIJob model
     * @return array|null Analysis results
     */
    protected function analyzeResultsWithAI(FirecrawlResult $result, AIJob $aiJob): ?array
    {
        try {
            $aiService = $this->getAIService();
            
            if (!$aiService) {
                return null;
            }

            $inputData = $aiJob->input_data ?? [];
            $productName = $inputData['product_name'] ?? 'Unknown Product';

            // Build prompt for AI analysis
            $resultsJson = json_encode(array_slice($result->results, 0, 15), JSON_PRETTY_PRINT);
            
            $prompt = <<<PROMPT
Analyze these price search results for "{$productName}":

{$resultsJson}

Provide a brief analysis including:
1. Best deal recommendation (which store/price)
2. Price range summary (low to high)
3. Any notable patterns (e.g., sale prices, out of stock items)
4. Buying advice

Return your analysis as a JSON object:
{
    "best_deal": {
        "store": "Store name",
        "price": 99.99,
        "why": "Brief reason"
    },
    "price_range": {
        "low": 89.99,
        "high": 129.99,
        "average": 105.00
    },
    "summary": "Brief 1-2 sentence summary",
    "advice": "Brief buying advice"
}

Only return the JSON object, no other text.
PROMPT;

            $response = $aiService->complete($prompt);
            
            // Parse JSON from response
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('FirecrawlDiscoveryJob: AI analysis failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Schedule weekly discovery for all users with AI provider configured.
     * Called from the scheduler to find new prices for existing products.
     */
    public static function scheduleWeeklyDiscovery(): void
    {
        // Track delay across all users to spread jobs over time
        $globalDelay = 0;

        // Cache of users with discovery available (need AI provider)
        $userDiscoveryCache = [];

        // Process items in chunks to avoid memory issues with large databases
        ListItem::with('shoppingList')
            ->where('is_purchased', false)
            ->whereHas('shoppingList', function ($query) {
                $query->whereNotNull('user_id');
            })
            ->chunkById(100, function ($items) use (&$globalDelay, &$userDiscoveryCache) {
                foreach ($items as $item) {
                    $userId = $item->shoppingList->user_id;

                    if ($userId === null) {
                        continue;
                    }

                    // Check if user has discovery available (AI provider configured)
                    if (!isset($userDiscoveryCache[$userId])) {
                        $discoveryService = StoreDiscoveryService::forUser($userId);
                        $userDiscoveryCache[$userId] = $discoveryService->isAvailable();
                    }

                    if (!$userDiscoveryCache[$userId]) {
                        continue;
                    }

                    // Dispatch discovery job with delay
                    $aiJob = AIJob::createJob(
                        userId: $userId,
                        type: AIJob::TYPE_FIRECRAWL_DISCOVERY,
                        inputData: [
                            'product_name' => $item->product_name,
                            'product_query' => $item->product_query ?? $item->product_name,
                            'upc' => $item->upc,
                            'is_generic' => $item->is_generic ?? false,
                            'unit_of_measure' => $item->unit_of_measure,
                            'shop_local' => $item->shop_local ?? $item->shoppingList->shop_local ?? false,
                            'source' => 'weekly_discovery',
                        ],
                        relatedItemId: $item->id,
                        relatedListId: $item->shopping_list_id,
                    );

                    dispatch(new self($aiJob->id, $userId))
                        ->delay(now()->addSeconds($globalDelay));

                    $globalDelay += rand(30, 60); // Spread jobs over time

                    Log::info('FirecrawlDiscoveryJob: Scheduled weekly discovery', [
                        'user_id' => $userId,
                        'item_id' => $item->id,
                        'product' => $item->product_name,
                    ]);
                }
            });
    }
}
