<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Services\Crawler\CrawlResult;
use App\Services\Crawler\StoreDiscoveryService;
use App\Support\CrawlLogger;
use Illuminate\Support\Facades\Log;

/**
 * PriceDiscoveryJob
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
class PriceDiscoveryJob extends BaseAIJob
{
    /**
     * The number of seconds the job can run before timing out.
     * Reduced from 10 minutes since tiered discovery is faster.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Process the price discovery job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $productName = $inputData['product_name'] ?? null;
        $itemId = $aiJob->related_item_id;

        // Initialize structured logger
        $logger = new CrawlLogger();

        if (!$productName) {
            throw new \RuntimeException('No product name provided for price discovery.');
        }

        $logger->info("Starting price discovery for: {$productName}");
        $this->updateProgressWithLogger($aiJob, 10, $logger);

        // Create StoreDiscoveryService (uses Crawl4AI + AI extraction)
        $discoveryService = StoreDiscoveryService::forUser($this->userId);

        // Check if service is available (Crawl4AI + AI provider)
        if (!$discoveryService->isAvailable()) {
            $logger->error('Price discovery not available. Please ensure AI provider is configured in Settings.');
            throw new \RuntimeException('Price discovery not available. Please ensure AI provider is configured in Settings.');
        }

        $logger->success("Store Discovery service initialized (Crawl4AI mode)");
        $this->updateProgressWithLogger($aiJob, 20, $logger);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            $logger->warning('Job cancelled by user');
            return $this->buildOutputData($logger, null, null, true);
        }

        // Perform tiered price discovery
        $shopLocal = $inputData['shop_local'] ?? false;

        $logger->info("Using Crawl4AI for free web scraping");
        $logger->info($shopLocal ? "Prioritizing local stores" : "Searching all online retailers");
        $this->updateProgressWithLogger($aiJob, 30, $logger);

        Log::info('PriceDiscoveryJob: Starting Crawl4AI discovery', [
            'ai_job_id' => $aiJob->id,
            'product_name' => $productName,
            'item_id' => $itemId,
            'shop_local' => $shopLocal,
        ]);

        // Pass the logger to the discovery service for detailed logging
        $result = $discoveryService->discoverPrices($productName, [
            'shop_local' => $shopLocal,
            'upc' => $inputData['upc'] ?? null,
            'brand' => $inputData['brand'] ?? null,
            'is_generic' => $inputData['is_generic'] ?? false,
            'unit_of_measure' => $inputData['unit_of_measure'] ?? null,
            'logger' => $logger,
        ]);

        // Merge any logs from the discovery service
        if ($result->metadata && isset($result->metadata['logs'])) {
            foreach ($result->metadata['logs'] as $log) {
                $logger->info($log);
            }
        }

        $logger->info("Discovery phase completed (source: {$result->source})");
        $this->updateProgressWithLogger($aiJob, 50, $logger);

        $logger->info("Processing discovery results");
        $this->updateProgressWithLogger($aiJob, 60, $logger);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            $logger->warning('Job cancelled by user');
            return $this->buildOutputData($logger, null, null, true);
        }

        if (!$result->isSuccess()) {
            $errorMsg = $result->error ?? 'Price discovery failed';
            $logger->error("Discovery failed: {$errorMsg}");
            Log::error('PriceDiscoveryJob: Discovery failed', [
                'ai_job_id' => $aiJob->id,
                'product_name' => $productName,
                'error' => $result->error,
            ]);
            throw new \RuntimeException($result->error ?? 'Price discovery failed');
        }

        // Log results summary
        if ($result->hasResults()) {
            $logger->success("Found {$result->count()} price results");
            $logger->info("Price range: \${$result->getLowestPrice()} - \${$result->getHighestPrice()}");

            // List stores found
            $stores = array_unique(array_column($result->results, 'store_name'));
            $logger->setStat('stores_found', count($stores));

            if (count($stores) <= 5) {
                $logger->info("Stores found: " . implode(', ', $stores));
            } else {
                $logger->info("Stores found: " . implode(', ', array_slice($stores, 0, 5)));
                $logger->info("...and " . (count($stores) - 5) . " more stores");
            }

            // Log individual prices for verification
            foreach ($result->results as $priceResult) {
                $logger->logPriceExtraction(
                    $priceResult['store_name'] ?? 'Unknown',
                    (float) ($priceResult['price'] ?? 0),
                    $priceResult['item_name'] ?? null
                );
            }
        } else {
            $logger->warning("No prices found for this product");
        }

        $this->updateProgressWithLogger($aiJob, 70, $logger);

        // If we have a related item, update its prices
        if ($itemId && $result->hasResults()) {
            $logger->info("Saving prices to database...");
            $this->updateProgressWithLogger($aiJob, 75, $logger);

            $this->updateItemPrices($itemId, $result);

            $logger->success("Prices saved to database successfully");
        }

        $this->updateProgressWithLogger($aiJob, 80, $logger);

        // Optionally analyze results with AI
        $analysis = null;
        if ($result->hasResults() && $itemId) {
            $logger->info("Analyzing price data with AI...");
            $this->updateProgressWithLogger($aiJob, 85, $logger);

            $analysis = $this->analyzeResultsWithAI($result, $aiJob);

            if ($analysis) {
                $logger->success("Price analysis complete");
                if (isset($analysis['best_deal'])) {
                    $logger->success("Best deal: {$analysis['best_deal']['store']} at \${$analysis['best_deal']['price']}");
                }
            } else {
                $logger->info("AI analysis skipped or unavailable");
            }
        }

        $this->updateProgressWithLogger($aiJob, 95, $logger);

        $logger->success("Price discovery completed successfully");

        Log::info('PriceDiscoveryJob: Completed', [
            'ai_job_id' => $aiJob->id,
            'product_name' => $productName,
            'results_count' => $result->count(),
            'lowest_price' => $result->getLowestPrice(),
        ]);

        return $this->buildOutputData($logger, $result, $analysis, false, $productName);
    }

    /**
     * Update job progress with structured logger.
     *
     * @param AIJob $aiJob The AI job
     * @param int $progress Progress percentage
     * @param CrawlLogger $logger The structured logger
     */
    protected function updateProgressWithLogger(AIJob $aiJob, int $progress, CrawlLogger $logger): void
    {
        // Update with both structured and simple logs for compatibility
        $this->updateProgress($aiJob, $progress, $logger->getSimpleLogs());

        // Also update output_data with structured logs
        $currentOutput = $aiJob->output_data ?? [];
        $currentOutput['progress_logs'] = $logger->getLogs();
        $currentOutput['crawl_stats'] = $logger->getStats();
        $aiJob->update(['output_data' => $currentOutput]);
    }

    /**
     * Build the final output data array.
     *
     * @param CrawlLogger $logger The structured logger
     * @param CrawlResult|null $result The crawl result
     * @param array|null $analysis The AI analysis
     * @param bool $cancelled Whether the job was cancelled
     * @param string|null $productName The product name
     * @return array
     */
    protected function buildOutputData(
        CrawlLogger $logger,
        ?CrawlResult $result,
        ?array $analysis,
        bool $cancelled,
        ?string $productName = null
    ): array {
        $output = [
            'progress_logs' => $logger->getLogs(),
            'logs' => $logger->getSimpleLogs(),
            'crawl_stats' => $logger->getStats(),
            'cancelled' => $cancelled,
        ];

        if ($productName) {
            $output['product_name'] = $productName;
        }

        if ($result) {
            $output['results'] = $result->results;
            $output['results_count'] = $result->count();
            $output['lowest_price'] = $result->getLowestPrice();
            $output['highest_price'] = $result->getHighestPrice();
            $output['source'] = $result->source;
        }

        if ($analysis) {
            $output['analysis'] = $analysis;
        }

        return $output;
    }

    /**
     * Update the related item with crawl price results.
     *
     * @param int $itemId The list item ID
     * @param CrawlResult $result The crawl result
     */
    protected function updateItemPrices(int $itemId, CrawlResult $result): void
    {
        $item = ListItem::find($itemId);

        if (!$item) {
            Log::warning('PriceDiscoveryJob: Item not found', ['item_id' => $itemId]);
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
                    'last_crawled_at' => now(),
                    'crawl_source' => $result->source,
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
                    'last_crawled_at' => now(),
                    'crawl_source' => $result->source,
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
            PriceHistory::captureFromItem($item, 'price_discovery');
        }

        Log::info('PriceDiscoveryJob: Updated item prices', [
            'item_id' => $itemId,
            'lowest_price' => $lowestPrice,
            'lowest_vendor' => $lowestVendor,
            'results_count' => count($result->results),
        ]);
    }

    /**
     * Optionally analyze crawl results with AI for price insights.
     *
     * @param CrawlResult $result The crawl result
     * @param AIJob $aiJob The AIJob model
     * @return array|null Analysis results
     */
    protected function analyzeResultsWithAI(CrawlResult $result, AIJob $aiJob): ?array
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
            Log::warning('PriceDiscoveryJob: AI analysis failed', [
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
                        type: AIJob::TYPE_PRICE_DISCOVERY,
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

                    Log::info('PriceDiscoveryJob: Scheduled weekly discovery', [
                        'user_id' => $userId,
                        'item_id' => $item->id,
                        'product' => $item->product_name,
                    ]);
                }
            });
    }
}
