<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Services\Crawler\FirecrawlService;
use App\Services\Crawler\FirecrawlResult;
use Illuminate\Support\Facades\Log;

/**
 * PriceSearchJob
 * 
 * Background job for searching prices using Firecrawl.
 * This job uses real-time web crawling for accurate pricing data.
 * 
 * @deprecated Use FirecrawlDiscoveryJob instead. This job is kept
 * for backward compatibility with existing queued jobs.
 */
class PriceSearchJob extends BaseAIJob
{
    /**
     * Process the price search job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $query = $inputData['query'] ?? $inputData['product_name'] ?? null;
        $itemId = $aiJob->related_item_id;

        if (!$query) {
            throw new \RuntimeException('No search query provided.');
        }

        $this->updateProgress($aiJob, 10);

        // Create Firecrawl service
        $firecrawlService = FirecrawlService::forUser($this->userId);

        // Check if Firecrawl is available
        if (!$firecrawlService->isAvailable()) {
            throw new \RuntimeException('Firecrawl is not configured. Please set up a Firecrawl API key in Settings.');
        }

        $this->updateProgress($aiJob, 20);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        Log::info('PriceSearchJob: Starting Firecrawl discovery', [
            'query' => $query,
            'item_id' => $itemId,
            'shop_local' => $inputData['shop_local'] ?? false,
        ]);

        // Perform price search using Firecrawl
        $result = $firecrawlService->discoverProductPrices($query, [
            'shop_local' => $inputData['shop_local'] ?? false,
            'is_generic' => $inputData['is_generic'] ?? false,
            'unit_of_measure' => $inputData['unit_of_measure'] ?? null,
        ]);

        $this->updateProgress($aiJob, 70);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        if (!$result->isSuccess()) {
            throw new \RuntimeException($result->error ?? 'Firecrawl discovery failed');
        }

        // If we have a related item, update its prices
        if ($itemId && $result->hasResults()) {
            $this->updateItemPrices($itemId, $result);
        }

        $this->updateProgress($aiJob, 90);

        return [
            'query' => $query,
            'results' => $result->results,
            'lowest_price' => $result->getLowestPrice(),
            'highest_price' => $result->getHighestPrice(),
            'providers_used' => ['firecrawl'],
            'results_count' => $result->count(),
            'source' => $result->source,
        ];
    }

    /**
     * Update the related item with price search results.
     *
     * @param int $itemId The list item ID
     * @param FirecrawlResult $result The Firecrawl result
     */
    protected function updateItemPrices(int $itemId, FirecrawlResult $result): void
    {
        $item = ListItem::find($itemId);

        if (!$item) {
            Log::warning('PriceSearchJob: Item not found', ['item_id' => $itemId]);
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

            // Find or create vendor price entry
            $vendorPrice = $item->vendorPrices()
                ->where('vendor', $vendor)
                ->first();

            $stockStatus = $priceResult['stock_status'] ?? 'in_stock';
            $inStock = $stockStatus !== 'out_of_stock';

            if ($vendorPrice) {
                $vendorPrice->updatePrice($price, $priceResult['product_url'] ?? null, true);
                $vendorPrice->update(['in_stock' => $inStock]);
            } else {
                $item->vendorPrices()->create([
                    'vendor' => $vendor,
                    'vendor_sku' => null,
                    'product_url' => $priceResult['product_url'] ?? null,
                    'current_price' => $price,
                    'lowest_price' => $price,
                    'highest_price' => $price,
                    'in_stock' => $inStock,
                    'last_checked_at' => now(),
                ]);
            }

            // Track lowest price from in-stock items
            if ($inStock && ($lowestPrice === null || $price < $lowestPrice)) {
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
            PriceHistory::captureFromItem($item, 'job_search');
        }

        // Update last_checked_at
        $item->update(['last_checked_at' => now()]);

        Log::info('PriceSearchJob: Updated item prices', [
            'item_id' => $itemId,
            'lowest_price' => $lowestPrice,
            'lowest_vendor' => $lowestVendor,
            'results_count' => $result->count(),
        ]);
    }
}
