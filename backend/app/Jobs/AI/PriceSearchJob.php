<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\ItemVendorPrice;
use App\Models\ListItem;
use App\Models\PriceHistory;
use App\Services\AI\AIPriceSearchService;
use Illuminate\Support\Facades\Log;

/**
 * PriceSearchJob
 * 
 * Background job for searching prices using SERP API + AI aggregation.
 * This job uses real-time data from SERP API and does NOT fabricate prices.
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

        // Create price search service
        $priceService = AIPriceSearchService::forUser($this->userId);
        $priceService->setAIJobId($aiJob->id);

        // Check if SERP API is available
        if (!$priceService->isWebSearchAvailable()) {
            throw new \RuntimeException('SERP API is not configured. Please set up a SerpAPI key in Settings.');
        }

        $this->updateProgress($aiJob, 20);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        // Perform price search
        $searchResult = $priceService->search($query, [
            'is_generic' => $inputData['is_generic'] ?? false,
            'unit_of_measure' => $inputData['unit_of_measure'] ?? null,
            'shop_local' => $inputData['shop_local'] ?? true,
            'zip_code' => $inputData['zip_code'] ?? null,
        ]);

        $this->updateProgress($aiJob, 70);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        // If we have a related item, update its prices
        if ($itemId && !$searchResult->hasError() && $searchResult->hasResults()) {
            $this->updateItemPrices($itemId, $searchResult);
        }

        $this->updateProgress($aiJob, 90);

        return [
            'query' => $searchResult->query,
            'results' => $searchResult->results,
            'lowest_price' => $searchResult->lowestPrice,
            'highest_price' => $searchResult->highestPrice,
            'providers_used' => $searchResult->providersUsed,
            'is_generic' => $searchResult->isGeneric,
            'unit_of_measure' => $searchResult->unitOfMeasure,
            'error' => $searchResult->error,
            'results_count' => count($searchResult->results),
        ];
    }

    /**
     * Update the related item with price search results.
     *
     * @param int $itemId The list item ID
     * @param \App\Services\AI\AIPriceSearchResult $searchResult The search result
     */
    protected function updateItemPrices(int $itemId, $searchResult): void
    {
        $item = ListItem::find($itemId);

        if (!$item) {
            Log::warning('PriceSearchJob: Item not found', ['item_id' => $itemId]);
            return;
        }

        $lowestPrice = null;
        $lowestVendor = null;
        $lowestUrl = null;

        foreach ($searchResult->results as $result) {
            $vendor = ItemVendorPrice::normalizeVendor($result['retailer'] ?? 'Unknown');
            $price = (float) ($result['price'] ?? 0);

            if ($price <= 0) {
                continue;
            }

            // Find or create vendor price entry
            $vendorPrice = $item->vendorPrices()
                ->where('vendor', $vendor)
                ->first();

            if ($vendorPrice) {
                $vendorPrice->updatePrice($price, $result['url'] ?? null, true);
            } else {
                $item->vendorPrices()->create([
                    'vendor' => $vendor,
                    'vendor_sku' => null,
                    'product_url' => $result['url'] ?? null,
                    'current_price' => $price,
                    'lowest_price' => $price,
                    'highest_price' => $price,
                    'in_stock' => $result['in_stock'] ?? true,
                    'last_checked_at' => now(),
                ]);
            }

            // Track lowest price
            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
                $lowestVendor = $vendor;
                $lowestUrl = $result['url'] ?? null;
            }
        }

        // Update the main item with best price
        if ($lowestPrice !== null) {
            $item->updatePrice($lowestPrice, $lowestVendor);

            // Update product URL if we found a better one
            if ($lowestUrl && empty($item->product_url)) {
                $item->update(['product_url' => $lowestUrl]);
            }

            // Update product image URL if missing
            if (empty($item->product_image_url)) {
                foreach ($searchResult->results as $result) {
                    if (!empty($result['image_url'])) {
                        $item->update(['product_image_url' => $result['image_url']]);
                        break;
                    }
                }
            }

            // Capture price history
            PriceHistory::captureFromItem($item, 'job_search');
        }

        // Update generic info from search if not already set
        if ($searchResult->isGeneric && !$item->is_generic) {
            $item->update([
                'is_generic' => true,
                'unit_of_measure' => $searchResult->unitOfMeasure,
            ]);
        }

        Log::info('PriceSearchJob: Updated item prices', [
            'item_id' => $itemId,
            'lowest_price' => $lowestPrice,
            'lowest_vendor' => $lowestVendor,
            'results_count' => count($searchResult->results),
        ]);
    }
}
