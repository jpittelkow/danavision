<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Services\Crawler\StoreDiscoveryService;
use App\Services\Crawler\FirecrawlResult;
use Illuminate\Support\Facades\Log;

/**
 * PriceRefreshJob
 * 
 * Background job for refreshing prices for a single item.
 * Now uses Crawl4AI (via StoreDiscoveryService) for free local web crawling.
 * 
 * @deprecated Use FirecrawlDiscoveryJob or FirecrawlRefreshJob instead. 
 * This job is kept for backward compatibility with existing queued jobs
 * and has been updated to use the new Crawl4AI backend.
 */
class PriceRefreshJob extends BaseAIJob
{
    /**
     * Process the price refresh job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $itemId = $aiJob->related_item_id;

        if (!$itemId) {
            throw new \RuntimeException('No item ID provided for price refresh.');
        }

        $item = ListItem::with('shoppingList')->find($itemId);

        if (!$item) {
            throw new \RuntimeException('Item not found.');
        }

        $this->updateProgress($aiJob, 10);

        // Use StoreDiscoveryService (Crawl4AI backend) instead of old FirecrawlService
        $discoveryService = StoreDiscoveryService::forUser($this->userId);

        // Check if service is available (Crawl4AI + AI provider)
        if (!$discoveryService->isAvailable()) {
            throw new \RuntimeException('Price discovery not available. Please configure an AI provider in Settings.');
        }

        $this->updateProgress($aiJob, 20);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        // Determine if shop_local is enabled
        $shopLocal = $item->shouldShopLocal();

        // Build search query
        $searchQuery = $item->product_query ?? $item->product_name;

        Log::info('PriceRefreshJob: Starting Crawl4AI discovery', [
            'item_id' => $itemId,
            'query' => $searchQuery,
            'shop_local' => $shopLocal,
        ]);

        // Perform price search using StoreDiscoveryService (Crawl4AI)
        $result = $discoveryService->discoverPrices($searchQuery, [
            'shop_local' => $shopLocal,
            'is_generic' => $item->is_generic ?? false,
            'unit_of_measure' => $item->unit_of_measure ?? null,
            'upc' => $item->upc ?? null,
        ]);

        $this->updateProgress($aiJob, 70);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        if (!$result->isSuccess()) {
            return [
                'success' => false,
                'error' => $result->error,
                'providers_used' => ['crawl4ai'],
            ];
        }

        if (!$result->hasResults()) {
            return [
                'success' => false,
                'error' => 'No prices found for this item.',
                'providers_used' => ['crawl4ai'],
            ];
        }

        // Update vendor prices
        $lowestPrice = null;
        $lowestVendor = null;
        $resultsProcessed = 0;

        foreach ($result->results as $priceResult) {
            $vendor = ItemVendorPrice::normalizeVendor($priceResult['store_name'] ?? 'Unknown');
            $price = (float) ($priceResult['price'] ?? 0);

            if ($price <= 0) {
                continue;
            }

            $stockStatus = $priceResult['stock_status'] ?? 'in_stock';
            $inStock = $stockStatus !== 'out_of_stock';

            // Find or create vendor price entry
            $vendorPrice = $item->vendorPrices()
                ->where('vendor', $vendor)
                ->first();

            if ($vendorPrice) {
                $vendorPrice->updatePrice($price, $priceResult['product_url'] ?? null, true);
                $vendorPrice->update(['in_stock' => $inStock]);
            } else {
                $item->vendorPrices()->create([
                    'vendor' => $vendor,
                    'product_url' => $priceResult['product_url'] ?? null,
                    'current_price' => $price,
                    'lowest_price' => $price,
                    'highest_price' => $price,
                    'in_stock' => $inStock,
                    'last_checked_at' => now(),
                ]);
            }

            $resultsProcessed++;

            // Track lowest price from in-stock items
            if ($inStock && ($lowestPrice === null || $price < $lowestPrice)) {
                $lowestPrice = $price;
                $lowestVendor = $vendor;
            }
        }

        $this->updateProgress($aiJob, 85);

        // Update main item with best price
        $previousPrice = $item->current_price;
        
        if ($lowestPrice !== null) {
            $item->updatePrice($lowestPrice, $lowestVendor);

            // Capture price history
            PriceHistory::captureFromItem($item, 'user_refresh');
        }

        // Update last_checked_at
        $item->update(['last_checked_at' => now()]);

        $this->updateProgress($aiJob, 90);

        // Calculate price change
        $priceChange = null;
        $priceChangePercent = null;
        if ($previousPrice !== null && $lowestPrice !== null) {
            $priceChange = $lowestPrice - $previousPrice;
            $priceChangePercent = $previousPrice > 0 
                ? round(($priceChange / $previousPrice) * 100, 1)
                : null;
        }

        Log::info('PriceRefreshJob: Completed', [
            'item_id' => $itemId,
            'product_name' => $item->product_name,
            'lowest_price' => $lowestPrice,
            'lowest_vendor' => $lowestVendor,
            'results_processed' => $resultsProcessed,
            'price_change' => $priceChange,
        ]);

        return [
            'success' => true,
            'item_id' => $itemId,
            'product_name' => $item->product_name,
            'lowest_price' => $lowestPrice,
            'lowest_vendor' => $lowestVendor,
            'previous_price' => $previousPrice,
            'price_change' => $priceChange,
            'price_change_percent' => $priceChangePercent,
            'results_count' => $resultsProcessed,
            'providers_used' => ['crawl4ai'],
            'source' => $result->source,
        ];
    }
}
